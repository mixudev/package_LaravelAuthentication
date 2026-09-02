<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\ConfirmPasswordController;

/*
|--------------------------------------------------------------------------
| Confirm Password — Authenticated only, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.confirm_password.enabled', true)) {
    Route::middleware(
        config('authentication.routes.api.auth_middleware', ['auth:sanctum'])
    )->group(function () {
        Route::post('/confirm-password', [ConfirmPasswordController::class, 'confirm'])
            ->name('api.auth.password.confirm');
    });
}
