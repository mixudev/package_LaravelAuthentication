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
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Vendor\LaravelAuthentication\Contracts\OtpServiceInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Http\Requests\SendOtpRequest;
use Vendor\LaravelAuthentication\Http\Requests\VerifyOtpRequest;
use Vendor\LaravelAuthentication\Services\AccountLockService;
use Vendor\LaravelAuthentication\Services\DeviceTrustService;
use Vendor\LaravelAuthentication\Services\SessionSecurityService;
use Vendor\LaravelAuthentication\Services\TwoFactorService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class OtpController extends Controller
{
    public function __construct(
        protected readonly OtpServiceInterface $otpService,
        protected readonly AuthFactory $auth,
        protected readonly SessionSecurityService $sessionSecurity,
        protected readonly TokenManagerInterface $tokenManager,
        protected readonly AuthenticationConfig $config,
        protected readonly AccountLockService $lockService,
        protected readonly TwoFactorService $twoFactorService,
        protected readonly DeviceTrustService $deviceTrustService,
        protected readonly CacheRepository $cache
    ) {}

    /**
     * Show OTP Request Form.
     */
    public function showRequestForm(): View|JsonResponse
    {
        if (!$this->otpService->isEnabled()) {
            abort(404, 'OTP authentication is disabled.');
        }

        $viewName = (string) config('authentication.views.otp_request', 'authentication::otp-request');

        if (view()->exists($viewName)) {
            return view($viewName);
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
        $viewName = (string) config('authentication.views.otp_verify', 'authentication::otp-verify');

        if (view()->exists($viewName)) {
            return view($viewName, ['identifier' => $identifier]);
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
        $code       = (string) $request->input('code');
        $remember   = $request->boolean('remember');
        $context    = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->otpService->verify($identifier, $code, $context);

            // BP-08 FIX: Jika OTP valid tapi user tidak ditemukan, jangan redirect sukses palsu.
            if ($user === null) {
                throw new InvalidCredentialsException('OTP verified but no account found for the given identifier.');
            }

            // BP-02 FIX: Cek account lockout sebelum login — OTP tidak boleh membypass lockout.
            if ($this->lockService->isLocked($user)) {
                throw new AccountLockedException($this->config->getLockoutDurationMinutes());
            }

            // Enforce Two-Factor Authentication if enabled for the user
            if ($this->twoFactorService->isEnabledFor($user)) {
                $isDeviceTrusted = $this->deviceTrustService->isTrusted($user, $request);
                if (!$isDeviceTrusted) {
                    if ($request->hasSession()) {
                        $request->session()->put('auth.2fa.user_id', $user->getAuthIdentifier());
                        $request->session()->put('auth.2fa.remember', $remember);
                    }
                    return redirect()->route('two-factor.challenge');
                }
            }

            $guard = $this->auth->guard($context->guard);
            if ($guard instanceof StatefulGuard && $request->hasSession()) {
                $this->sessionSecurity->loginUser($guard, $user, $remember, $request);
            }

            return redirect()->intended($this->config->getRedirect('login', '/dashboard'))
                ->with('status', 'Authentication successful.');
        } catch (AccountLockedException $e) {
            throw ValidationException::withMessages([
                'identifier' => [$e->getMessage()],
            ]);
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
        $code       = (string) $request->input('code');
        $context    = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->otpService->verify($identifier, $code, $context);

            // BP-08 FIX: Jika OTP valid tapi user tidak ditemukan, jangan return token null dengan status success.
            if ($user === null) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'OTP verified but no account found for the given identifier.',
                ], 404);
            }

            // BP-02 FIX: Cek account lockout sebelum issue token — OTP tidak boleh membypass lockout.
            if ($this->lockService->isLocked($user)) {
                return response()->json([
                    'status'  => 'locked',
                    'message' => 'Your account has been temporarily locked. Please contact support.',
                ], 423);
            }

            // Enforce Two-Factor Authentication if enabled for the user
            if ($this->twoFactorService->isEnabledFor($user)) {
                $isDeviceTrusted = $this->deviceTrustService->isTrusted($user, $request);
                if (!$isDeviceTrusted) {
                    $pendingToken = \Illuminate\Support\Str::random(64);
                    $cacheKey     = '2fa.pending.' . hash('sha256', $pendingToken);
                    $this->cache->put($cacheKey, $user->getAuthIdentifier(), now()->addMinutes(10));

                    return response()->json([
                        'status'              => 'two_factor_required',
                        'message'             => 'Two-factor authentication code required.',
                        'pending_token'       => $pendingToken,
                        'two_factor_required' => true,
                    ], 200);
                }
            }

            $token = $this->tokenManager->createToken($user, 'otp_token');

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
