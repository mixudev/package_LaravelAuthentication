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
4. [Installation Methods](#installation-methods)
   - [A. Standard Packagist](#a-standard-packagist)
   - [B. Private Git VCS](#b-private-git-vcs)
   - [C. Local Composer Path Repository](#c-local-composer-path-repository)
   - [D. Tagged ZIP Distribution](#d-tagged-zip-distribution)
5. [Publishing Assets](#publishing-assets)
6. [Configuration Guide](#configuration-guide)
7. [Supported Login Strategies](#supported-login-strategies)
8. [Extending: Custom Strategy (e.g. Employee ID)](#extending-custom-strategy-eg-employee-id)
9. [Multi-Project Deployment Recipes](#multi-project-deployment-recipes)
10. [Security & Threat Mitigation](#security--threat-mitigation)
11. [Event System & Auditing](#event-system--auditing)
12. [Testing](#testing)
13. [Troubleshooting & FAQ](#troubleshooting--faq)
14. [Security Policy & Disclosures](#security-policy--disclosures)

---

## Key Features

- **Decoupled Architecture**: Zero hard-coded coupling to `App\Models\User` or host application namespaces.
- **Strategy Pattern Engine**: Easily switch between `username_or_email`, `email_password`, `username_password`, or add custom strategies (`employee_id`, `phone_number`, `sso`) at runtime.
- **Fail-Closed Design**: Corrupted configurations or invalid strategies safely reject operations without fallback degradation.
- **User Enumeration Defense**: Consistent error responses and normalized execution timing for existing vs non-existent accounts.
- **Composite Rate Limiting**: Anti-brute force and credential stuffing defense with configurable decay windows and IP/Identifier composite throttling.
- **Account Lockout Management**: Configurable lockout thresholds and automated recovery timeouts.
- **Session Security**: Native session ID regeneration upon login (session fixation prevention) and complete cache/token invalidation on logout.
- **Password Hygiene**: Transparent password rehashing to modern algorithms and historical password reuse prevention.
- **Audit Logging**: Structured security event trails with automated PII masking and credential redaction.

---

## Compatibility Matrix

| Package Version | PHP Versions Supported | Laravel Target Versions | Status |
| :--- | :--- | :--- | :--- |
| **1.0.x** / **main** | `8.1`, `8.2`, `8.3`, `8.4`, `8.5` | `10.x`, `11.x`, `12.x`, `13.x` | **Active / Supported** |
| **2.0.x** *(Planned)* | `8.3`, `8.4`, `8.5` | `12.x`, `13.x`, `14.x` | *Future Roadmap* |

---

## Architecture Overview

```
Laravel HTTP Request
        ↓
Package Service Provider
        ↓
Form Request Validation
        ↓
Login Attempt Rate Limiter
        ↓
Authentication Service
        ↓
Authentication Strategy Registry
        ↓
Identifier Normalizer
        ↓
Identity Resolver
        ↓
Credential Validator (Rehashing & Hash Checks)
        ↓
Security Policy / Account Lockout Check
        ↓
Authentication Result
        ↓
Session Login / API Bearer Token
        ↓
Event Dispatcher (LoginSucceeded / LoginFailed)
        ↓
Security Audit Log (Redacted Metadata)
        ↓
HTTP Response
```

---

## Installation Methods

### A. Standard Packagist
If the package is published to public Packagist:
```bash
composer require mixudev/laravel-authentication
```

### B. Private Git VCS
If the package resides in a private company repository (GitHub, GitLab, Bitbucket):
Add the repository definition to the host application's `composer.json`:
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:mixudev/package_LaravelAuthentication.git"
        }
    ]
}
```
Then run:
```bash
composer require mixudev/laravel-authentication:^1.0
```

### C. Local Composer Path Repository
For monorepos or local package development:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/laravel-authentication",
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

### D. Tagged ZIP Distribution
For offline environments or air-gapped distributions:
1. Extract the release ZIP into `packages/laravel-authentication`.
2. Add a path repository in the host app's `composer.json`:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/laravel-authentication"
        }
    ]
}
```
3. Execute:
```bash
composer require mixudev/laravel-authentication:*
```

---

## Publishing Assets

After installation, publish the configuration and migrations:

```bash
# Publish Configuration
php artisan vendor:publish --tag=authentication-config

# Publish Migrations
php artisan vendor:publish --tag=authentication-migrations

# Run Database Migrations
php artisan migrate
```

Optional view and language asset tags:
```bash
php artisan vendor:publish --tag=authentication-views
php artisan vendor:publish --tag=authentication-lang
```

---

## Configuration Guide

The published `config/authentication.php` controls all security and authentication policies:

```php
return [
    // Master package switch (fail-closed)
    'enabled' => env('AUTH_PACKAGE_ENABLED', true),

    // Default guard & Eloquent User Model
    'guard' => 'web',
    'user_model' => App\Models\User::class,

    // Login strategy configuration
    'login' => [
        'default_strategy' => 'username_or_email',
        'identifiers' => [
            'username_column' => 'username',
            'email_column'    => 'email',
            'custom_column'   => 'employee_id',
            'password_column' => 'password',
        ],
        'normalize_identifiers' => true,
    ],

    // Security & Abuse Defense
    'security' => [
        'user_enumeration_protection' => true,

        'rate_limit' => [
            'enabled'       => true,
            'max_attempts'  => 5,
            'decay_minutes' => 1,
            'strategy'      => 'composite', // 'ip', 'identifier', 'composite'
        ],

        'account_lockout' => [
            'enabled'                => false,
            'max_failed_attempts'    => 5,
            'lockout_duration_mins'  => 15,
        ],

        'session' => [
            'regenerate_on_login'  => true,
            'invalidate_on_logout' => true,
        ],
    ],
];
```

---

## Extending: Custom Strategy (e.g. Employee ID)

To create an authentication method such as **Employee ID + Password**:

### Step 1: Implement the Strategy
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

## Multi-Project Deployment Recipes

### Project A: High-Volume Consumer Web App
- Strategy: `username_or_email`
- Session: Web Guard with session regeneration
- Rate Limiting: Composite (IP + Identifier)

### Project B: Enterprise Internal Portal
- Strategy: `employee_id`
- Security: Account Lockout enabled (3 failures = 30-minute lockout)
- Password: Password history remembering last 10 passwords

### Project C: Mobile / SPA Backend
- Strategy: `email_password`
- Channel: API tokens (Sanctum)
- Routes: `config/authentication.php` -> `routes.api.enabled => true`

---

## Security & Threat Mitigation

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

Run the full automated test suite:

```bash
composer test
```
Or execute PHPUnit directly:
```bash
vendor/bin/phpunit
```

To run static analysis:
```bash
vendor/bin/phpstan analyse
```

---

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).
