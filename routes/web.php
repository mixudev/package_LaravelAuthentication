<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\EmailVerificationController;
use Vendor\LaravelAuthentication\Http\Controllers\LoginController;
use Vendor\LaravelAuthentication\Http\Controllers\LogoutController;
use Vendor\LaravelAuthentication\Http\Controllers\OtpController;
use Vendor\LaravelAuthentication\Http\Controllers\PasswordResetController;
use Vendor\LaravelAuthentication\Http\Controllers\RegisterController;
use Vendor\LaravelAuthentication\Http\Controllers\SocialAuthController;

Route::group(['middleware' => config('authentication.routes.web.middleware', ['web'])], function () {
    // Guest Routes
    Route::middleware('guest')->group(function () {
        // Standard Login
        Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [LoginController::class, 'login'])->name('login.perform');

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
    });

    // Authenticated Routes
    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

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
