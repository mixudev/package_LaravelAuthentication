# Getting Started

## 1. Installation

### Option A: Standard Composer Require
```bash
composer require mixudev/laravel-authentication
```

### Option B: Local Path Repository (Local / Monorepo)
Add to host `composer.json`:
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

## 2. Publishing Assets

### A. Publish Configuration
```bash
php artisan vendor:publish --tag=authentication-config
```
Creates `config/authentication.php` in your application.

### B. Publish & Run Migrations
```bash
php artisan vendor:publish --tag=authentication-migrations
php artisan migrate
```
This creates:
- `authentication_attempts` (tracks IP, identifier, success/failure status for audit & lockout)
- `login_histories` (records login sessions, user agents, channels)
- `password_histories` (stores historical password hashes for reuse prevention)

### C. Publish Blade Views (Optional)
```bash
php artisan vendor:publish --tag=authentication-views
```
Copies templates to `resources/views/vendor/authentication/` for full customization.
