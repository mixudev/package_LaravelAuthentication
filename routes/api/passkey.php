<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\PasskeyController;

/*
|--------------------------------------------------------------------------
| Passkey / WebAuthn — feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.passkey.enabled', true)) {
    /*
    | Public: Passkey Login
    */
    Route::post('/passkey/login-options', [PasskeyController::class, 'loginOptions'])
        ->name('api.auth.passkey.login.options');

    Route::post('/passkey/login', [PasskeyController::class, 'login'])
        ->name('api.auth.passkey.login');

    /*
    | Authenticated: Passkey Registration & Management
    */
    Route::middleware(
        config('authentication.routes.api.auth_middleware', ['auth:sanctum'])
    )->group(function () {
        Route::post('/passkey/register-options', [PasskeyController::class, 'registerOptions'])
            ->name('api.auth.passkey.register.options');

        Route::post('/passkey/register', [PasskeyController::class, 'register'])
            ->name('api.auth.passkey.register');

        Route::delete('/passkey/{id}', [PasskeyController::class, 'destroy'])
            ->name('api.auth.passkey.destroy');
    });
}
