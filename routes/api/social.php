<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\SocialAuthController;

/*
|--------------------------------------------------------------------------
| Social / OAuth — Public, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.social.enabled', true)) {
    Route::post('/social/{provider}', [SocialAuthController::class, 'apiCallback'])
        ->name('api.auth.social');
}
