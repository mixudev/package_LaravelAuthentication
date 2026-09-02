<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\TwoFactorChallengeController;
use Vendor\LaravelAuthentication\Http\Controllers\TwoFactorSetupController;

/*
|--------------------------------------------------------------------------
| Two-Factor Authentication — feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.two_factor.enabled', true)) {
    /*
    | Public: 2FA Challenge during pending login
    */
    Route::post('/two-factor/verify', [TwoFactorChallengeController::class, 'verify'])
        ->name('api.auth.two-factor.verify');

    /*
    | Authenticated: 2FA Setup & Management
    */
    Route::middleware(
        config('authentication.routes.api.auth_middleware', ['auth:sanctum'])
    )->group(function () {
        Route::get('/two-factor/setup', [TwoFactorSetupController::class, 'show'])
            ->name('api.auth.two-factor.setup');

        Route::post('/two-factor/confirm', [TwoFactorSetupController::class, 'confirm'])
            ->name('api.auth.two-factor.confirm');

        Route::delete('/two-factor/disable', [TwoFactorSetupController::class, 'destroy'])
            ->name('api.auth.two-factor.disable');
    });
}
