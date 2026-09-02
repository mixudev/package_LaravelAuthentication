<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Services\TwoFactor\TwoFactorService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class TwoFactorSetupController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly AuthenticationConfig $config
    ) {}

    public function show(Request $request): HttpResponse|JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Jika 2FA sudah aktif & terkonfirmasi, tolak akses ke halaman setup QR code
        if ($this->twoFactorService->isEnabledFor($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'            => 'Two-factor authentication is already enabled.',
                    'two_factor_enabled' => true,
                ], 400);
            }

            $redirectUrl = \Illuminate\Support\Facades\Route::has('auth.sessions.index')
                ? route('auth.sessions.index')
                : (string) config('authentication.redirects.login', '/dashboard');

            return redirect($redirectUrl)
                ->with('status', 'Two-factor authentication is already enabled on your account.');
        }

        $setupData = $this->twoFactorService->setup($user);

        if ($request->expectsJson()) {
            return response()->json($setupData);
        }

        $viewName = $this->config->getView('two_factor_setup', 'authentication::two-factor-setup');

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
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Jika 2FA sudah aktif, tolak konfirmasi ulang
        if ($this->twoFactorService->isEnabledFor($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Two-factor authentication is already enabled.',
                ], 400);
            }

            return redirect()->route('auth.sessions.index')
                ->with('status', 'Two-factor authentication is already enabled.');
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = (string) $request->input('code');

        if (!$this->twoFactorService->confirm($user, $code)) {
            throw ValidationException::withMessages([
                'code' => [__('authentication::messages.invalid_two_factor_code')],
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Two-factor authentication enabled successfully.',
            ]);
        }

        return redirect()->route('auth.sessions.index')->with('status', __('authentication::messages.two_factor_enabled'));
    }

    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $this->twoFactorService->disable($user, (string) $request->input('password'));
        } catch (InvalidCredentialsException) {
            throw ValidationException::withMessages([
                'password' => [__('authentication::messages.invalid_password')],
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Two-factor authentication disabled successfully.',
            ]);
        }

        return back()->with('status', __('authentication::messages.two_factor_disabled'));
    }
}
