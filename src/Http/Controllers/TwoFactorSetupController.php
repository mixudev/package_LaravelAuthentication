<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Services\TwoFactor\TwoFactorService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\ThrottleMessage;

class TwoFactorSetupController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly AuthenticationConfig $config,
        private readonly FeatureRateLimiterInterface $rateLimiter
    ) {}

    public function show(Request $request): HttpResponse|JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => (string) __('authentication::messages.unauthenticated')], 401);
        }

        $ip = \Vendor\LaravelAuthentication\Support\ClientIpResolver::resolve($request);
        $context = \Vendor\LaravelAuthentication\DTO\AuthenticationContext::fromRequest($request);
        $userId = (string) $user->getAuthIdentifier();

        if ($this->rateLimiter->tooManyAttempts('two_factor_setup', $userId, $ip, $context->clientId)) {
            return response()->json([
                'status'  => 'throttled',
                'message' => ThrottleMessage::forSeconds(
                    $this->rateLimiter->availableIn('two_factor_setup', $userId, $ip, $context->clientId)
                ),
            ], 429);
        }

        $this->rateLimiter->hit('two_factor_setup', $userId, $ip, $context->clientId);

        // Jika 2FA sudah aktif & terkonfirmasi, tolak akses ke halaman setup QR code
        if ($this->twoFactorService->isEnabledFor($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'            => (string) __('authentication::messages.two_factor_already_enabled'),
                    'two_factor_enabled' => true,
                ], 400);
            }

            $redirectUrl = \Illuminate\Support\Facades\Route::has('authentication.auth.sessions.index')
                ? route('authentication.auth.sessions.index')
                : (string) config('authentication.redirects.login', '/dashboard');

            return redirect($redirectUrl)
                ->with('status', (string) __('authentication::messages.two_factor_already_enabled'));
        }

        $setupData = $this->twoFactorService->setup($user);

        if ($request->expectsJson()) {
            return response()->json($setupData);
        }

        $viewName = $this->config->getView('two_factor_setup', 'authentication::pages.two-factor.setup');

        return response()->view($viewName, [
            'secret'        => $setupData['secret'],
            'otpauthUrl'    => $setupData['otpauth_url'],
            'qrCodeUrl'     => $setupData['qr_code_url'],
            'recoveryCodes' => $setupData['recovery_codes'],
            'brandName'     => config('authentication.ui.brand_name', config('app.name', 'Laravel')),
            'brandTagline'  => config('authentication.ui.brand_tagline', 'Pengaturan Autentikasi Dua Langkah'),
        ]);
    }

    public function confirm(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => (string) __('authentication::messages.unauthenticated')], 401);
        }

        // Jika 2FA sudah aktif, tolak konfirmasi ulang
        if ($this->twoFactorService->isEnabledFor($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => (string) __('authentication::messages.two_factor_already_enabled'),
                ], 400);
            }

            return redirect()->route('authentication.auth.sessions.index')
                ->with('status', (string) __('authentication::messages.two_factor_already_enabled'));
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = (string) $request->input('code');
        $ip = \Vendor\LaravelAuthentication\Support\ClientIpResolver::resolve($request);

        // Rate limit: brute-force TOTP 6-digit saat setup confirmation.
        // HIGH-04 FIX: Gunakan channel 'two_factor' tersendiri agar tidak terjadi
        // collision DoS dengan password confirmation ('confirm_password').
        if ($this->rateLimiter->tooManyAttempts('two_factor', (string) $user->getAuthIdentifier(), $ip)) {
            $seconds = $this->rateLimiter->availableIn('two_factor', (string) $user->getAuthIdentifier(), $ip);
            throw ValidationException::withMessages([
                'code' => [ThrottleMessage::forSeconds($seconds)],
            ]);
        }

        if (!$this->twoFactorService->confirm($user, $code)) {
            $this->rateLimiter->hit('two_factor', (string) $user->getAuthIdentifier(), $ip);

            throw ValidationException::withMessages([
                'code' => [(string) __('authentication::messages.invalid_two_factor_code')],
            ]);
        }

        $this->rateLimiter->clear('two_factor', (string) $user->getAuthIdentifier(), $ip);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => (string) __('authentication::messages.two_factor_enabled'),
            ]);
        }

        return redirect()->route('authentication.auth.sessions.index')->with('status', (string) __('authentication::messages.two_factor_enabled'));
    }

    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => (string) __('authentication::messages.unauthenticated')], 401);
        }

        $ip = \Vendor\LaravelAuthentication\Support\ClientIpResolver::resolve($request);

        // Rate limit: disable 2FA menerima password — tanpa limit attacker bisa
        // brute-force password untuk mematikan proteksi 2FA korban.
        if ($this->rateLimiter->tooManyAttempts('confirm_password', (string) $user->getAuthIdentifier(), $ip)) {
            $seconds = $this->rateLimiter->availableIn('confirm_password', (string) $user->getAuthIdentifier(), $ip);
            throw ValidationException::withMessages([
                'password' => [ThrottleMessage::forSeconds($seconds)],
            ]);
        }

        try {
            $this->twoFactorService->disable($user, (string) $request->input('password'));
        } catch (InvalidCredentialsException) {
            $this->rateLimiter->hit('confirm_password', (string) $user->getAuthIdentifier(), $ip);

            throw ValidationException::withMessages([
                'password' => [(string) __('authentication::messages.invalid_password')],
            ]);
        }

        $this->rateLimiter->clear('confirm_password', (string) $user->getAuthIdentifier(), $ip);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => (string) __('authentication::messages.two_factor_disabled'),
            ]);
        }

        return back()->with('status', (string) __('authentication::messages.two_factor_disabled'));
    }
}
