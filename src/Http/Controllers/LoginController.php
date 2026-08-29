<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Exceptions\TwoFactorChallengeRequiredException;
use Vendor\LaravelAuthentication\Http\Requests\LoginRequest;

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
        $viewName = (string) config('authentication.views.login', 'authentication::login');

        if (view()->exists($viewName)) {
            return view($viewName);
        }

        return response()->json(['message' => 'Please authenticate via POST.']);
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
            return redirect()->route('two-factor.challenge');
        } catch (AuthenticationThrottledException $e) {
            throw ValidationException::withMessages([
                'identifier' => ["Too many login attempts. Please try again in {$e->secondsRemaining} seconds."],
            ]);
        } catch (AccountLockedException $e) {
            throw ValidationException::withMessages([
                'identifier' => [$e->getMessage()],
            ]);
        } catch (InvalidCredentialsException) {
            $message = __('authentication::messages.invalid_credentials');
            throw ValidationException::withMessages([
                'identifier' => [$message ?: 'These credentials do not match our records.'],
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
                'message' => 'Authenticated successfully.',
                'token'   => $result->token,
                'user'    => $result->user,
            ]);
        } catch (TwoFactorChallengeRequiredException $e) {
            // BP-01 FIX: Ganti user_id langsung dengan opaque pending_token ber-TTL pendek.
            // Token ini disimpan di cache dan divalidasi oleh TwoFactorChallengeController.
            // Attacker tidak dapat menyuntikkan user_id sembarangan ke endpoint 2FA verify.
            $pendingToken = Str::random(64);
            $cacheKey     = '2fa.pending.' . hash('sha256', $pendingToken);
            $this->cache->put($cacheKey, $e->user->getAuthIdentifier(), now()->addMinutes(10));

            return response()->json([
                'status'              => 'two_factor_required',
                'message'             => 'Two-factor authentication code required.',
                'pending_token'       => $pendingToken,
                'two_factor_required' => true,
            ], 200);
        } catch (AuthenticationThrottledException $e) {
            return response()->json([
                'status'            => 'throttled',
                'message'           => 'Too many login attempts. Please try again later.',
                'seconds_remaining' => $e->secondsRemaining,
            ], 429);
        } catch (AccountLockedException $e) {
            return response()->json([
                'status'  => 'locked',
                'message' => $e->getMessage(),
            ], 423);
        } catch (InvalidCredentialsException) {
            return response()->json([
                'status'  => 'invalid_credentials',
                'message' => 'Invalid credentials.',
            ], 401);
        }
    }
}
