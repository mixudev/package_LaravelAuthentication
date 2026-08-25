<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Vendor\LaravelAuthentication\Contracts\OtpServiceInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Http\Requests\SendOtpRequest;
use Vendor\LaravelAuthentication\Http\Requests\VerifyOtpRequest;
use Vendor\LaravelAuthentication\Services\SessionSecurityService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class OtpController extends Controller
{
    public function __construct(
        protected readonly OtpServiceInterface $otpService,
        protected readonly AuthFactory $auth,
        protected readonly SessionSecurityService $sessionSecurity,
        protected readonly TokenManagerInterface $tokenManager,
        protected readonly AuthenticationConfig $config
    ) {}

    /**
     * Show OTP Request Form.
     */
    public function showRequestForm(): View|JsonResponse
    {
        if (!$this->otpService->isEnabled()) {
            abort(404, 'OTP authentication is disabled.');
        }

        if (view()->exists('authentication::otp-request')) {
            return view('authentication::otp-request');
        }

        return response()->json(['message' => 'Please request an OTP code via POST.']);
    }

    /**
     * Generate & Send OTP code (Web).
     */
    public function sendOtp(SendOtpRequest $request): RedirectResponse
    {
        if (!$this->otpService->isEnabled()) {
            abort(404, 'OTP authentication is disabled.');
        }

        $identifier = (string) $request->input('identifier');
        $context = AuthenticationContext::fromRequest($request);

        try {
            $this->otpService->generate($identifier, $context);

            return redirect()->route('otp.verify.form', ['identifier' => $identifier])
                ->with('status', 'A verification code has been dispatched to your identifier.');
        } catch (AuthenticationException $e) {
            throw ValidationException::withMessages([
                'identifier' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Show OTP Verify Form.
     */
    public function showVerifyForm(Request $request): View|JsonResponse
    {
        if (!$this->otpService->isEnabled()) {
            abort(404, 'OTP authentication is disabled.');
        }

        $identifier = (string) $request->query('identifier', session('otp_identifier', ''));

        if (view()->exists('authentication::otp-verify')) {
            return view('authentication::otp-verify', ['identifier' => $identifier]);
        }

        return response()->json(['message' => 'Please verify OTP code via POST.', 'identifier' => $identifier]);
    }

    /**
     * Verify OTP code and login (Web).
     */
    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        if (!$this->otpService->isEnabled()) {
            abort(404, 'OTP authentication is disabled.');
        }

        $identifier = (string) $request->input('identifier');
        $code = (string) $request->input('code');
        $remember = $request->boolean('remember');
        $context = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->otpService->verify($identifier, $code, $context);

            if ($user !== null) {
                $guard = $this->auth->guard($context->guard);
                if ($guard instanceof StatefulGuard && $request->hasSession()) {
                    $this->sessionSecurity->loginUser($guard, $user, $remember, $request);
                }
            }

            return redirect()->intended($this->config->getRedirect('login', '/dashboard'))
                ->with('status', 'Authentication successful.');
        } catch (InvalidCredentialsException|AuthenticationException $e) {
            throw ValidationException::withMessages([
                'code' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Generate & Send OTP code (API).
     */
    public function apiSendOtp(SendOtpRequest $request): JsonResponse
    {
        if (!$this->otpService->isEnabled()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'OTP authentication is disabled.',
            ], 403);
        }

        $identifier = (string) $request->input('identifier');
        $context = AuthenticationContext::fromRequest($request);

        try {
            $this->otpService->generate($identifier, $context);

            return response()->json([
                'status'  => 'success',
                'message' => 'OTP code dispatched successfully.',
            ]);
        } catch (AuthenticationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 429);
        }
    }

    /**
     * Verify OTP and return Bearer token (API).
     */
    public function apiVerifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        if (!$this->otpService->isEnabled()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'OTP authentication is disabled.',
            ], 403);
        }

        $identifier = (string) $request->input('identifier');
        $code = (string) $request->input('code');
        $context = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->otpService->verify($identifier, $code, $context);

            $token = null;
            if ($user !== null) {
                $token = $this->tokenManager->createToken($user, 'otp_token');
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'OTP verified successfully.',
                'token'   => $token,
                'user'    => $user,
            ]);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 401);
        } catch (AuthenticationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
