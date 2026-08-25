<?php

declare(strict_types=1);

/**
 * Laravel Authentication Package Configuration.
 *
 * This configuration serves as the central policy declaration for the package.
 * All settings are fail-closed: unrecognized or unsafe values will halt execution safely.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Master Package Enable Switch
    |--------------------------------------------------------------------------
    |
    | When disabled, the authentication routes, middleware guards, and services
    | will reject all incoming login attempts and throw a fail-closed exception.
    |
    */
    'enabled' => env('AUTH_PACKAGE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Guard & User Model
    |--------------------------------------------------------------------------
    |
    | The default authentication guard to authenticate against (e.g. 'web', 'api', 'sanctum').
    | The user model class can be overridden to any Eloquent model implementing
    | Illuminate\Contracts\Auth\Authenticatable.
    |
    */
    'guard' => env('AUTH_PACKAGE_GUARD', 'web'),

    'user_model' => env('AUTH_PACKAGE_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Login Strategy & Identifier Resolution
    |--------------------------------------------------------------------------
    |
    | Supported default methods:
    | - 'username_or_email' : Autodetects format and authenticates via username or email
    | - 'email_password'    : Strictly expects standard email format
    | - 'username_password' : Strictly matches against the username column
    | - 'custom_identifier' : Matches against custom configured column (e.g. employee_id)
    |
    */
    'login' => [
        'default_strategy' => env('AUTH_DEFAULT_STRATEGY', 'username_or_email'),

        'strategies' => [
            'username_or_email' => \Vendor\LaravelAuthentication\Strategies\UsernameOrEmailStrategy::class,
            'email_password'    => \Vendor\LaravelAuthentication\Strategies\EmailPasswordStrategy::class,
            'username_password' => \Vendor\LaravelAuthentication\Strategies\UsernamePasswordStrategy::class,
            'custom_identifier' => \Vendor\LaravelAuthentication\Strategies\CustomIdentifierStrategy::class,
        ],

        // Column mapping for database lookups
        'identifiers' => [
            'username_column' => 'username',
            'email_column'    => 'email',
            'custom_column'   => 'employee_id',
            'password_column' => 'password',
        ],

        // Normalize identifier casing and whitespace
        'normalize_identifiers' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Security Policies
    |--------------------------------------------------------------------------
    |
    | Configure password strength, automatic rehashing with modern algorithms
    | (Argon2id/Bcrypt), and historical password reuse prevention.
    |
    */
    'password' => [
        'rehash' => true,

        'validation_rules' => [
            /*
            |----------------------------------------------------------
            | Password Strength Policy
            |----------------------------------------------------------
            | Each rule can be toggled via environment variables so
            | you can adjust the policy per environment (staging vs production).
            |
            | AUTH_PASSWORD_MIN_LENGTH        — Minimum character count (default: 8)
            | AUTH_PASSWORD_REQUIRE_UPPERCASE — Must contain at least 1 uppercase letter
            | AUTH_PASSWORD_REQUIRE_LOWERCASE — Must contain at least 1 lowercase letter
            | AUTH_PASSWORD_REQUIRE_NUMBERS   — Must contain at least 1 digit
            | AUTH_PASSWORD_REQUIRE_SYMBOLS   — Must contain at least 1 special char
            | AUTH_PASSWORD_SYMBOLS_CHARSET   — Custom allowed symbol set (e.g. "@#$!%*")
            | AUTH_PASSWORD_UNCOMPROMISED     — Check against HaveIBeenPwned (requires internet)
            */
            'min_length'         => (int) env('AUTH_PASSWORD_MIN_LENGTH', 8),
            'require_uppercase'  => (bool) env('AUTH_PASSWORD_REQUIRE_UPPERCASE', true),
            'require_lowercase'  => (bool) env('AUTH_PASSWORD_REQUIRE_LOWERCASE', true),
            'require_mixed_case' => (bool) env('AUTH_PASSWORD_REQUIRE_UPPERCASE', true) && (bool) env('AUTH_PASSWORD_REQUIRE_LOWERCASE', true),
            'require_numbers'    => (bool) env('AUTH_PASSWORD_REQUIRE_NUMBERS', true),
            'require_symbols'    => (bool) env('AUTH_PASSWORD_REQUIRE_SYMBOLS', true),
            'symbols_charset'    => env('AUTH_PASSWORD_SYMBOLS_CHARSET', '@$!%*#?&_-+=[]{}|;:,.<>'),
            'uncompromised'      => (bool) env('AUTH_PASSWORD_UNCOMPROMISED', false),
        ],

        'history' => [
            'enabled'  => env('AUTH_PASSWORD_HISTORY_ENABLED', false),
            'remember' => 5, // Prevent reusing the last N passwords
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & Abuse Protections
    |--------------------------------------------------------------------------
    |
    | Rate limiting, account lockouts, user enumeration mitigations,
    | and strict session invalidation controls.
    |
    */
    'security' => [
        // Protect against account discovery by always returning identical error messages and timings
        'user_enumeration_protection' => true,

        'rate_limit' => [
            'enabled'       => true,
            'max_attempts'  => (int) env('AUTH_RATE_LIMIT_MAX', 5),
            'decay_minutes' => (int) env('AUTH_RATE_LIMIT_DECAY', 1),
            'strategy'      => 'composite', // 'ip', 'identifier', 'composite' (ip + identifier)
        ],

        'account_lockout' => [
            'enabled'                => env('AUTH_LOCKOUT_ENABLED', false),
            'max_failed_attempts'    => 5,
            'lockout_duration_mins'  => 15,
            'auto_unlock'            => true,
        ],

        'session' => [
            'regenerate_on_login'  => true,
            'invalidate_on_logout' => true,
            'session_fixation_protection' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modular Authentication Feature Switches
    |--------------------------------------------------------------------------
    |
    | Enable or disable high-level authentication features with a single flag.
    | Unused routes and controllers are fail-closed when a feature is disabled.
    |
    */
    'features' => [
        // User registration (Web & API)
        'registration' => [
            'enabled'               => env('AUTH_REGISTRATION_ENABLED', true),
            'auto_login_on_register'=> env('AUTH_AUTO_LOGIN_ON_REGISTER', true),
            'require_email_verify'  => env('AUTH_REQUIRE_EMAIL_VERIFY', false),
        ],

        // Self-service password reset & recovery (Web & API)
        'forgot_password' => [
            'enabled' => env('AUTH_FORGOT_PASSWORD_ENABLED', true),
        ],

        // One-Time Password (OTP) / Passwordless Login (Web & API)
        'otp' => [
            'enabled'          => env('AUTH_OTP_ENABLED', true),
            'length'           => (int) env('AUTH_OTP_LENGTH', 6),
            'expiry_minutes'   => (int) env('AUTH_OTP_EXPIRY_MINUTES', 10),
            'max_attempts'     => (int) env('AUTH_OTP_MAX_ATTEMPTS', 3),
            'throttle_seconds' => (int) env('AUTH_OTP_THROTTLE_SECONDS', 60),
            'type'             => env('AUTH_OTP_TYPE', 'numeric'), // 'numeric' or 'alphanumeric'

            // Automated Email Dispatching
            'send_email'       => env('AUTH_OTP_SEND_EMAIL', true),
            'email_subject'    => env('AUTH_OTP_EMAIL_SUBJECT', null), // null = default: '{App Name} — Kode Verifikasi Masuk (OTP)'
            'email_view'       => env('AUTH_OTP_EMAIL_VIEW', 'authentication::emails.otp'),
        ],

        // Social / OAuth Login via Laravel Socialite (Web & API)
        'social' => [
            'enabled'       => env('AUTH_SOCIAL_ENABLED', true),
            'auto_register' => env('AUTH_SOCIAL_AUTO_REGISTER', true), // Auto create user if doesn't exist
            'providers'     => [
                'google' => [
                    'enabled'       => env('AUTH_GOOGLE_ENABLED', true),
                    'client_id'     => env('GOOGLE_CLIENT_ID'),
                    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                    'redirect'      => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/google/callback'),
                    'scopes'        => ['openid', 'profile', 'email'],
                ],
                'github' => [
                    'enabled'       => env('AUTH_GITHUB_ENABLED', true),
                    'client_id'     => env('GITHUB_CLIENT_ID'),
                    'client_secret' => env('GITHUB_CLIENT_SECRET'),
                    'redirect'      => env('GITHUB_REDIRECT_URI', env('APP_URL') . '/auth/github/callback'),
                    'scopes'        => ['user:email', 'read:user'],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect Routes / Paths
    |--------------------------------------------------------------------------
    |
    | Default redirection destinations after successful authentication events.
    |
    */
    'redirects' => [
        'login'          => env('AUTH_REDIRECT_LOGIN', '/dashboard'),
        'register'       => env('AUTH_REDIRECT_REGISTER', '/dashboard'),
        'logout'         => env('AUTH_REDIRECT_LOGOUT', '/login'),
        'password_reset' => env('AUTH_REDIRECT_PASSWORD_RESET', '/login'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging System
    |--------------------------------------------------------------------------
    |
    | Configure storage of authentication events (success, failure, lockout).
    | Sensitive payload fields (passwords, tokens) are redacted automatically.
    |
    */
    'audit' => [
        'enabled'       => env('AUTH_AUDIT_ENABLED', true),
        'driver'        => 'database', // 'database', 'log', 'null'
        'log_channel'   => 'stack',
        'retention_days'=> 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route & HTTP Integration
    |--------------------------------------------------------------------------
    |
    | Enable or disable built-in package routes for Web sessions or API auth.
    |
    */
    'routes' => [
        'web' => [
            'enabled'    => env('AUTH_WEB_ROUTES_ENABLED', true),
            'prefix'     => '',
            'middleware' => ['web'],
        ],
        'api' => [
            'enabled'    => env('AUTH_API_ROUTES_ENABLED', true),
            'prefix'     => 'api/v1/auth',
            'middleware' => ['api'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Views Configuration (Custom / Bring-Your-Own-UI)
    |--------------------------------------------------------------------------
    |
    | Define custom Blade views if you prefer using your own UI templates
    | instead of the package's built-in Sentra Console dark theme.
    |
    | You can point these to any Blade view in your application, e.g.:
    | 'login' => 'auth.login' (points to resources/views/auth/login.blade.php)
    |
    */
    'views' => [
        'login'           => env('AUTH_VIEW_LOGIN', 'authentication::login'),
        'register'        => env('AUTH_VIEW_REGISTER', 'authentication::register'),
        'forgot_password' => env('AUTH_VIEW_FORGOT_PASSWORD', 'authentication::forgot-password'),
        'reset_password'  => env('AUTH_VIEW_RESET_PASSWORD', 'authentication::reset-password'),
        'otp_request'     => env('AUTH_VIEW_OTP_REQUEST', 'authentication::otp-request'),
        'otp_verify'      => env('AUTH_VIEW_OTP_VERIFY', 'authentication::otp-verify'),
        'otp_email'       => env('AUTH_VIEW_OTP_EMAIL', 'authentication::emails.otp'),
    ],

];
