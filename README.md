# Laravel Authentication Package

[![CI Tests](https://github.com/mixudev/package_LaravelAuthentication/actions/workflows/ci.yml/badge.svg)](https://github.com/mixudev/package_LaravelAuthentication/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1%20%7C%20%5E8.2%20%7C%20%5E8.3%20%7C%20%5E8.4%20%7C%20%5E8.5-8892BF.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x%20%7C%2012.x%20%7C%2013.x-FF2D20.svg)](https://laravel.com)

A production-grade, modular, portable, secure-by-default, and extensible authentication library for Laravel applications. Designed to be reused across dozens of diverse client applications via standard Composer workflows without duplicating or modifying core code.

---

## Table of Contents
1. [Key Features](#key-features)
2. [Compatibility Matrix](#compatibility-matrix)
3. [Architecture Overview](#architecture-overview)
4. [Installation](#installation)
5. [Publishing Assets & Views](#publishing-assets--views)
6. [Modular Feature Switches](#modular-feature-switches)
7. [User Registration (Web & API)](#user-registration-web--api)
8. [Passwordless OTP Authentication](#passwordless-otp-authentication)
9. [Social / OAuth Login (Google & GitHub)](#social--oauth-login-google--github)
10. [Password Recovery & Reset](#password-recovery--reset)
11. [Supported Login Strategies](#supported-login-strategies)
12. [Extending Custom Strategies (e.g. Employee ID)](#extending-custom-strategies-eg-employee-id)
13. [API Endpoints Reference](#api-endpoints-reference)
14. [Security & Threat Mitigations](#security--threat-mitigations)
15. [Testing](#testing)

---

## Key Features

- **Modular Feature Architecture**: Toggle User Registration, Password Recovery, Passwordless OTP, and Socialite OAuth via simple boolean config flags.
- **Decoupled Architecture**: Zero hard-coded coupling to `App\Models\User` or host application namespaces.
- **Ready-to-Use Dark Console UI**: Beautiful, accessible, responsive Blade views (`login`, `register`, `forgot-password`, `reset-password`, `otp-request`, `otp-verify`) matching high-grade developer console aesthetics.
- **Passwordless OTP Login**: Cryptographically secure, rate-limited, single-use OTP codes with configurable expiry and timing-safe verification.
- **OAuth Social Login**: Built-in support for Google, GitHub, and custom providers via Laravel Socialite with automated user provisioning.
- **Complete REST API**: Every authentication flow is 100% supported via clean JSON endpoints (`/api/v1/auth/...`).
- **Strategy Pattern Engine**: Easily switch between `username_or_email`, `email_password`, `username_password`, or add custom strategies (`employee_id`, `phone_number`, `sso`) at runtime.
- **Composite Rate Limiting & Account Lockout**: Anti-brute force and credential stuffing defense with configurable decay windows and IP/Identifier composite throttling.
- **Session Security**: Native session ID regeneration upon login (session fixation prevention) and complete cache/token invalidation on logout.
- **Password Hygiene**: Transparent password rehashing to modern algorithms (Argon2id/Bcrypt) and historical password reuse prevention.

---

## Compatibility Matrix

| Package Version | PHP Versions Supported | Laravel Target Versions | Status |
| :--- | :--- | :--- | :--- |
| **1.1.x** / **main** | `8.1`, `8.2`, `8.3`, `8.4`, `8.5` | `10.x`, `11.x`, `12.x`, `13.x` | **Active / Current** |
| **2.0.x** *(Planned)* | `8.3`, `8.4`, `8.5` | `12.x`, `13.x`, `14.x` | *Future Roadmap* |

---

## Architecture Overview

```
HTTP Request (Web / JSON API)
        ↓
Feature Guard (Enabled / Disabled Check)
        ↓
Rate Limiter (LoginAttemptManager - Composite Key)
        ↓
Authentication / Registration / OTP / Social Orchestrator
        ↓
Strategy Selection & Identity Resolver
        ↓
Credential Verification & Security Policies
        ↓
Session Login (Web) OR Sanctum Bearer Token (API)
        ↓
Event Dispatcher (LoginSucceeded / UserRegistered / OtpVerified)
        ↓
Security Audit Log (Redacted PII)
        ↓
Response (Redirect / JSON DTO)
```

---

## Installation

### A. Standard Composer Require
```bash
composer require mixudev/laravel-authentication
```

### B. Local Composer Path Repository (Monorepos / Local Dev)
In host application's `composer.json`:
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

## Publishing Assets & Views

After installation, publish the configuration, database migrations, and optional UI views:

```bash
# 1. Publish Configuration
php artisan vendor:publish --tag=authentication-config

# 2. Publish Database Migrations & Run
php artisan vendor:publish --tag=authentication-migrations
php artisan migrate

# 3. Publish UI Views (Optional - to customize Blade templates)
php artisan vendor:publish --tag=authentication-views
```

---

## Modular Feature Switches

In your published `config/authentication.php`, enable or disable entire subsystems with simple boolean switches. When a feature is disabled, its routes, controller actions, and API endpoints immediately fail-closed:

```php
'features' => [
    // 1. User Registration (Web & API)
    'registration' => [
        'enabled'                => env('AUTH_REGISTRATION_ENABLED', true),
        'auto_login_on_register' => env('AUTH_AUTO_LOGIN_ON_REGISTER', true),
        'require_email_verify'   => env('AUTH_REQUIRE_EMAIL_VERIFY', false),
    ],

    // 2. Self-Service Password Reset (Web & API)
    'forgot_password' => [
        'enabled' => env('AUTH_FORGOT_PASSWORD_ENABLED', true),
    ],

    // 3. Passwordless OTP Login (Web & API)
    'otp' => [
        'enabled'          => env('AUTH_OTP_ENABLED', true),
        'length'           => 6,
        'expiry_minutes'   => 10,
        'max_attempts'     => 3,
        'throttle_seconds' => 60,
        'type'             => 'numeric', // 'numeric' or 'alphanumeric'
    ],

    // 4. Socialite OAuth Login (Web & API)
    'social' => [
        'enabled'       => env('AUTH_SOCIAL_ENABLED', true),
        'auto_register' => true,
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
```

---

## User Registration (Web & API)

Registration dynamically creates users using your configured Eloquent model (`config('authentication.user_model')`) without hardcoded database assumptions.

- **Web Route**: `GET /register`, `POST /register`
- **API Route**: `POST /api/v1/auth/register`
- **Payload**:
  ```json
  {
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!"
  }
  ```
- **Events**: Dispatches `Vendor\LaravelAuthentication\Events\UserRegistered`.

---

## Passwordless OTP Authentication

Provides passwordless authentication using single-use verification codes.

- **Web Routes**:
  - Request Code: `GET /otp/login`, `POST /otp/send`
  - Verify Code: `GET /otp/verify?identifier=...`, `POST /otp/verify`
- **API Endpoints**:
  - `POST /api/v1/auth/otp/send` (`{"identifier": "jane@example.com"}`)
  - `POST /api/v1/auth/otp/verify` (`{"identifier": "jane@example.com", "code": "123456"}`)
- **Events**:
  - `Vendor\LaravelAuthentication\Events\OtpGenerated` (Listen to this event to send SMS, WhatsApp, or custom Mail)
  - `Vendor\LaravelAuthentication\Events\OtpVerified`

---

## Social / OAuth Login (Google & GitHub)

Integrates cleanly with Laravel Socialite.

### Prerequisites:
Install Socialite in your host application:
```bash
composer require laravel/socialite
```
Configure your OAuth keys in `config/services.php`:
```php
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect'      => env('APP_URL') . '/auth/google/callback',
],
'github' => [
    'client_id'     => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect'      => env('APP_URL') . '/auth/github/callback',
],
```

- **Web Routes**:
  - Redirect: `GET /auth/{provider}/redirect`
  - Callback: `GET /auth/{provider}/callback`
- **API Endpoint**:
  - `POST /api/v1/auth/social/{provider}`

---

## Password Recovery & Reset

Self-service password recovery with user enumeration defense.

- **Web Routes**:
  - Request Reset Link: `GET /forgot-password`, `POST /forgot-password`
  - Reset Form: `GET /reset-password/{token}`, `POST /reset-password`
- **API Endpoints**:
  - `POST /api/v1/auth/forgot-password` (`{"email": "user@example.com"}`)
  - `POST /api/v1/auth/reset-password` (`{"token": "...", "email": "...", "password": "...", "password_confirmation": "..."}`)

---

## API Endpoints Reference

All API routes default to the prefix `/api/v1/auth`:

| Method | Endpoint | Description | Toggle Flag |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/auth/login` | Authenticate with credentials & receive Bearer token | Always active |
| `POST` | `/api/v1/auth/register` | Register new user account | `features.registration.enabled` |
| `POST` | `/api/v1/auth/forgot-password` | Send password reset email | `features.forgot_password.enabled` |
| `POST` | `/api/v1/auth/reset-password` | Reset password using token | `features.forgot_password.enabled` |
| `POST` | `/api/v1/auth/otp/send` | Request single-use OTP code | `features.otp.enabled` |
| `POST` | `/api/v1/auth/otp/verify` | Verify OTP code & receive Bearer token | `features.otp.enabled` |
| `POST` | `/api/v1/auth/social/{provider}` | Stateless OAuth token exchange | `features.social.enabled` |
| `POST` | `/api/v1/auth/logout` | Revoke current Bearer token | Guard protected (`auth:sanctum`) |

---

## Supported Login Strategies

Configure default strategy in `config/authentication.php`:
- `username_or_email` *(Default)*: Autodetects whether input is email or username.
- `email_password`: Strictly expects standard email format.
- `username_password`: Strictly matches against the username column.
- `custom_identifier`: Matches against custom configured column (e.g. `employee_id`).

---

## Extending Custom Strategies (e.g. Employee ID)

### Step 1: Implement Strategy
```php
namespace App\Authentication\Strategies;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\Contracts\AuthenticationStrategyInterface;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EmployeeIdStrategy implements AuthenticationStrategyInterface
{
    public function name(): string
    {
        return 'employee_id';
    }

    public function supports(LoginData $data): bool
    {
        return str_starts_with($data->identifier, 'EMP-');
    }

    public function resolveUser(LoginData $data, AuthenticationContext $context): ?Authenticatable
    {
        return User::where('employee_id', $data->identifier)->first();
    }

    public function validateCredentials(Authenticatable $user, LoginData $data): bool
    {
        return Hash::check($data->password, $user->getAuthPassword());
    }
}
```

### Step 2: Register in `AppServiceProvider`
```php
use Vendor\LaravelAuthentication\Support\AuthenticationStrategyRegistry;
use App\Authentication\Strategies\EmployeeIdStrategy;

public function boot(AuthenticationStrategyRegistry $registry): void
{
    $registry->register('employee_id', EmployeeIdStrategy::class);
}
```

---

## Security & Threat Mitigations

| Threat | Attack Vector | Mitigation in Package |
| :--- | :--- | :--- |
| **Brute Force** | High-frequency password guessing | Rate limiting with IP + Identifier composite throttling. |
| **User Enumeration** | Timing/error message discrepancy probing | Identical generic error messages and uniform execution paths. |
| **Session Fixation** | Attacker pre-sets session ID | Full `session()->regenerate()` immediately upon valid login. |
| **SQL Injection** | Malicious identifier payloads | Parameterized Eloquent/PDO query bindings throughout. |
| **Credential Leakage** | Plaintext logs / stack traces | Redacted audit log sinks and `#[\SensitiveParameter]` attributes. |
| **Password Reuse** | Immediate revert to old passwords | Encrypted password history repository tracking last *N* hashes. |

---

## Testing

Run the test suite in the package:
```bash
composer test
```
Or execute PHPUnit:
```bash
vendor/bin/phpunit
```

---

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).
