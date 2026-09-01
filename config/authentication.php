<?php

declare(strict_types=1);

/**
 * Konfigurasi Package Autentikasi Laravel.
 *
 * Semua opsi didefinisikan langsung di sini (tidak lewat env) supaya
 * konfigurasi mudah dibaca, di-review, dan konsisten di semua environment.
 * env() hanya dipakai untuk kredensial rahasia yang memang wajib berbeda
 * per environment (OAuth client id/secret, CAPTCHA keys).
 */

return [

    // Saklar utama package. Kalau false, semua route & guard auth ditolak.
    'enabled' => true,

    // Guard default Laravel yang dipakai (web, api, sanctum, dst).
    'guard' => 'web',

    // Model user yang dipakai untuk autentikasi.
    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Database & Tabel Migrasi
    |--------------------------------------------------------------------------
    | Pengguna package dapat mengkustomisasi nama tabel tanpa perlu mem-fork
    | repository. load_migrations dapat diset false jika migrasi dikelola manual.
    */
    'database' => [
        'load_migrations' => true,

        'table_names' => [
            'attempts'           => 'authentication_attempts',
            'login_histories'    => 'authentication_login_histories',
            'password_histories' => 'authentication_password_histories',
            'two_factor'         => 'authentication_two_factors',
            'devices'            => 'authentication_devices',
            'sessions'           => 'authentication_sessions',
            'passkeys'           => 'authentication_passkeys',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengiriman Email Asinkron (Queue)
    |--------------------------------------------------------------------------
    | Mencegah proses login/OTP blocking ketika koneksi SMTP mail server lambat.
    */
    'mail' => [
        'queue'            => false, // Set true untuk dispatch email lewat background worker queue
        'queue_connection' => null,  // null = ikuti default queue connection
        'queue_name'       => 'auth-emails',
    ],

    /*
    |--------------------------------------------------------------------------
    | Strategi Login
    |--------------------------------------------------------------------------
    | Pilihan strategi yang tersedia:
    | - username_or_email : deteksi otomatis, login pakai username ATAU email
    | - email_password    : wajib format email
    | - username_password : hanya cocokkan kolom username
    | - custom_identifier : cocokkan kolom custom (mis. employee_id)
    */
    'login' => [
        // Strategi yang aktif secara default
        'default_strategy' => 'username_or_email',

        'strategies' => [
            'username_or_email' => \Vendor\LaravelAuthentication\Strategies\UsernameOrEmailStrategy::class,
            'email_password'    => \Vendor\LaravelAuthentication\Strategies\EmailPasswordStrategy::class,
            'username_password' => \Vendor\LaravelAuthentication\Strategies\UsernamePasswordStrategy::class,
            'custom_identifier' => \Vendor\LaravelAuthentication\Strategies\CustomIdentifierStrategy::class,
            'passkey'           => \Vendor\LaravelAuthentication\Strategies\PasskeyAuthenticationStrategy::class,
        ],

        // Mapping nama kolom di database
        'identifiers' => [
            'username_column' => 'username',
            'email_column'    => 'email',
            'custom_column'   => 'employee_id',
            'password_column' => 'password',
        ],

        // Normalisasi huruf besar/kecil & spasi pada identifier saat login
        'normalize_identifiers' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kebijakan Password
    |--------------------------------------------------------------------------
    | Aturan kekuatan password, rehash otomatis, dan pencegahan pemakaian
    | ulang password lama.
    */
    'password' => [
        // Rehash otomatis ke algoritma terbaru (Argon2id/Bcrypt) saat login
        'rehash' => true,

        'validation_rules' => [
            'min_length'         => 8,     // Minimal jumlah karakter
            'require_uppercase'  => true,  // Wajib ada huruf besar
            'require_lowercase'  => true,  // Wajib ada huruf kecil
            'require_mixed_case' => true,  // Wajib kombinasi besar & kecil
            'require_numbers'    => true,  // Wajib ada angka
            'require_symbols'    => true,  // Wajib ada karakter spesial
            'symbols_charset'    => '@$!%*#?&_-+=[]{}|;:,.<>', // Karakter spesial yang diizinkan
            'uncompromised'      => false, // Cek ke HaveIBeenPwned (butuh koneksi internet)
        ],

        'history' => [
            'enabled'  => false, // Aktifkan pengecekan riwayat password
            'remember' => 5,     // Jumlah password lama yang tidak boleh dipakai ulang
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Keamanan & Proteksi Abuse
    |--------------------------------------------------------------------------
    | Rate limiting granular per fitur, lockout akun, proteksi enumerasi,
    | CAPTCHA adaptif, dan notifikasi device baru.
    */
    'security' => [
        // Selalu balas pesan & waktu error yang sama agar user/email tidak bisa ditebak
        'user_enumeration_protection' => true,

        // Rate limiting terpisah dan granular per fitur auth
        'rate_limits' => [
            'login' => [
                'enabled'       => true,
                'max_attempts'  => 5,
                'decay_minutes' => 1,
                'strategy'      => 'composite', // 'ip', 'identifier', atau 'composite'
            ],
            'registration' => [
                'enabled'       => true,
                'max_attempts'  => 5,
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
                'max_attempts'  => 5,
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
                'max_attempts'  => 5,
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

        // CAPTCHA / Bot Protection Adaptif
        'captcha' => [
            'enabled'                       => false,
            'driver'                        => 'turnstile', // 'turnstile', 'recaptcha_v2', 'recaptcha_v3', 'hcaptcha'
            'trigger_after_failed_attempts' => 3,           // 0 = selalu minta, >0 = baru minta setelah N kali gagal
            'site_key'                      => env('AUTH_CAPTCHA_SITE_KEY', ''),
            'secret_key'                    => env('AUTH_CAPTCHA_SECRET_KEY', ''),
        ],

        // Notifikasi login dari perangkat/lokasi baru
        'new_device_notification' => [
            'enabled'          => true,
            'mail_subject'     => null, // null = default: '{AppName} — Deteksi Masuk dari Perangkat Baru'
            'include_location' => true,
        ],

        'account_lockout' => [
            'enabled'               => false, // Kunci akun setelah gagal berkali-kali
            'max_failed_attempts'   => 5,
            'lockout_duration_mins' => 15,
            'auto_unlock'           => true,  // Buka kunci otomatis setelah durasi habis
        ],

        'session' => [
            'regenerate_on_login'         => true, // Ganti session ID tiap login (cegah fixation)
            'invalidate_on_logout'        => true, // Hapus session saat logout
            'session_fixation_protection' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fitur Autentikasi
    |--------------------------------------------------------------------------
    | Nyala/matikan fitur besar dengan satu flag. Route & controller yang
    | tidak dipakai otomatis ditolak saat fiturnya nonaktif.
    */
    'features' => [

        // Registrasi user baru (Web & API)
        'registration' => [
            'enabled'                => true,
            'auto_login_on_register' => true,  // Langsung login setelah daftar
            'require_email_verify'   => false, // Wajib verifikasi email dulu
        ],

        // Lupa & reset password (Web & API)
        'forgot_password' => [
            'enabled' => true,
        ],

        // Login via OTP / tanpa password (Web & API)
        'otp' => [
            'enabled'          => true,
            'length'           => 6,        // Jumlah digit/karakter OTP
            'expiry_minutes'   => 10,       // Masa berlaku OTP
            'max_attempts'     => 3,        // Maksimal percobaan verifikasi
            'throttle_seconds' => 60,       // Jeda minimal sebelum kirim ulang OTP
            'type'             => 'numeric', // 'numeric' atau 'alphanumeric'

            // Pengiriman email OTP otomatis
            'send_email'    => true,
            'email_subject' => null, // null = pakai default: '{Nama App} — Kode Verifikasi Masuk (OTP)'
            'email_view'    => 'authentication::emails.otp',
        ],

        // Multi-Factor Authentication (MFA / 2FA TOTP RFC 6238 & Backup Codes)
        'two_factor' => [
            'enabled'             => true,
            'digits'              => 6,
            'period'              => 30,
            'window'              => 1, // Clock drift tolerance (+-1 step = +-30s)
            'backup_codes_count'  => 8,
            'issuer'              => env('APP_NAME', 'Laravel'),

            // Fitur Remember This Device untuk bypass 2FA pada perangkat terpercaya
            'trust_device' => [
                'enabled'       => true,
                'duration_days' => 30,
                'cookie_name'   => 'auth_trusted_device',
            ],
        ],

        // Konfirmasi Password untuk Aksi Sensitif (Re-authentication)
        'confirm_password' => [
            'enabled'         => true,
            'timeout_seconds' => 900, // 15 menit
        ],

        // Manajemen Sesi & Perangkat Aktif
        'session_management' => [
            'enabled'             => true,
            'max_active_sessions' => 5, // 0 = tidak dibatasi
        ],

        // Login sosial / OAuth via Laravel Socialite (Web & API)
        'social' => [
            'enabled'       => true,
            'auto_register' => true, // Buat user baru otomatis jika belum terdaftar

            'providers' => [
                'google' => [
                    'enabled' => true,
                    // Kredensial rahasia — WAJIB lewat env, jangan hardcode
                    'client_id'     => env('GOOGLE_CLIENT_ID'),
                    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                    'redirect'      => env('APP_URL') . '/auth/google/callback',
                    'scopes'        => ['openid', 'profile', 'email'],
                ],
                'github' => [
                    'enabled' => true,
                    // Kredensial rahasia — WAJIB lewat env, jangan hardcode
                    'client_id'     => env('GITHUB_CLIENT_ID'),
                    'client_secret' => env('GITHUB_CLIENT_SECRET'),
                    'redirect'      => env('APP_URL') . '/auth/github/callback',
                    'scopes'        => ['user:email', 'read:user'],
                ],
            ],
        ],

        // Passkey / WebAuthn FIDO2 Passwordless Authentication (Web & API)
        'passkey' => [
            'enabled'           => true,
            'rp_name'           => env('APP_NAME', 'Laravel'),
            'rp_id'             => null, // null = auto detect host
            'user_verification' => 'preferred', // 'required', 'preferred', 'discouraged'
            'timeout'           => 60000, // 60 detik
        ],
    ],

    // Tujuan redirect setelah aksi autentikasi berhasil
    'redirects' => [
        'login'          => '/dashboard',
        'register'       => '/dashboard',
        'logout'         => '/login',
        'password_reset' => '/login',
        'two_factor'     => '/dashboard',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    | Mencatat event autentikasi (sukses, gagal, lockout). Field sensitif
    | seperti password/token otomatis disamarkan.
    */
    'audit' => [
        'enabled'        => true,
        'driver'         => 'database', // 'database', 'log', atau 'null'
        'log_channel'    => 'stack',
        'retention_days' => 90,
    ],

    // Route bawaan package untuk Web (session) dan API (token)
    'routes' => [
        'web' => [
            'enabled'    => true,
            'prefix'     => '',
            'middleware' => ['web'],
        ],
        'api' => [
            'enabled'    => true,
            'prefix'     => 'api/v1/auth',
            'middleware' => ['api'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | View Kustom (Bring-Your-Own-UI)
    |--------------------------------------------------------------------------
    | Arahkan ke view Blade sendiri jika tidak mau pakai tampilan bawaan
    | package, mis. 'login' => 'auth.login'.
    */
    'views' => [
        'login'                => 'authentication::login',
        'register'             => 'authentication::register',
        'forgot_password'      => 'authentication::forgot-password',
        'reset_password'       => 'authentication::reset-password',
        'otp_request'          => 'authentication::otp-request',
        'otp_verify'           => 'authentication::otp-verify',
        'otp_email'            => 'authentication::emails.otp',
        'two_factor_challenge' => 'authentication::two-factor-challenge',
        'two_factor_setup'     => 'authentication::two-factor-setup',
        'confirm_password'     => 'authentication::confirm-password',
        'sessions'             => 'authentication::sessions',
        'new_device_email'     => 'authentication::emails.new-device',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tampilan & Layout
    |--------------------------------------------------------------------------
    | layout : 'card' (kartu tengah minimalis) atau 'split' (2-kolom)
    | theme  : 'light', 'dark', atau 'auto'
    | brand  : nama, tagline, dan logo yang tampil di halaman auth
    */
    'ui' => [
        'layout' => 'card',
        'theme'  => 'light',

        'brand_name'    => env('APP_NAME', 'Laravel'), // Ikut nama aplikasi host
        'brand_tagline' => 'Portal Autentikasi & Masuk Akun',
        'brand_badge'   => null,
        'logo_url'      => null,

        // Pakai aset Vite dari aplikasi host (false = pakai CDN fallback)
        'use_vite' => true,
    ],

];