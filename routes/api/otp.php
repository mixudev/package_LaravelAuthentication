<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Http\Controllers\OtpController;

/*
|--------------------------------------------------------------------------
| OTP / Passwordless Authentication — Public, feature-gated
|--------------------------------------------------------------------------
*/

if (config('authentication.features.otp.enabled', true)) {
    Route::post('/otp/send', [OtpController::class, 'apiSendOtp'])
        ->name('api.auth.otp.send');

    Route::post('/otp/verify', [OtpController::class, 'apiVerifyOtp'])
        ->name('api.auth.otp.verify');
}
