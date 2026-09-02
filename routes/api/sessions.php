<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\SessionController;

/*
|--------------------------------------------------------------------------
| Session Management — Authenticated only, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.session_management.enabled', true)) {
    Route::middleware(
        config('authentication.routes.api.auth_middleware', ['auth:sanctum'])
    )->group(function () {
        Route::get('/sessions', [SessionController::class, 'index'])
            ->name('api.auth.sessions.index');

        Route::delete('/sessions/{id}', [SessionController::class, 'destroy'])
            ->name('api.auth.sessions.destroy');

        Route::post('/sessions/revoke-others', [SessionController::class, 'destroyOthers'])
            ->name('api.auth.sessions.destroy-others');
    });
}
