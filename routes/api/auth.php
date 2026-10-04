<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Support\RouteConfig;
use Vendor\LaravelAuthentication\Http\Controllers\LoginController;
use Vendor\LaravelAuthentication\Http\Controllers\LogoutController;

/*
|--------------------------------------------------------------------------
| Login — Public
|--------------------------------------------------------------------------
*/

Route::post('/login', [LoginController::class, 'apiLogin'])
    ->name('api.auth.login');

/*
|--------------------------------------------------------------------------
| Logout — Authenticated only
|--------------------------------------------------------------------------
*/

Route::middleware(
    RouteConfig::apiAuthMiddleware()
)->group(function () {
    Route::post('/logout', [LogoutController::class, 'apiLogout'])
        ->name('api.auth.logout');
});
