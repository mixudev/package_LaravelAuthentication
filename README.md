# Enterprise Authentication Package for Laravel

[![CI Tests](https://github.com/mixudev/package_LaravelAuthentication/actions/workflows/ci.yml/badge.svg)](https://github.com/mixudev/package_LaravelAuthentication/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1%20%7C%20%5E8.2%20%7C%20%5E8.3%20%7C%20%5E8.4%20%7C%20%5E8.5-8892BF.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x%20%7C%2012.x%20%7C%2013.x-FF2D20.svg)](https://laravel.com)

A production-grade, modular, portable, secure-by-default, and extensible authentication architecture for Laravel 10.x, 11.x, 12.x, and 13.x applications.

Designed to be integrated effortlessly into monolithic applications, REST APIs, SPAs, and multi-tenant platforms without duplicating or modifying core code.

---

## 📑 Documentation Hub

In-depth technical guides are available in the [`docs/`](docs/index.md) directory:

- 🚀 [1. Getting Started Guide](docs/getting-started.md)
- 📁 [2. Unified Single-Folder Module Mode](docs/modular-installation.md)
- ⚙️ [3. Features & Modular Switches (MFA, Sessions, Rate Limits, CAPTCHA)](docs/features.md)
- 🎨 [4. Views & Custom UI Guide](docs/views-customization.md) / [🇮🇩 Panduan Kustomisasi View](docs/panduan-kustomisasi-view.md)
- 🧩 [5. Strategies & Extending the Package](docs/strategies-and-extending.md)
- 🔌 [6. REST API Reference](docs/api-reference.md)
- 🛡️ [7. Security & Threat Mitigations](docs/security-and-best-practices.md)
- 🚢 [8. Publishing & Releases Guide](docs/publishing-guide.md)

---

## ✨ Core Highlights & Enterprise Features

- **Multi-Factor Authentication (MFA / 2FA)**: Pure PHP RFC 6238 TOTP engine (compatible with Google Authenticator, Authy, Microsoft Authenticator, 1Password), single-use encrypted recovery backup codes, and "Trust This Device" (30-day cookie bypass).
- **Active Session & Device Management**: Track active devices with OS, browser, IP, and location hints (`DeviceDetector`). Remote session revocation and *Logout All Other Devices* with password confirmation.
- **Granular Rate Limiting**: Dedicated, isolated throttle counters for `login`, `registration`, `otp_request`, `otp_verify`, `forgot_password`, `two_factor`, and `confirm_password` preventing cross-feature denial-of-service.
- **Suspicious & New Device Login Alerts**: Automated device fingerprinting with immediate security email notification on unfamiliar device/location logins.
- **Adaptive CAPTCHA & Bot Protection**: Support for Cloudflare Turnstile, Google reCAPTCHA v2/v3, and hCaptcha with adaptive threshold trigger (only appears after $N$ failed attempts to protect normal UX).
- **Re-Authentication for Sensitive Actions**: Built-in `password.confirm` middleware, controller, and views for protecting critical admin/security operations.
- **Asynchronous Mail Queues**: Non-blocking email dispatch support (`mail.queue = true`) across OTPs, new device alerts, and password resets.
- **Configurable Database Tables**: Change table names dynamically via `database.table_names` without forking or breaking relationships.
- **Passwordless OTP Login**: Single-use, cryptographically secure OTP codes with configurable expiry and rate limiting.
- **OAuth Social Login**: Google & GitHub OAuth via Laravel Socialite with automated user provisioning.
- **Dual UI Support**:
  - Out-of-the-box multi-template engine: `split` (2-column enterprise console) & `card` (centered minimalist card).
  - **Bring-Your-Own-UI**: Point the package to your own custom Blade templates in seconds.
- **Unified Single-Folder Module Exporter**: Package all config, migrations, views, and routes into a self-contained `modules/Authentication/` folder via `php artisan authentication:install-module`.
- **Zero-Trust Security**: Zero User Enumeration, composite rate limiting (`sha1(ip + identifier)`), account lockout, session fixation mitigation, and `#[\SensitiveParameter]` protection.
- **Complete REST API**: Every authentication flow is 100% supported via stateless JSON endpoints (`/api/v1/auth/*`) with Laravel Sanctum token issuance.

---

## 📦 Installation

### Option 1: Standard Composer Require
```bash
composer require mixudev/laravel-authentication
```

### Option 2: Local Path Repository (Monorepos / Local Development)
Add to your application's `composer.json`:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/LaravelAuthentication",
            "options": {
                "symlink": true
            }
        }
    ]
}
```
Then run:
```bash
composer require mixudev/laravel-authentication:@dev
```

---

## 🛠️ Setup & Publishing Modes

### Mode A: Single-Folder Module Mode *(Recommended)*
Export the entire authentication subsystem into a clean, unified `modules/Authentication/` folder:
```bash
php artisan authentication:install-module
```

Register the module Service Provider in `bootstrap/providers.php` (Laravel 11-13) or `config/app.php`:
```php
\Modules\Authentication\AuthenticationModuleServiceProvider::class,
```
Then run migrations:
```bash
php artisan migrate
```

---

### Mode B: Standard Publishing (Individual Folders)
```bash
# 1. Publish Configuration
php artisan vendor:publish --tag=authentication-config

# 2. Publish & Run Migrations
php artisan vendor:publish --tag=authentication-migrations
php artisan migrate

# 3. Publish Blade Views (Optional, for UI customization)
php artisan vendor:publish --tag=authentication-views

# 4. Publish Translations (Optional)
php artisan vendor:publish --tag=authentication-lang
```

---

## ⚙️ Quick Configuration Overview (`config/authentication.php`)

```php
return [
    'enabled' => true,
    'guard'   => 'web',
    'user_model' => \App\Models\User::class,

    // Dynamic database table names
    'database' => [
        'load_migrations' => true,
        'table_names' => [
            'attempts'           => 'authentication_attempts',
            'login_histories'    => 'authentication_login_histories',
            'password_histories' => 'authentication_password_histories',
            'two_factor'         => 'authentication_two_factors',
            'devices'            => 'authentication_devices',
            'sessions'           => 'authentication_sessions',
        ],
    ],

    // Non-blocking mail queue
    'mail' => [
        'queue'            => false, // Set true for queue worker dispatching
        'queue_connection' => null,
        'queue_name'       => 'auth-emails',
    ],

    // Granular rate limits
    'security' => [
        'rate_limits' => [
            'login'           => ['enabled' => true, 'max_attempts' => 5, 'decay_minutes' => 1, 'strategy' => 'composite'],
            'registration'    => ['enabled' => true, 'max_attempts' => 5, 'decay_minutes' => 60, 'strategy' => 'ip'],
            'otp_request'     => ['enabled' => true, 'max_attempts' => 3, 'decay_minutes' => 5, 'strategy' => 'composite'],
            'otp_verify'      => ['enabled' => true, 'max_attempts' => 5, 'decay_minutes' => 10, 'strategy' => 'composite'],
            'forgot_password' => ['enabled' => true, 'max_attempts' => 3, 'decay_minutes' => 60, 'strategy' => 'composite'],
            'two_factor'      => ['enabled' => true, 'max_attempts' => 5, 'decay_minutes' => 5, 'strategy' => 'ip'],
            'confirm_password'=> ['enabled' => true, 'max_attempts' => 5, 'decay_minutes' => 1, 'strategy' => 'ip'],
        ],
        'captcha' => [
            'enabled'                       => false,
            'driver'                        => 'turnstile', // 'turnstile', 'recaptcha_v2', 'recaptcha_v3', 'hcaptcha'
            'trigger_after_failed_attempts' => 3,           // Adaptive: only show after 3 failed attempts
            'site_key'                      => env('AUTH_CAPTCHA_SITE_KEY', ''),
            'secret_key'                    => env('AUTH_CAPTCHA_SECRET_KEY', ''),
        ],
        'new_device_notification' => [
            'enabled'          => true,
            'include_location' => true,
        ],
    ],

    // Modular features
    'features' => [
        'registration'       => ['enabled' => true, 'auto_login_on_register' => true],
        'forgot_password'    => ['enabled' => true],
        'otp'                => ['enabled' => true, 'length' => 6, 'expiry_minutes' => 10],
        'two_factor'         => [
            'enabled'        => true,
            'trust_device'   => ['enabled' => true, 'duration_days' => 30],
        ],
        'confirm_password'   => ['enabled' => true, 'timeout_seconds' => 900],
        'session_management' => ['enabled' => true, 'max_active_sessions' => 5],
        'social'             => ['enabled' => true],
    ],

    // UI Configuration
    'ui' => [
        'layout'   => 'card', // 'card' or 'split'
        'theme'    => 'light',
        'use_vite' => true,
    ],
];
```

---

## 🔒 Security Best Practices

- **Zero Hardcoded Coupling**: Never import `App\Models\User` into vendor logic. Everything resolves via `config('authentication.user_model')`.
- **Sensitive Parameter Redaction**: All raw passwords, TOTP secrets, and API tokens use `#[\SensitiveParameter]` attributes to prevent exposure in logs and stack traces.
- **Fail-Closed by Design**: Unrecognized channels, invalid configuration, or unhandled strategies throw explicit typed exceptions rather than defaulting insecurely.
- **Timing Safe**: All hash, token, and OTP comparisons use `hash_equals` to mitigate timing attacks.

---

## 🧪 Testing

Run the test suite via PHPUnit:
```bash
vendor/bin/phpunit
```

Analyze static types with PHPStan (Level 8):
```bash
vendor/bin/phpstan analyse
```

---

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).
