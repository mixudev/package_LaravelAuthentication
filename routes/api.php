<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\LoginController;
use Vendor\LaravelAuthentication\Http\Controllers\LogoutController;
use Vendor\LaravelAuthentication\Http\Controllers\OtpController;
use Vendor\LaravelAuthentication\Http\Controllers\PasswordResetController;
use Vendor\LaravelAuthentication\Http\Controllers\RegisterController;
use Vendor\LaravelAuthentication\Http\Controllers\SocialAuthController;

Route::group([
    'prefix'     => config('authentication.routes.api.prefix', 'api/v1/auth'),
    'middleware' => config('authentication.routes.api.middleware', ['api']),
], function () {
    // Standard Credentials Login
    Route::post('/login', [LoginController::class, 'apiLogin'])->name('api.auth.login');

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

    // Authenticated API Routes
    Route::middleware(config('authentication.routes.api.auth_middleware', ['auth:sanctum']))->group(function () {
        Route::post('/logout', [LogoutController::class, 'apiLogout'])->name('api.auth.logout');
    });
});
