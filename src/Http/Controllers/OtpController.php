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
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Http\Requests\SendOtpRequest;
use Vendor\LaravelAuthentication\Http\Requests\VerifyOtpRequest;
use Vendor\LaravelAuthentication\Services\Otp\OtpService;
use Vendor\LaravelAuthentication\Services\Security\AccountLockService;
use Vendor\LaravelAuthentication\Services\Session\DeviceTrustService;
use Vendor\LaravelAuthentication\Services\Session\SessionSecurityService;
use Vendor\LaravelAuthentication\Services\TwoFactor\TwoFactorService;
use Vendor\LaravelAuthentication\Support\AuthenticationView;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\SafeUserPresenter;
use Vendor\LaravelAuthentication\Support\ThrottleMessage;
use Vendor\LaravelAuthentication\Support\TwoFactorPendingToken;

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
            abort(404, (string) __('authentication::messages.otp_disabled'));
        }

        // A stale published config can name a view that no longer exists; falling back
        // to JSON would make a browser GET return an API message.
        $viewName = AuthenticationView::resolve('otp_request', 'authentication::pages.otp.request');

        return view($viewName);
    }

    /**
     * Generate & Send OTP code (Web).
     */
    public function sendOtp(SendOtpRequest $request): RedirectResponse
    {
        if (!$this->otpService->isEnabled()) {
            abort(404, (string) __('authentication::messages.otp_disabled'));
        }

        $identifier = (string) $request->input('identifier');
        $context = AuthenticationContext::fromRequest($request);

        try {
            $this->otpService->generate($identifier, $context);

            return redirect()->route('authentication.otp.verify.form', ['identifier' => $identifier])
                ->with('status', (string) __('authentication::messages.otp_sent_generic'));
        } catch (AuthenticationException $e) {
            // Jangan bocorkan detail internal exception ke user.
            // Pesan generik untuk mencegah user enumeration & info disclosure.
            throw ValidationException::withMessages([
                'identifier' => [(string) __('authentication::messages.otp_send_failed')],
            ]);
        }
    }

    /**
     * Show OTP Verify Form.
     */
    public function showVerifyForm(Request $request): View|JsonResponse
    {
        if (!$this->otpService->isEnabled()) {
            abort(404, (string) __('authentication::messages.otp_disabled'));
        }

        $identifier = (string) $request->query('identifier', session('otp_identifier', ''));
        // A stale published config can name a view that no longer exists; falling back
        // to JSON would make a browser GET return an API message.
        $viewName = AuthenticationView::resolve('otp_verify', 'authentication::pages.otp.verify');

        return view($viewName, ['identifier' => $identifier]);
    }

    /**
     * Verify OTP code and login (Web).
     */
    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        if (!$this->otpService->isEnabled()) {
            abort(404, (string) __('authentication::messages.otp_disabled'));
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
                    return redirect()->route('authentication.two-factor.challenge');
                }
            }

            $guard = $this->auth->guard($context->guard);
            if ($guard instanceof StatefulGuard && $request->hasSession()) {
                $this->sessionSecurity->loginUser($guard, $user, $remember, $request);
            }

            return redirect()->intended($this->config->getRedirect('login', '/dashboard'))
                ->with('status', (string) __('authentication::messages.authenticated'));
        } catch (AccountLockedException $e) {
            throw ValidationException::withMessages([
                'identifier' => [(string) __('authentication::messages.account_locked')],
            ]);
        } catch (InvalidCredentialsException|AuthenticationException $e) {
            // Jangan bocorkan detail internal exception ke user.
            throw ValidationException::withMessages([
                'code' => [(string) __('authentication::messages.otp_invalid')],
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
                'message' => (string) __('authentication::messages.otp_disabled'),
            ], 403);
        }

        $identifier = (string) $request->input('identifier');
        $context = AuthenticationContext::fromRequest($request);

        try {
            $this->otpService->generate($identifier, $context);

            return response()->json([
                'status'  => 'success',
                'message' => (string) __('authentication::messages.otp_sent_generic'),
            ]);
        } catch (AuthenticationThrottledException $e) {
            return response()->json([
                'status'  => 'throttled',
                'message' => ThrottleMessage::forSeconds($e->secondsRemaining),
            ], 429);
        } catch (AuthenticationException $e) {
            // Jangan bocorkan detail internal (mis. "OTP was recently requested") —
            // pesan ini bisa dipakai attacker untuk mengkonfirmasi identifier valid.
            return response()->json([
                'status'  => 'error',
                'message' => (string) __('authentication::messages.otp_send_failed'),
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
                'message' => (string) __('authentication::messages.otp_disabled'),
            ], 403);
        }

        $identifier = (string) $request->input('identifier');
        $code       = (string) $request->input('code');
        $context    = AuthenticationContext::fromRequest($request);

        try {
            $user = $this->otpService->verify($identifier, $code, $context);

            if ($user === null) {
                return response()->json([
                    'status'  => 'error',
                    'message' => (string) __('authentication::messages.otp_invalid'),
                ], 401);
            }

            // Cek account lockout sebelum issue token
            if ($this->lockService->isLocked($user)) {
                return response()->json([
                    'status'  => 'locked',
                    'message' => (string) __('authentication::messages.account_locked_support'),
                ], 423);
            }

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

            $token = $this->tokenManager->createToken($user, 'otp_token');

            return response()->json([
                'status'  => 'success',
                'message' => (string) __('authentication::messages.otp_verified'),
                'token'   => $token,
                // SEC-03: safe user payload — jangan expose Eloquent model mentah (hash password dll).
                'user'    => SafeUserPresenter::present($user),
            ]);
        } catch (AuthenticationThrottledException $e) {
            return response()->json([
                'status'  => 'throttled',
                'message' => ThrottleMessage::forSeconds($e->secondsRemaining),
            ], 429);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => (string) __('authentication::messages.otp_invalid'),
            ], 401);
        } catch (AuthenticationException $e) {
            // Jangan bocorkan detail internal exception ke client.
            return response()->json([
                'status'  => 'error',
                'message' => (string) __('authentication::messages.otp_verify_failed'),
            ], 422);
        }
    }
}
