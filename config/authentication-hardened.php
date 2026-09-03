<?php

declare(strict_types=1);

/**
 * Konfigurasi Hardened Preset — untuk production / aplikasi berisiko tinggi.
 *
 * Cara pakai: salin/merge nilai di bawah ke config/authentication.php host app,
 * ATAU publish preset ini dan muat sebagai override:
 *
 *   // config/authentication.php
 *   return array_replace_recursive(
 *       include __DIR__ . '/vendor/mixudev/laravel-authentication/config/authentication.php',
 *       include __DIR__ . '/authentication-hardened.php', // nilai override di bawah
 *   );
 *
 * Nilai ini MEMPERKETAT default longgar yang ada di authentication.php:
 * password strength, account lockout, captcha, verifikasi email, dst.
 */
return [

    'password' => [
        'validation_rules' => [
            'min_length'         => 12,
            'require_uppercase'  => true,
            'require_lowercase'  => true,
            'require_mixed_case' => true,
            'require_numbers'    => true,
            'require_symbols'    => true,
        ],
        'history' => [
            'enabled'  => true,
            'remember' => 5,
        ],
    ],

    'security' => [
        'user_enumeration_protection' => true,

        'rate_limits' => [
            'login' => [
                'enabled'       => true,
                'max_attempts'  => 5,
                'decay_minutes' => 1,
                'strategy'      => 'composite',
            ],
            'registration' => [
                'enabled'       => true,
                'max_attempts'  => 3,
                'decay_minutes' => 60,
                'strategy'      => 'ip',
            ],
            'otp_request' => [
                'enabled'       => true,
                'max_attempts'  => 3,
                'decay_minutes' => 5,
                'strategy'      => 'composite',
            ],
            'otp_verify' => [
                'enabled'       => true,
                'max_attempts'  => 3,
                'decay_minutes' => 10,
                'strategy'      => 'composite',
            ],
            'forgot_password' => [
                'enabled'       => true,
                'max_attempts'  => 3,
                'decay_minutes' => 60,
                'strategy'      => 'composite',
            ],
            'two_factor' => [
                'enabled'       => true,
                'max_attempts'  => 3,
                'decay_minutes' => 5,
                'strategy'      => 'ip',
            ],
            'confirm_password' => [
                'enabled'       => true,
                'max_attempts'  => 5,
                'decay_minutes' => 1,
                'strategy'      => 'ip',
            ],
        ],

        'captcha' => [
            // WAJIB diisi site/secret key (env AUTH_CAPTCHA_SITE_KEY / AUTH_CAPTCHA_SECRET_KEY)
            'enabled'                       => true,
            'driver'                        => 'turnstile',
            'trigger_after_failed_attempts' => 3,
        ],

        'account_lockout' => [
            'enabled'               => true,
            'max_failed_attempts'   => 5,
            'lockout_duration_mins' => 15,
            'auto_unlock'           => true,
        ],

        'session' => [
            'regenerate_on_login'         => true,
            'invalidate_on_logout'        => true,
            'session_fixation_protection' => true,
        ],
    ],

    'features' => [
        'registration' => [
            'enabled'                => true,
            'auto_login_on_register' => false,
            'require_email_verify'   => true,
        ],

        'otp' => [
            'enabled'          => true,
            'length'           => 6,
            'expiry_minutes'   => 5,
            'max_attempts'     => 3,
            'throttle_seconds' => 60,
            'type'             => 'numeric',
        ],

        'social' => [
            'enabled'       => true,
            'auto_register' => false, // WAJIB verifikasi email manual; no silent account creation
        ],

        'confirm_password' => [
            'enabled'         => true,
            'timeout_seconds' => 300, // 5 menit — re-auth lebih sering untuk aksi sensitif
        ],

        'session_management' => [
            'enabled'             => true,
            'max_active_sessions' => 3,
        ],
    ],

    'audit' => [
        'enabled'        => true,
        'driver'         => 'database',
        'retention_days' => 180,
    ],

];
