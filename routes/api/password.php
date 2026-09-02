<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\PasswordResetController;

/*
|--------------------------------------------------------------------------
| Password Reset — Public, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.forgot_password.enabled', true)) {
    Route::post('/forgot-password', [PasswordResetController::class, 'apiSendResetLink'])
        ->name('api.auth.password.email');

    Route::post('/reset-password', [PasswordResetController::class, 'apiResetPassword'])
        ->name('api.auth.password.reset');
}
