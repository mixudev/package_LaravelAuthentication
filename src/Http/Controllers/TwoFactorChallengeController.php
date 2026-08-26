<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Vendor\LaravelAuthentication\Services\DeviceTrustService;
use Vendor\LaravelAuthentication\Services\NewDeviceDetectionService;
use Vendor\LaravelAuthentication\Services\SessionSecurityService;
use Vendor\LaravelAuthentication\Services\TwoFactorService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly DeviceTrustService $deviceTrustService,
        private readonly NewDeviceDetectionService $newDeviceService,
        private readonly FeatureRateLimiterInterface $rateLimiter,
        private readonly SessionSecurityService $sessionSecurity,
        private readonly TokenManagerInterface $tokenService,
        private readonly AuthenticationConfig $config
    ) {}

    public function show(Request $request): HttpResponse|JsonResponse|RedirectResponse
    {
        if (!$request->session()->has('auth.2fa.user_id')) {
            return redirect()->route('login');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message'             => 'Two-factor challenge required.',
                'two_factor_required' => true,
            ]);
        }

        $viewName = $this->config->getView('two_factor_challenge', 'authentication::two-factor-challenge');

        return response()->view($viewName, [
            'brandName'    => config('authentication.ui.brand_name', config('app.name', 'Laravel')),
            'brandTagline' => config('authentication.ui.brand_tagline', 'Verifikasi 2 Langkah'),
            'allowTrust'   => $this->config->isDeviceTrustEnabled(),
        ]);
    }

    public function verify(Request $request): RedirectResponse|JsonResponse
    {
        $userId = $request->session()->get('auth.2fa.user_id') ?? $request->input('user_id');

        if (!$userId) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Invalid two-factor session.'], 400)
                : redirect()->route('login');
        }

        // Accept either TOTP 6-digit code or backup recovery code from separate fields
        $request->validate([
            'code'          => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
            'trust_device'  => ['nullable', 'boolean'],
            'remember'      => ['nullable', 'boolean'],
        ]);

        // One of the two fields must be present
        $code = trim((string) ($request->input('code') ?: $request->input('recovery_code', '')));

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => [__('authentication::messages.invalid_two_factor_code')],
            ]);
        }

        $ip = (string) $request->ip();

        if ($this->rateLimiter->tooManyAttempts('two_factor', (string) $userId, $ip)) {
            $seconds = $this->rateLimiter->availableIn('two_factor', (string) $userId, $ip);
            throw ValidationException::withMessages([
                'code' => [__('authentication::messages.throttle_error', ['seconds' => $seconds])],
            ]);
        }

        $userModel = $this->config->getUserModel();
        $user = $userModel::find($userId);

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$this->twoFactorService->verifyChallenge($user, $code)) {
            $this->rateLimiter->hit('two_factor', (string) $userId, $ip);

            throw ValidationException::withMessages([
                'code' => [__('authentication::messages.invalid_two_factor_code')],
            ]);
        }

        $this->rateLimiter->clear('two_factor', (string) $userId, $ip);
        $request->session()->forget('auth.2fa.user_id');

        $context = AuthenticationContext::fromRequest(
            $request,
            $this->config->getGuard()
        );

        // Record device / detect new device
        $this->newDeviceService->handleLogin($user, $context);

        // Web session login
        $token = null;
        if (!$request->expectsJson() && $request->hasSession()) {
            $guard = Auth::guard($this->config->getGuard());
            if ($guard instanceof StatefulGuard) {
                $remember = (bool) ($request->session()->get('auth.2fa.remember') ?? $request->input('remember', false));
                $this->sessionSecurity->loginUser($guard, $user, $remember, $request);
            }
        } else {
            $token = $this->tokenService->createToken($user);
        }

        event(new LoginSucceeded($user, $context, 'two_factor'));

        $response = $request->expectsJson()
            ? response()->json([
                'message' => 'Two-factor authentication successful.',
                'token'   => $token,
                'user'    => $user,
            ])
            : redirect()->intended($this->config->getRedirect('two_factor', '/dashboard'));

        // Handle remember this device
        if ($request->boolean('trust_device') && $this->config->isDeviceTrustEnabled()) {
            $cookie = $this->deviceTrustService->createTrustCookie($user, $request);
            if (method_exists($response, 'withCookie')) {
                $response->withCookie($cookie);
            }
        }

        return $response;
    }
}
