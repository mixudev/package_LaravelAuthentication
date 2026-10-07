<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Exceptions\TwoFactorChallengeRequiredException;
use Vendor\LaravelAuthentication\Http\Requests\LoginRequest;
use Vendor\LaravelAuthentication\Support\AuthenticationView;
use Vendor\LaravelAuthentication\Support\RouteConfig;
use Vendor\LaravelAuthentication\Support\SafeUserPresenter;
use Vendor\LaravelAuthentication\Support\TwoFactorPendingToken;

class LoginController extends Controller
{
    public function __construct(
        protected readonly AuthenticationServiceInterface $authService,
        protected readonly CacheRepository $cache
    ) {}

    /**
     * Show Web Login Form (if view exists or fallback basic form).
     */
    public function showLoginForm(): View|JsonResponse
    {
        // A stale published config can name a view that no longer exists. Falling
        // back to JSON here turns every GET page into "Please authenticate via
        // POST", so the canonical view wins and a genuinely missing view fails
        // loudly instead of silently.
        $viewName = AuthenticationView::resolve('login', 'authentication::pages.auth.login');

        return view($viewName);
    }

    /**
     * Handle Web Login Request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $loginData = $request->toDto();
        $context = AuthenticationContext::fromRequest($request);

        try {
            $this->authService->authenticate($loginData, $context);
            return redirect()->intended(config('authentication.redirects.login', '/dashboard'));
        } catch (TwoFactorChallengeRequiredException) {
            return redirect()->route(RouteConfig::name('two-factor.challenge'));
        } catch (AuthenticationThrottledException $e) {
            session()->flash('auth_retry_after', max(0, (int) $e->secondsRemaining));
            throw ValidationException::withMessages([
                'identifier' => [(string) __('authentication::messages.throttle_error', ['seconds' => $e->secondsRemaining])],
            ]);
        } catch (AccountLockedException $e) {
            $lockoutMinutes = (int) config('authentication.security.account_lockout.lockout_duration_mins', 15);
            session()->flash('auth_retry_after', max(0, $lockoutMinutes * 60));
            throw ValidationException::withMessages([
                'identifier' => [$e->getMessage()],
            ]);
        } catch (InvalidCredentialsException|\Vendor\LaravelAuthentication\Exceptions\InvalidStrategyException) {
            $message = (string) __('authentication::messages.invalid_credentials');
            throw ValidationException::withMessages([
                'identifier' => [$message ?: (string) __('authentication::messages.invalid_credentials')],
            ]);
        }
    }

    /**
     * Handle API / Stateless JSON Login.
     */
    public function apiLogin(LoginRequest $request): JsonResponse
    {
        $loginData = $request->toDto();
        $context = AuthenticationContext::fromRequest($request);

        try {
            $result = $this->authService->authenticate($loginData, $context);

            return response()->json([
                'status'  => 'success',
                'message' => (string) __('authentication::messages.authenticated'),
                'token'   => $result->token,
                // SEC-03: safe user payload — jangan expose Eloquent model mentah.
                'user'    => SafeUserPresenter::present($result->user),
            ]);
        } catch (TwoFactorChallengeRequiredException $e) {
            // BP-01 FIX: Ganti user_id langsung dengan opaque pending_token ber-TTL pendek.
            // Token ini disimpan di cache dan divalidasi oleh TwoFactorChallengeController.
            // Attacker tidak dapat menyuntikkan user_id sembarangan ke endpoint 2FA verify.
            $pendingTokenService = app(TwoFactorPendingToken::class);
            $pendingToken = $pendingTokenService->issue($e->user->getAuthIdentifier());

            return response()->json([
                'status'              => 'two_factor_required',
                'message'             => (string) __('authentication::messages.two_factor_required'),
                'pending_token'       => $pendingToken,
                'two_factor_required' => true,
            ], 200);
        } catch (AuthenticationThrottledException $e) {
            return response()->json([
                'status'            => 'throttled',
                'message'           => (string) __('authentication::messages.throttle_error', ['seconds' => $e->secondsRemaining]),
                'seconds_remaining' => $e->secondsRemaining,
            ], 429);
        } catch (AccountLockedException $e) {
            return response()->json([
                'status'  => 'locked',
                'message' => $e->getMessage(),
            ], 423);
        } catch (InvalidCredentialsException|\Vendor\LaravelAuthentication\Exceptions\InvalidStrategyException) {
            return response()->json([
                'status'  => 'invalid_credentials',
                'message' => (string) __('authentication::messages.invalid_credentials'),
            ], 401);
        }
    }
}
