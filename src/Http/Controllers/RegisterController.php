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
use Vendor\LaravelAuthentication\Http\Requests\RegisterRequest;
use Vendor\LaravelAuthentication\Services\SessionSecurityService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

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
            abort(404, 'Registration is currently disabled.');
        }

        if (view()->exists('authentication::register')) {
            return view('authentication::register');
        }

        return response()->json(['message' => 'Please register via POST.']);
    }

    /**
     * Handle Web Registration Request.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        if (!$this->registrationService->isEnabled()) {
            abort(404, 'Registration is currently disabled.');
        }

        $dto = $request->toDto();
        $context = AuthenticationContext::fromRequest($request);

        $user = $this->registrationService->register($dto, $context);

        // Auto-login user if configured
        if ($this->config->shouldAutoLoginOnRegister()) {
            $guard = $this->auth->guard($context->guard);
            if ($guard instanceof StatefulGuard && $request->hasSession()) {
                $this->sessionSecurity->loginUser($guard, $user, false, $request);
            }
        }

        return redirect()->intended($this->config->getRedirect('register', '/dashboard'))
            ->with('status', 'Registration completed successfully.');
    }

    /**
     * Handle API / Stateless JSON Registration.
     */
    public function apiRegister(RegisterRequest $request): JsonResponse
    {
        if (!$this->registrationService->isEnabled()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Registration is currently disabled.',
            ], 403);
        }

        $dto = $request->toDto();
        $context = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->registrationService->register($dto, $context);
            $token = $this->tokenManager->createToken($user, 'registration_token');

            return response()->json([
                'status'  => 'success',
                'message' => 'Account registered successfully.',
                'user'    => $user,
                'token'   => $token,
            ], 201);
        } catch (AuthenticationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
