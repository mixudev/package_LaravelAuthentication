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
use Vendor\LaravelAuthentication\Services\TwoFactorService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class TwoFactorSetupController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly AuthenticationConfig $config
    ) {}

    public function show(Request $request): HttpResponse|JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $setupData = $this->twoFactorService->setup($user);

        if ($request->expectsJson()) {
            return response()->json($setupData);
        }

        $viewName = $this->config->getView('two_factor_setup', 'authentication::two-factor-setup');

        return response()->view($viewName, [
            'secret'        => $setupData['secret'],
            'otpauthUrl'    => $setupData['otpauth_url'],
            'recoveryCodes' => $setupData['recovery_codes'],
            'brandName'     => config('authentication.ui.brand_name', config('app.name', 'Laravel')),
            'brandTagline'  => config('authentication.ui.brand_tagline', 'Pengaturan Autentikasi Dua Langkah'),
        ]);
    }

    public function confirm(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

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
