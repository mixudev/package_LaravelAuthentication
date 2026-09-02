<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Authentication Package
|--------------------------------------------------------------------------
|
| Entry point untuk semua Web routes paket ini. Setiap feature dipisah
| ke file tersendiri di routes/web/ agar mudah di-maintain dan dioverride.
|
| File ini di-load oleh AuthenticationServiceProvider::registerRoutes()
| dan tidak perlu diubah saat menambah atau mematikan sebuah feature.
|
*/

Route::group([
    'middleware' => config('authentication.routes.web.middleware', ['web']),
], function () {
    /*
    |--------------------------------------------------------------------------
    | Core: Login & Logout
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/web/auth.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Registration
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/web/registration.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Password Reset
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/web/password.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: OTP / Passwordless Login
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/web/otp.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Social / OAuth Login
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/web/social.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Passkey / WebAuthn
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/web/passkey.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Two-Factor Authentication
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/web/two_factor.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Confirm Password / Re-authentication
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/web/confirm_password.php';

    /*
    |--------------------------------------------------------------------------
    | Feature: Session & Device Management
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/web/sessions.php';

    /*
    |--------------------------------------------------------------------------
    | Core: Email Verification
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/web/email_verification.php';
});
