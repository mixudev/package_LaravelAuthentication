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
    | Public: Passkey Login (Guest)
    */
    Route::middleware('guest')->group(function () {
        Route::match(
            ['get', 'post'],
            '/auth/passkey/login-options',
            [PasskeyController::class, 'loginOptions']
        )->name('passkey.login.options');

        Route::post('/auth/passkey/login', [PasskeyController::class, 'login'])
            ->name('passkey.login');
    });

    /*
    | Authenticated: Passkey Registration & Management
    */
    Route::middleware('auth')->group(function () {
        Route::match(
            ['get', 'post'],
            '/auth/passkey/register-options',
            [PasskeyController::class, 'registerOptions']
        )->name('passkey.register.options');

        Route::post('/auth/passkey/register', [PasskeyController::class, 'register'])
            ->name('passkey.register');

        Route::delete('/auth/passkey/{id}', [PasskeyController::class, 'destroy'])
            ->name('passkey.destroy');
    });
}
