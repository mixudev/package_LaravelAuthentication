<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

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
use Vendor\LaravelAuthentication\Http\Requests\LoginRequest;

class LoginController extends Controller
{
    public function __construct(
        protected readonly AuthenticationServiceInterface $authService
    ) {}

    /**
     * Show Web Login Form (if view exists or fallback basic form).
     */
    public function showLoginForm(): View|JsonResponse
    {
        if (view()->exists('authentication::login')) {
            return view('authentication::login');
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
        } catch (AuthenticationThrottledException $e) {
            throw ValidationException::withMessages([
                'identifier' => ["Too many login attempts. Please try again in {$e->secondsRemaining} seconds."],
            ]);
        } catch (AccountLockedException $e) {
            throw ValidationException::withMessages([
                'identifier' => [$e->getMessage()],
            ]);
        } catch (InvalidCredentialsException $e) {
            throw ValidationException::withMessages([
                'identifier' => [trans('auth.failed', [], 'These credentials do not match our records.')],
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
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'status'  => 'invalid_credentials',
                'message' => 'Invalid credentials.',
            ], 401);
        }
    }
}
