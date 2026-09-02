<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\SocialAuthController;

/*
|--------------------------------------------------------------------------
| Social / OAuth Login — Guest only, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.social.enabled', true)) {
    Route::middleware('guest')->group(function () {
        Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
            ->name('social.redirect');

        Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
            ->name('social.callback');
    });
}
