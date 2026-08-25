<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\LoginController;
use Vendor\LaravelAuthentication\Http\Controllers\LogoutController;
use Vendor\LaravelAuthentication\Http\Controllers\PasswordResetController;
use Vendor\LaravelAuthentication\Http\Controllers\EmailVerificationController;

Route::group(['middleware' => config('authentication.routes.web.middleware', ['web'])], function () {
    // Guest Routes
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [LoginController::class, 'login'])->name('login.perform');

        // Password Reset
        Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
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
