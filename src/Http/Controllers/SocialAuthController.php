<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Vendor\LaravelAuthentication\Contracts\SocialAuthServiceInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Services\SessionSecurityService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class SocialAuthController extends Controller
{
    public function __construct(
        protected readonly SocialAuthServiceInterface $socialAuthService,
        protected readonly AuthFactory $auth,
        protected readonly SessionSecurityService $sessionSecurity,
        protected readonly TokenManagerInterface $tokenManager,
        protected readonly AuthenticationConfig $config
    ) {}

    /**
     * Redirect user to OAuth Provider authentication page.
     */
    public function redirect(string $provider): Response
    {
        if (!$this->socialAuthService->isProviderEnabled($provider)) {
            abort(404, "Social provider [{$provider}] is disabled or unsupported.");
        }

        return $this->socialAuthService->getRedirectResponse($provider);
    }

    /**
     * Handle OAuth Callback (Web).
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        if (!$this->socialAuthService->isProviderEnabled($provider)) {
            abort(404, "Social provider [{$provider}] is disabled or unsupported.");
        }

        $context = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->socialAuthService->handleCallback($provider, $context, stateless: false);

            $guard = $this->auth->guard($context->guard);
            if ($guard instanceof StatefulGuard && $request->hasSession()) {
                $this->sessionSecurity->loginUser($guard, $user, true, $request);
            }

            return redirect()->intended($this->config->getRedirect('login', '/dashboard'))
                ->with('status', "Successfully signed in with " . ucfirst($provider) . ".");
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->withErrors(['identifier' => "Social sign-in with {$provider} failed: " . $e->getMessage()]);
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
                'message' => "Social provider [{$provider}] is disabled or unsupported.",
            ], 403);
        }

        $context = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->socialAuthService->handleCallback($provider, $context, stateless: true);
            $token = $this->tokenManager->createToken($user, "social_{$provider}_token");

            return response()->json([
                'status'  => 'success',
                'message' => "Authenticated successfully with " . ucfirst($provider) . ".",
                'token'   => $token,
                'user'    => $user,
            ]);
        } catch (AuthenticationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => "Social authentication failed: " . $e->getMessage(),
            ], 500);
        }
    }
}
