<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\LoginController;
use Vendor\LaravelAuthentication\Http\Controllers\LogoutController;
use Vendor\LaravelAuthentication\Http\Controllers\PasswordResetController;

Route::group([
    'prefix'     => config('authentication.routes.api.prefix', 'api/v1/auth'),
    'middleware' => config('authentication.routes.api.middleware', ['api']),
], function () {
    Route::post('/login', [LoginController::class, 'apiLogin'])->name('api.auth.login');
    Route::post('/forgot-password', [PasswordResetController::class, 'apiSendResetLink'])->name('api.auth.password.email');
    Route::post('/reset-password', [PasswordResetController::class, 'apiResetPassword'])->name('api.auth.password.reset');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [LogoutController::class, 'apiLogout'])->name('api.auth.logout');
    });
});
