<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\RegisterController;

/*
|--------------------------------------------------------------------------
| Registration — Public, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.registration.enabled', true)) {
    Route::post('/register', [RegisterController::class, 'apiRegister'])
        ->name('api.auth.register');
}
