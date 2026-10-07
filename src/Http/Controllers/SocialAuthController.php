<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\Contracts\SocialAuthServiceInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Services\Session\DeviceTrustService;
use Vendor\LaravelAuthentication\Services\Session\SessionSecurityService;
use Vendor\LaravelAuthentication\Services\TwoFactor\TwoFactorService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\RouteConfig;
use Vendor\LaravelAuthentication\Support\SafeUserPresenter;
use Vendor\LaravelAuthentication\Support\ThrottleMessage;
use Vendor\LaravelAuthentication\Support\TwoFactorPendingToken;

class SocialAuthController extends Controller
{
    public function __construct(
        protected readonly SocialAuthServiceInterface $socialAuthService,
        protected readonly AuthFactory $auth,
        protected readonly SessionSecurityService $sessionSecurity,
        protected readonly TokenManagerInterface $tokenManager,
        protected readonly AuthenticationConfig $config,
        protected readonly TwoFactorService $twoFactorService,
        protected readonly DeviceTrustService $deviceTrustService,
        protected readonly CacheRepository $cache,
        protected readonly FeatureRateLimiterInterface $rateLimiter
    ) {}

    /**
     * Redirect user to OAuth Provider authentication page.
     */
    public function redirect(string $provider): Response
    {
        if (!$this->socialAuthService->isProviderEnabled($provider)) {
            abort(404, (string) __('authentication::messages.social_provider_disabled', ['provider' => $provider]));
        }

        $ip = \Vendor\LaravelAuthentication\Support\ClientIpResolver::resolve(request());

        if ($this->rateLimiter->tooManyAttempts('social', $provider, $ip)) {
            abort(429, ThrottleMessage::forSeconds(
                $this->rateLimiter->availableIn('social', $provider, $ip)
            ));
        }

        $this->rateLimiter->hit('social', $provider, $ip);

        return $this->socialAuthService->getRedirectResponse($provider);
    }

    /**
     * Handle OAuth Callback (Web).
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        if (!$this->socialAuthService->isProviderEnabled($provider)) {
            abort(404, (string) __('authentication::messages.social_provider_disabled', ['provider' => $provider]));
        }

        $context = AuthenticationContext::fromRequest($request);

        try {
            // SEC-CRITICAL: Explicit state validation for stateful OAuth.
            // Fail-closed: state is REQUIRED when a session exists. A missing state is
            // treated exactly like a mismatched one, otherwise an attacker could simply
            // strip the parameter to bypass this check. Socialite performs the same
            // validation later, this is defence in depth.
            if ($request->hasSession()) {
                $sessionState = $request->session()->get('state');
                $callbackState = $request->input('state');

                if (!is_string($sessionState) || $sessionState === '' || !is_string($callbackState) || !hash_equals($sessionState, $callbackState)) {
                    report(new AuthenticationException("OAuth state mismatch for provider [{$provider}]."));
                    throw new AuthenticationException("Invalid OAuth state parameter. Possible CSRF attack.");
                }
            }

            $user = $this->socialAuthService->handleCallback($provider, $context, stateless: false);

            // Enforce Two-Factor Authentication if enabled for the user
            if ($this->twoFactorService->isEnabledFor($user)) {
                $isDeviceTrusted = $this->deviceTrustService->isTrusted($user, $request);
                if (!$isDeviceTrusted) {
                    if ($request->hasSession()) {
                        $request->session()->put('auth.2fa.user_id', $user->getAuthIdentifier());
                        $request->session()->put('auth.2fa.remember', true);
                    }
                    return redirect()->route(RouteConfig::name('two-factor.challenge'));
                }
            }

            $guard = $this->auth->guard($context->guard);
            if ($guard instanceof StatefulGuard && $request->hasSession()) {
                $this->sessionSecurity->loginUser($guard, $user, true, $request);
            }

            return redirect()->intended($this->config->getRedirect('login', '/dashboard'))
                ->with('status', (string) __('authentication::messages.social_auth_success', ['provider' => ucfirst($provider)]));
        } catch (AccountLockedException $e) {
            return redirect()->route(RouteConfig::name('login'))
                ->withErrors(['identifier' => (string) __('authentication::messages.account_locked')]);
        } catch (AuthenticationException $e) {
            report($e);

            return redirect()->route(RouteConfig::name('login'))
                ->withErrors(['identifier' => (string) __('authentication::messages.social_auth_failed', ['provider' => ucfirst($provider)])]);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route(RouteConfig::name('login'))
                ->withErrors(['identifier' => (string) __('authentication::messages.social_auth_failed', ['provider' => ucfirst($provider)])]);
        }
    }

    /**
     * Handle Stateless OAuth Callback (API).
     */
    public function apiCallback(string $provider, Request $request): JsonResponse
    {
        if (!$this->socialAuthService->isProviderEnabled($provider)) {
            return response()->json([
                'status'  => 'error',
                'message' => (string) __('authentication::messages.social_provider_disabled', ['provider' => $provider]),
            ], 403);
        }

        $context = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->socialAuthService->handleCallback($provider, $context, stateless: true);

            // Enforce Two-Factor Authentication if enabled for the user
            if ($this->twoFactorService->isEnabledFor($user)) {
                $isDeviceTrusted = $this->deviceTrustService->isTrusted($user, $request);
                if (!$isDeviceTrusted) {
                    $pendingToken = app(TwoFactorPendingToken::class)->issue($user->getAuthIdentifier());

                    return response()->json([
                        'status'              => 'two_factor_required',
                        'message'             => (string) __('authentication::messages.two_factor_required'),
                        'pending_token'       => $pendingToken,
                        'two_factor_required' => true,
                    ], 200);
                }
            }

            $token = $this->tokenManager->createToken($user, "social_{$provider}_token");

            return response()->json([
                'status'  => 'success',
                'message' => (string) __('authentication::messages.social_auth_success', ['provider' => ucfirst($provider)]),
                'token'   => $token,
                // SEC-03: safe user payload — jangan expose Eloquent model mentah (hash password dll).
                'user'    => SafeUserPresenter::present($user),
            ]);
        } catch (AccountLockedException $e) {
            return response()->json([
                'status'  => 'locked',
                // Pesan standar — jangan bocorkan detail lockout yang bisa membantu attacker.
                'message' => (string) __('authentication::messages.account_locked'),
            ], 423);
        } catch (AuthenticationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => (string) __('authentication::messages.social_auth_failed', ['provider' => ucfirst($provider)]),
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'status'  => 'error',
                'message' => (string) __('authentication::messages.social_auth_failed', ['provider' => ucfirst($provider)]),
            ], 500);
        }
    }
}
