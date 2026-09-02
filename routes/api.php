<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Authentication Package
|--------------------------------------------------------------------------
|
| Entry point untuk semua API routes paket ini. Setiap feature dipisah
| ke file tersendiri di routes/api/ agar mudah di-maintain dan dioverride.
|
| File ini di-load oleh AuthenticationServiceProvider::registerRoutes()
| dan tidak perlu diubah saat menambah atau mematikan sebuah feature.
|
*/

Route::group([
    'prefix' => config(
        'authentication.routes.api.prefix',
        'api/v1/auth'
    ),
    'middleware' => config(
        'authentication.routes.api.middleware',
        ['api']
    ),
], function () {
    /*
    |--------------------------------------------------------------------------
    | Core: Login & Logout
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/api/auth.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Registration
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/api/registration.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Password Reset
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/api/password.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: OTP / Passwordless Authentication
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/api/otp.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Social / OAuth Login
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/api/social.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Passkey / WebAuthn
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/api/passkey.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Two-Factor Authentication
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/api/two_factor.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Confirm Password
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/api/confirm_password.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Session Management
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/api/sessions.php';
});
