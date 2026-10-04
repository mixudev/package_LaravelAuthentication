<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Support\RouteConfig;
use Vendor\LaravelAuthentication\Http\Controllers\OtpController;

/*
|--------------------------------------------------------------------------
| OTP / Passwordless Login — Guest only, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.otp.enabled', true)) {
    Route::middleware('guest')->group(function () {
        Route::get('/otp/login', [OtpController::class, 'showRequestForm'])
            ->name(RouteConfig::name('otp.request.form'));

        Route::post('/otp/send', [OtpController::class, 'sendOtp'])
            ->name(RouteConfig::name('otp.send'));

        Route::get('/otp/verify', [OtpController::class, 'showVerifyForm'])
            ->name(RouteConfig::name('otp.verify.form'));

        Route::post('/otp/verify', [OtpController::class, 'verifyOtp'])
            ->name(RouteConfig::name('otp.verify'));
    });
}
