<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\ConfirmPasswordController;
use Vendor\LaravelAuthentication\Http\Controllers\LoginController;
use Vendor\LaravelAuthentication\Http\Controllers\LogoutController;
use Vendor\LaravelAuthentication\Http\Controllers\OtpController;
use Vendor\LaravelAuthentication\Http\Controllers\PasswordResetController;
use Vendor\LaravelAuthentication\Http\Controllers\RegisterController;
use Vendor\LaravelAuthentication\Http\Controllers\SessionController;
use Vendor\LaravelAuthentication\Http\Controllers\SocialAuthController;
use Vendor\LaravelAuthentication\Http\Controllers\TwoFactorChallengeController;
use Vendor\LaravelAuthentication\Http\Controllers\TwoFactorSetupController;

Route::group([
    'prefix'     => config('authentication.routes.api.prefix', 'api/v1/auth'),
    'middleware' => config('authentication.routes.api.middleware', ['api']),
], function () {
    // Standard Credentials Login
    Route::post('/login', [LoginController::class, 'apiLogin'])->name('api.auth.login');

    // Two-Factor Challenge Verification
    if (config('authentication.features.two_factor.enabled', true)) {
        Route::post('/two-factor/verify', [TwoFactorChallengeController::class, 'verify'])->name('api.auth.two-factor.verify');
    }

    // Registration (Toggleable)
    if (config('authentication.features.registration.enabled', true)) {
        Route::post('/register', [RegisterController::class, 'apiRegister'])->name('api.auth.register');
    }

    // Password Reset (Toggleable)
    if (config('authentication.features.forgot_password.enabled', true)) {
        Route::post('/forgot-password', [PasswordResetController::class, 'apiSendResetLink'])->name('api.auth.password.email');
        Route::post('/reset-password', [PasswordResetController::class, 'apiResetPassword'])->name('api.auth.password.reset');
    }

    // OTP / Passwordless Authentication (Toggleable)
    if (config('authentication.features.otp.enabled', true)) {
        Route::post('/otp/send', [OtpController::class, 'apiSendOtp'])->name('api.auth.otp.send');
        Route::post('/otp/verify', [OtpController::class, 'apiVerifyOtp'])->name('api.auth.otp.verify');
    }

    // Social / OAuth Login Token Exchange (Toggleable)
    if (config('authentication.features.social.enabled', true)) {
        Route::post('/social/{provider}', [SocialAuthController::class, 'apiCallback'])->name('api.auth.social');
    }

    // Passkey / WebAuthn API (Toggleable)
    if (config('authentication.features.passkey.enabled', true)) {
        Route::post('/passkey/login-options', [\Vendor\LaravelAuthentication\Http\Controllers\PasskeyController::class, 'loginOptions'])->name('api.auth.passkey.login.options');
        Route::post('/passkey/login', [\Vendor\LaravelAuthentication\Http\Controllers\PasskeyController::class, 'login'])->name('api.auth.passkey.login');
    }

    // Authenticated API Routes
    Route::middleware(config('authentication.routes.api.auth_middleware', ['auth:sanctum']))->group(function () {
        Route::post('/logout', [LogoutController::class, 'apiLogout'])->name('api.auth.logout');

        // Passkey / WebAuthn Management API
        if (config('authentication.features.passkey.enabled', true)) {
            Route::post('/passkey/register-options', [\Vendor\LaravelAuthentication\Http\Controllers\PasskeyController::class, 'registerOptions'])->name('api.auth.passkey.register.options');
            Route::post('/passkey/register', [\Vendor\LaravelAuthentication\Http\Controllers\PasskeyController::class, 'register'])->name('api.auth.passkey.register');
            Route::delete('/passkey/{id}', [\Vendor\LaravelAuthentication\Http\Controllers\PasskeyController::class, 'destroy'])->name('api.auth.passkey.destroy');
        }

        // Confirm Password API
        if (config('authentication.features.confirm_password.enabled', true)) {
            Route::post('/confirm-password', [ConfirmPasswordController::class, 'confirm'])->name('api.auth.password.confirm');
        }

        // Two-Factor Setup API
        if (config('authentication.features.two_factor.enabled', true)) {
            Route::get('/two-factor/setup', [TwoFactorSetupController::class, 'show'])->name('api.auth.two-factor.setup');
            Route::post('/two-factor/confirm', [TwoFactorSetupController::class, 'confirm'])->name('api.auth.two-factor.confirm');
            Route::delete('/two-factor/disable', [TwoFactorSetupController::class, 'destroy'])->name('api.auth.two-factor.disable');
        }

        // Active Sessions API
        if (config('authentication.features.session_management.enabled', true)) {
            Route::get('/sessions', [SessionController::class, 'index'])->name('api.auth.sessions.index');
            Route::delete('/sessions/{id}', [SessionController::class, 'destroy'])->name('api.auth.sessions.destroy');
            Route::post('/sessions/revoke-others', [SessionController::class, 'destroyOthers'])->name('api.auth.sessions.destroy-others');
        }
    });
});
