<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\SessionController;

/*
|--------------------------------------------------------------------------
| Session & Device Management — Authenticated only, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.session_management.enabled', true)) {
    Route::middleware('auth')->group(function () {
        Route::get('/auth/sessions', [SessionController::class, 'index'])
            ->name('auth.sessions.index');

        Route::delete('/auth/sessions/{id}', [SessionController::class, 'destroy'])
            ->name('auth.sessions.destroy');

        Route::post('/auth/sessions/revoke-others', [SessionController::class, 'destroyOthers'])
            ->name('auth.sessions.destroy-others');
    });
}
