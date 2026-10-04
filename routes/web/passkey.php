<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Support\RouteConfig;
use Vendor\LaravelAuthentication\Http\Controllers\PasskeyController;

/*
|--------------------------------------------------------------------------
| Passkey / WebAuthn — feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.passkey.enabled', true)) {
    /*
    | Public: Passkey Login (Guest)
    */
    Route::middleware('guest')->group(function () {
        Route::post('/auth/passkey/login-options', [PasskeyController::class, 'loginOptions'])
            ->name(RouteConfig::name('passkey.login.options'));

        Route::post('/auth/passkey/login', [PasskeyController::class, 'login'])
            ->name(RouteConfig::name('passkey.login'));
    });

    /*
    | Authenticated: Passkey Registration & Management
    */
    Route::middleware('auth')->group(function () {
        Route::post('/auth/passkey/register-options', [PasskeyController::class, 'registerOptions'])
            ->name(RouteConfig::name('passkey.register.options'));

        Route::post('/auth/passkey/register', [PasskeyController::class, 'register'])
            ->name(RouteConfig::name('passkey.register'));

        Route::delete('/auth/passkey/{id}', [PasskeyController::class, 'destroy'])
            ->name(RouteConfig::name('passkey.destroy'));
    });
}
