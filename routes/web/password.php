<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\PasswordResetController;

/*
|--------------------------------------------------------------------------
| Password Reset — Guest only, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.forgot_password.enabled', true)) {
    Route::middleware('guest')->group(function () {
        Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])
            ->name('password.request');

        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])
            ->name('password.email');

        Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
            ->name('password.reset');

        Route::post('/reset-password', [PasswordResetController::class, 'reset'])
            ->name('password.update');
    });
}
