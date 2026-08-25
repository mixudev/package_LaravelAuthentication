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
- ⚙️ [3. Features & Modular Switches](docs/features.md)
- 🎨 [4. Views & Custom UI Guide](docs/views-customization.md)
- 🧩 [5. Strategies & Extending the Package](docs/strategies-and-extending.md)
- 🔌 [6. REST API Reference](docs/api-reference.md)
- 🛡️ [7. Security & Threat Mitigations](docs/security-and-best-practices.md)

---

## ✨ Core Highlights

- **Modular Subsystem Switches**: Toggle User Registration, Password Recovery, Passwordless OTP, and Socialite OAuth via simple boolean config flags.
- **Decoupled Architecture**: Zero hardcoded application coupling (`App\Models\User`). Easily maps to any custom Authenticatable model.
- **Dual UI Support**:
  - Ready-to-use **Sentra Console** dark theme with real-time client validation, animated spinners, and live password checklist.
  - **Bring-Your-Own-UI**: Point the package to your own custom Blade templates (`AUTH_VIEW_LOGIN=auth.login`) in seconds.
- **Unified Single-Folder Module Exporter**: Package all config, migrations, views, and routes into a self-contained `modules/Authentication/` folder via `php artisan authentication:install-module`.
- **Passwordless OTP Login**: Single-use, cryptographically secure OTP codes with configurable expiry, rate-limiting, and timing-safe verification.
- **OAuth Social Login**: Google & GitHub OAuth via Laravel Socialite with automated user provisioning.
- **Dynamic Password Strength Policies**: Granular `.env` configuration for minimum length, uppercase, lowercase, numbers, and custom special symbol sets.
- **Zero-Trust Security**:
  - Zero User Enumeration (constant-time responses and identical messaging across valid and non-existent accounts).
  - Composite rate limiting (`sha1(ip + identifier)`).
  - Account lockout with exponential decay.
  - Session fixation mitigation with automated `session()->regenerate()`.
  - Sensitive parameter redaction (`#[\SensitiveParameter]`).
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

### Mode A: Standard Publishing (Individual Folders)
```bash
# 1. Publish Configuration
php artisan vendor:publish --tag=authentication-config

# 2. Publish & Run Migrations
php artisan vendor:publish --tag=authentication-migrations
php artisan migrate

# 3. Publish UI Views (Optional - for customizing Blade templates)
php artisan vendor:publish --tag=authentication-views
```

---

### Mode B: Unified Single-Folder Module Mode (Clean Organization)
Export all package assets (Config, Migrations, Views, Routes, and ServiceProvider) into a single folder:

```bash
# Export into modules/Authentication/
php artisan authentication:install-module

# Or specify a custom directory:
php artisan authentication:install-module --path=app/Modules/Authentication
```

See the [Unified Module Guide](docs/modular-installation.md) for full 3-step setup instructions.

---

## ⚙️ Modular Feature Switches

In `config/authentication.php` (or `.env`), toggle entire subsystems on or off. When disabled, associated routes, controller actions, and UI buttons fail-closed automatically:

```php
'features' => [
    'registration' => [
        'enabled'                => env('AUTH_REGISTRATION_ENABLED', true),
        'auto_login_on_register' => env('AUTH_AUTO_LOGIN_ON_REGISTER', true),
    ],

    'forgot_password' => [
        'enabled' => env('AUTH_FORGOT_PASSWORD_ENABLED', true),
    ],

    'otp' => [
        'enabled'          => env('AUTH_OTP_ENABLED', true),
        'length'           => (int) env('AUTH_OTP_LENGTH', 6),
        'expiry_minutes'   => (int) env('AUTH_OTP_EXPIRY_MINUTES', 10),
        'max_attempts'     => (int) env('AUTH_OTP_MAX_ATTEMPTS', 3),
        'throttle_seconds' => (int) env('AUTH_OTP_THROTTLE_SECONDS', 60),
        'type'             => env('AUTH_OTP_TYPE', 'numeric'), // 'numeric' or 'alphanumeric'
    ],

    'social' => [
        'enabled'       => env('AUTH_SOCIAL_ENABLED', true),
        'auto_register' => env('AUTH_SOCIAL_AUTO_REGISTER', true),
        'providers'     => [
            'google' => ['enabled' => env('AUTH_GOOGLE_ENABLED', true)],
            'github' => ['enabled' => env('AUTH_GITHUB_ENABLED', true)],
        ],
    ],
],
```

---

## 🔒 Dynamic Password Policies (.env)

Customize your application's password complexity rules directly in your `.env` file without touching code:

```env
AUTH_PASSWORD_MIN_LENGTH=8
AUTH_PASSWORD_REQUIRE_UPPERCASE=true
AUTH_PASSWORD_REQUIRE_LOWERCASE=true
AUTH_PASSWORD_REQUIRE_NUMBERS=true
AUTH_PASSWORD_REQUIRE_SYMBOLS=true
AUTH_PASSWORD_SYMBOLS_CHARSET="@#$!%*"
```

The live frontend registration checklist and backend validation automatically synchronize with these rules!

---

## 🎨 Bring Your Own UI (Custom Views)

To replace the built-in dark theme with your own custom Blade templates (e.g. Tailwind, Bootstrap, Filament):

In `config/authentication.php` or `.env`:
```env
AUTH_VIEW_LOGIN=auth.login
AUTH_VIEW_REGISTER=auth.register
AUTH_VIEW_FORGOT_PASSWORD=auth.forgot-password
AUTH_VIEW_RESET_PASSWORD=auth.reset-password
AUTH_VIEW_OTP_REQUEST=auth.otp-request
AUTH_VIEW_OTP_VERIFY=auth.otp-verify
```

Check the [Custom Views Guide](docs/views-customization.md) for complete field specifications, route helpers, and copy-paste starter templates.

---

## 🔌 REST API Endpoints

All endpoints support pure JSON payloads under `/api/v1/auth`:

| Method | URI | Description |
| :--- | :--- | :--- |
| `POST` | `/api/v1/auth/login` | Authenticate and issue Sanctum token |
| `POST` | `/api/v1/auth/register` | Register new account and issue token |
| `POST` | `/api/v1/auth/otp/send` | Dispatch OTP verification code |
| `POST` | `/api/v1/auth/otp/verify` | Verify OTP code and authenticate |
| `POST` | `/api/v1/auth/forgot-password` | Request password reset email |
| `POST` | `/api/v1/auth/reset-password` | Update password using reset token |
| `POST` | `/api/v1/auth/social/{provider}` | Exchange OAuth credentials for session/token |
| `POST` | `/api/v1/auth/logout` | Revoke current token / destroy session |

See the [REST API Reference](docs/api-reference.md) for complete request and response schemas.

---

## 🛡️ Security Architecture

| Vector | Protection Mechanism |
| :--- | :--- |
| **User Enumeration** | Timing normalization (`usleep`) and uniform generic responses across valid & non-existent accounts. |
| **Brute Force & Credential Stuffing** | Composite rate limiting (`sha1(ip + identifier)`) with configurable lockout thresholds. |
| **Session Fixation** | Automated `session()->regenerate()` immediately following successful authentication. |
| **Credential Leakage** | All raw credentials use PHP 8.2+ `#[\SensitiveParameter]` attributes to prevent exposure in logs & stack traces. |
| **Password Rehashing** | Automatic rehashing to modern cryptographic algorithms upon successful login. |

---

## 🧪 Testing

Run package tests with PHPUnit:

```bash
vendor/bin/phpunit
```

---

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
