<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Support\RouteConfig;
use Vendor\LaravelAuthentication\Http\Controllers\LoginController;
use Vendor\LaravelAuthentication\Http\Controllers\LogoutController;

/*
|--------------------------------------------------------------------------
| Login — Guest only
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])
        ->name(RouteConfig::name('login'));

    Route::post('/login', [LoginController::class, 'login'])
        ->name(RouteConfig::name('login.perform'));
});

/*
|--------------------------------------------------------------------------
| Logout — Authenticated only
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout'])
        ->name(RouteConfig::name('logout'));
});
