<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class ConfirmPasswordController extends Controller
{
    public function __construct(
        private readonly Hasher $hasher,
        private readonly AuthenticationConfig $config,
        private readonly FeatureRateLimiterInterface $rateLimiter
    ) {}

    public function show(Request $request): HttpResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Please confirm your password.',
            ]);
        }

        $viewName = $this->config->getView('confirm_password', 'authentication::confirm-password');

        return response()->view($viewName, [
            'brandName'    => config('authentication.ui.brand_name', config('app.name', 'Laravel')),
            'brandTagline' => config('authentication.ui.brand_tagline', 'Konfirmasi Password'),
        ]);
    }

    public function confirm(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user) {
            throw ValidationException::withMessages([
                'password' => [__('authentication::messages.unauthenticated')],
            ]);
        }

        $ip = (string) $request->ip();

        if ($this->rateLimiter->tooManyAttempts('confirm_password', (string) $user->getAuthIdentifier(), $ip)) {
            $seconds = $this->rateLimiter->availableIn('confirm_password', (string) $user->getAuthIdentifier(), $ip);
            throw ValidationException::withMessages([
                'password' => [__('authentication::messages.throttle_error', ['seconds' => $seconds])],
            ]);
        }

        $password = (string) $request->input('password');

        if (!$this->validateUserPassword($user, $password)) {
            $this->rateLimiter->hit('confirm_password', (string) $user->getAuthIdentifier(), $ip);

            throw ValidationException::withMessages([
                'password' => [__('authentication::messages.invalid_password')],
            ]);
        }

        $this->rateLimiter->clear('confirm_password', (string) $user->getAuthIdentifier(), $ip);

        $now = time();
        if ($request->hasSession()) {
            $request->session()->put('auth.password_confirmed_at', $now);
        } else {
            cache()->put('auth_pwd_confirmed:' . $user->getAuthIdentifier(), $now, $this->config->getConfirmPasswordTimeout());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message'   => 'Password confirmed successfully.',
                'confirmed' => true,
            ]);
        }

        return redirect()->intended(config('authentication.redirects.login', '/dashboard'));
    }

    protected function validateUserPassword(mixed $user, #[SensitiveParameter] string $password): bool
    {
        $passwordColumn = $this->config->getIdentifierColumn('password');
        $userHash = (string) ($user->{$passwordColumn} ?? '');

        if (empty($userHash)) {
            return false;
        }

        return $this->hasher->check($password, $userHash);
    }
}
