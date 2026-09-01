<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\ConfirmPasswordController;
use Vendor\LaravelAuthentication\Http\Controllers\EmailVerificationController;
use Vendor\LaravelAuthentication\Http\Controllers\LoginController;
use Vendor\LaravelAuthentication\Http\Controllers\LogoutController;
use Vendor\LaravelAuthentication\Http\Controllers\OtpController;
use Vendor\LaravelAuthentication\Http\Controllers\PasswordResetController;
use Vendor\LaravelAuthentication\Http\Controllers\RegisterController;
use Vendor\LaravelAuthentication\Http\Controllers\SessionController;
use Vendor\LaravelAuthentication\Http\Controllers\SocialAuthController;
use Vendor\LaravelAuthentication\Http\Controllers\TwoFactorChallengeController;
use Vendor\LaravelAuthentication\Http\Controllers\TwoFactorSetupController;

Route::group(['middleware' => config('authentication.routes.web.middleware', ['web'])], function () {
    // Guest Routes
    Route::middleware('guest')->group(function () {
        // Standard Login
        Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [LoginController::class, 'login'])->name('login.perform');

        // Two-Factor Challenge (during pending 2FA login)
        if (config('authentication.features.two_factor.enabled', true)) {
            Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
            Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])->name('two-factor.verify');
        }

        // Registration (Toggleable)
        if (config('authentication.features.registration.enabled', true)) {
            Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
            Route::post('/register', [RegisterController::class, 'register'])->name('register.perform');
        }

        // Password Reset (Toggleable)
        if (config('authentication.features.forgot_password.enabled', true)) {
            Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
            Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
            Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
            Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
        }

        // OTP / Passwordless Login (Toggleable)
        if (config('authentication.features.otp.enabled', true)) {
            Route::get('/otp/login', [OtpController::class, 'showRequestForm'])->name('otp.request.form');
            Route::post('/otp/send', [OtpController::class, 'sendOtp'])->name('otp.send');
            Route::get('/otp/verify', [OtpController::class, 'showVerifyForm'])->name('otp.verify.form');
            Route::post('/otp/verify', [OtpController::class, 'verifyOtp'])->name('otp.verify');
        }

        // Social / OAuth Login (Toggleable)
        if (config('authentication.features.social.enabled', true)) {
            Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
            Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
        }

        // Passkey / WebAuthn Login (Toggleable)
        if (config('authentication.features.passkey.enabled', true)) {
            Route::match(['get', 'post'], '/auth/passkey/login-options', [\Vendor\LaravelAuthentication\Http\Controllers\PasskeyController::class, 'loginOptions'])->name('passkey.login.options');
            Route::post('/auth/passkey/login', [\Vendor\LaravelAuthentication\Http\Controllers\PasskeyController::class, 'login'])->name('passkey.login');
        }
    });

    // Authenticated Routes
    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

        // Passkey / WebAuthn Management (Toggleable)
        if (config('authentication.features.passkey.enabled', true)) {
            Route::match(['get', 'post'], '/auth/passkey/register-options', [\Vendor\LaravelAuthentication\Http\Controllers\PasskeyController::class, 'registerOptions'])->name('passkey.register.options');
            Route::post('/auth/passkey/register', [\Vendor\LaravelAuthentication\Http\Controllers\PasskeyController::class, 'register'])->name('passkey.register');
            Route::delete('/auth/passkey/{id}', [\Vendor\LaravelAuthentication\Http\Controllers\PasskeyController::class, 'destroy'])->name('passkey.destroy');
        }

        // Confirm Password (Re-authentication)
        if (config('authentication.features.confirm_password.enabled', true)) {
            Route::get('/confirm-password', [ConfirmPasswordController::class, 'show'])->name('password.confirm');
            Route::post('/confirm-password', [ConfirmPasswordController::class, 'confirm'])->name('password.confirm.submit');
        }

        // 2FA Setup Management
        if (config('authentication.features.two_factor.enabled', true)) {
            Route::get('/auth/two-factor/setup', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
            Route::post('/auth/two-factor/confirm', [TwoFactorSetupController::class, 'confirm'])->name('two-factor.enable');
            Route::delete('/auth/two-factor/disable', [TwoFactorSetupController::class, 'destroy'])->name('two-factor.disable');
        }

        // Session & Device Management
        if (config('authentication.features.session_management.enabled', true)) {
            Route::get('/auth/sessions', [SessionController::class, 'index'])->name('auth.sessions.index');
            Route::delete('/auth/sessions/{id}', [SessionController::class, 'destroy'])->name('auth.sessions.destroy');
            Route::post('/auth/sessions/revoke-others', [SessionController::class, 'destroyOthers'])->name('auth.sessions.destroy-others');
        }

        // Email Verification
        Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware(['throttle:6,1'])
            ->name('verification.send');
    });
});
