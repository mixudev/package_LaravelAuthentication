<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Vendor\LaravelAuthentication\Contracts\RegistrationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Http\Requests\RegisterRequest;
use Vendor\LaravelAuthentication\Services\Session\SessionSecurityService;
use Vendor\LaravelAuthentication\Support\AuthenticationView;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\ThrottleMessage;

class RegisterController extends Controller
{
    public function __construct(
        protected readonly RegistrationServiceInterface $registrationService,
        protected readonly AuthFactory $auth,
        protected readonly SessionSecurityService $sessionSecurity,
        protected readonly TokenManagerInterface $tokenManager,
        protected readonly AuthenticationConfig $config
    ) {}

    /**
     * Show Web Registration Form.
     */
    public function showRegistrationForm(): View|JsonResponse
    {
        if (!$this->registrationService->isEnabled()) {
            abort(404, (string) __('authentication::messages.registration_disabled'));
        }

        // A stale published config can name a view that no longer exists; falling back
        // to JSON would make a browser GET return an API message.
        $viewName = AuthenticationView::resolve('register', 'authentication::pages.auth.register');

        return view($viewName, [
            'passwordPolicy' => [
                'min_length'        => config('authentication.password.validation_rules.min_length', 8),
                'require_uppercase' => config('authentication.password.validation_rules.require_uppercase', true),
                'require_lowercase' => config('authentication.password.validation_rules.require_lowercase', true),
                'require_numbers'   => config('authentication.password.validation_rules.require_numbers', true),
                'require_symbols'   => config('authentication.password.validation_rules.require_symbols', true),
                'symbols_charset'   => config('authentication.password.validation_rules.symbols_charset', '@$!%*#?&'),
            ],
        ]);
    }

    /**
     * Handle Web Registration Request.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        if (!$this->registrationService->isEnabled()) {
            abort(404, (string) __('authentication::messages.registration_disabled'));
        }

        $dto = $request->toDto();
        $context = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->registrationService->register($dto, $context);
        } catch (AuthenticationThrottledException $e) {
            return back()->withErrors([
                'email' => [ThrottleMessage::forSeconds($e->secondsRemaining)],
            ]);
        }

        // Auto-login user if configured
        if ($this->config->shouldAutoLoginOnRegister()) {
            $guard = $this->auth->guard($context->guard);
            if ($guard instanceof StatefulGuard && $request->hasSession()) {
                $this->sessionSecurity->loginUser($guard, $user, false, $request);
            }
        }

        return redirect()->intended($this->config->getRedirect('register', '/dashboard'))
            ->with('status', (string) __('authentication::messages.registered'));
    }

    /**
     * Handle API / Stateless JSON Registration.
     */
    public function apiRegister(RegisterRequest $request): JsonResponse
    {
        if (!$this->registrationService->isEnabled()) {
            return response()->json([
                'status'  => 'error',
                'message' => (string) __('authentication::messages.registration_disabled'),
            ], 403);
        }

        $dto = $request->toDto();
        $context = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->registrationService->register($dto, $context);
            $token = $this->tokenManager->createToken($user, 'registration_token');

            return response()->json([
                'status'  => 'success',
                'message' => (string) __('authentication::messages.registered'),
                'user'    => [
                    'id'    => $user->getAuthIdentifier(),
                    'name'  => $user->name ?? null,
                    'email' => $user->email ?? null,
                ],
                'token'   => $token,
            ], 201);
        } catch (AuthenticationThrottledException $e) {
            return response()->json([
                'status'            => 'throttled',
                'message'           => ThrottleMessage::forSeconds($e->secondsRemaining),
                'seconds_remaining' => $e->secondsRemaining,
            ], 429);
        } catch (AuthenticationException $e) {
            return response()->json([
                'status'  => 'error',
                // Jangan bocorkan detail internal (mis. "registration disabled") — pesan generik.
                'message' => (string) __('authentication::messages.registration_failed'),
            ], 422);
        }
    }
}
