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
    | Public: 2FA Challenge during pending login (Guest)
    */
    Route::middleware('guest')->group(function () {
        Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])
            ->name('two-factor.challenge');

        Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])
            ->name('two-factor.verify');
    });

    /*
    | Authenticated: 2FA Setup & Management
    */
    Route::middleware('auth')->group(function () {
        Route::get('/auth/two-factor/setup', [TwoFactorSetupController::class, 'show'])
            ->name('two-factor.setup');

        Route::post('/auth/two-factor/confirm', [TwoFactorSetupController::class, 'confirm'])
            ->name('two-factor.enable');

        Route::delete('/auth/two-factor/disable', [TwoFactorSetupController::class, 'destroy'])
            ->name('two-factor.disable');
    });
}
