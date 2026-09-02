<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\ConfirmPasswordController;

/*
|--------------------------------------------------------------------------
| Confirm Password / Re-authentication — Authenticated only, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.confirm_password.enabled', true)) {
    Route::middleware('auth')->group(function () {
        Route::get('/confirm-password', [ConfirmPasswordController::class, 'show'])
            ->name('password.confirm');

        Route::post('/confirm-password', [ConfirmPasswordController::class, 'confirm'])
            ->name('password.confirm.submit');
    });
}
