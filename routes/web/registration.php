<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\RegisterController;

/*
|--------------------------------------------------------------------------
| Registration — Guest only, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.registration.enabled', true)) {
    Route::middleware('guest')->group(function () {
        Route::get('/register', [RegisterController::class, 'showRegistrationForm'])
            ->name('register');

        Route::post('/register', [RegisterController::class, 'register'])
            ->name('register.perform');
    });
}
