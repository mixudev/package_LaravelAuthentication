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
            'min_length'      => 8,
            'require_mixed_case' => true,
            'require_numbers'    => true,
            'require_symbols'    => true,
            'uncompromised'      => false,
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
        ],

        // Social / OAuth Login via Laravel Socialite (Web & API)
        'social' => [
            'enabled'       => env('AUTH_SOCIAL_ENABLED', true),
            'auto_register' => env('AUTH_SOCIAL_AUTO_REGISTER', true), // Auto create user if doesn't exist
            'providers'     => [
                'google' => [
                    'enabled' => env('AUTH_GOOGLE_ENABLED', true),
                    'scopes'  => ['openid', 'profile', 'email'],
                ],
                'github' => [
                    'enabled' => env('AUTH_GITHUB_ENABLED', true),
                    'scopes'  => ['user:email', 'read:user'],
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

];
