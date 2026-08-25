# Unified Module Installation (Single-Folder Mode)

If you prefer keeping all authentication files — Config, Migrations, Blade Views, and Routes — bundled together in a **single clean `modules/Authentication/` folder** instead of scattering them across standard Laravel directories, the package provides a built-in module exporter.

---

## 📁 Resulting Module Structure

After exporting, everything lives inside one dedicated module folder:

```
modules/Authentication/
├── AuthenticationModuleServiceProvider.php   # Auto-bootstraps all module assets
├── Config/
│   └── authentication.php                    # Feature switches & policies
├── Database/
│   └── Migrations/
│       ├── 2026_01_01_000001_create_authentication_attempts_table.php
│       ├── 2026_01_01_000002_create_login_histories_table.php
│       └── 2026_01_01_000003_create_password_histories_table.php
├── Resources/
│   └── Views/
│       ├── login.blade.php
│       ├── register.blade.php
│       ├── forgot-password.blade.php
│       ├── reset-password.blade.php
│       ├── otp-request.blade.php
│       ├── otp-verify.blade.php
│       └── emails/
│           └── otp.blade.php
└── Routes/
    ├── web.php                               # Web form & callback routes
    └── api.php                               # REST API endpoints
```

---

## 🚀 Setup Guide

### Step 0: Install the Package via Composer

#### Option A — From Packagist (Recommended for production)

```bash
composer require mixudev/laravel-authentication
```

#### Option B — From a Local Path (For development / testing on your own machine)

If you are developing locally and want changes to the package to be reflected immediately **without waiting for Packagist**, use a **path repository**:

1. Add the following to your project's `composer.json` (adjust the `url` to where the package lives on your machine):

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "D:/WEBSITE/PACKAGE/LaravelAuthentication",
            "options": {
                "symlink": false
            }
        }
    ],
    "require": {
        "mixudev/laravel-authentication": "@dev"
    }
}
```

> **Note**: Use `"symlink": false` on Windows to avoid junction/symlink permission issues. This copies the package into `vendor/` at install time. After any change to the source package, run `composer reinstall mixudev/laravel-authentication` to re-copy.

2. Then install:

```bash
composer install
```

#### Option C — From a Private Git / VCS Repository

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/your-org/your-private-repo.git"
        }
    ],
    "require": {
        "mixudev/laravel-authentication": "^1.0"
    }
}
```

---

### Step 1: Export the Module

Once the package is installed, run **one** of the following commands to export the module:

#### Recommended — Artisan Command (Full Interactive Export)

```bash
# Default target: modules/Authentication
php artisan authentication:install-module

# Custom target path
php artisan authentication:install-module --path=app/Modules/Authentication

# Force overwrite existing files without prompt
php artisan authentication:install-module --force
```

#### Alternative — Standard `vendor:publish`

```bash
php artisan vendor:publish --tag=authentication-module
```

> The Artisan command is preferred as it also generates the self-contained `AuthenticationModuleServiceProvider.php` and displays post-install instructions.

---

### Step 2: Add `Modules\` Namespace to Composer Autoloader

If your `composer.json` does not yet map the `Modules\` namespace, add it to `autoload.psr-4`:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/",
            "Modules\\": "modules/"
        }
    }
}
```

Then regenerate the autoloader:

```bash
composer dump-autoload
```

---

### Step 3: Register the Module Service Provider

#### Laravel 11, 12, 13 — `bootstrap/providers.php`

```php
return [
    App\Providers\AppServiceProvider::class,
    Modules\Authentication\AuthenticationModuleServiceProvider::class, // <-- Add this
];
```

#### Laravel 10 — `config/app.php`

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    Modules\Authentication\AuthenticationModuleServiceProvider::class,
])->toArray(),
```

---

### Step 4: Run Database Migrations

```bash
php artisan migrate
```

---

### Step 5: Configure the Package

Copy and customize the configuration file if you haven't already:

```bash
php artisan vendor:publish --tag=authentication-config
```

Then edit `config/authentication.php` (or `modules/Authentication/Config/authentication.php`) with your `.env` keys:

```dotenv
# Feature Toggles
AUTH_OTP_ENABLED=true
AUTH_SOCIAL_ENABLED=true

# Password Policy
AUTH_PASSWORD_MIN_LENGTH=8
AUTH_PASSWORD_REQUIRE_UPPERCASE=true
AUTH_PASSWORD_REQUIRE_LOWERCASE=true
AUTH_PASSWORD_REQUIRE_NUMBERS=true
AUTH_PASSWORD_REQUIRE_SYMBOLS=true

# OAuth Credentials
AUTH_GOOGLE_CLIENT_ID=your-google-client-id
AUTH_GOOGLE_CLIENT_SECRET=your-google-client-secret
AUTH_GITHUB_CLIENT_ID=your-github-client-id
AUTH_GITHUB_CLIENT_SECRET=your-github-client-secret
```

---

## 🛠 Updating After Package Changes

### If using Packagist

```bash
composer update mixudev/laravel-authentication
```

### If using a Local Path Repository (`"symlink": false`)

Because files are **copied** (not symlinked) into `vendor/`, you must re-copy after any source change:

```bash
composer reinstall mixudev/laravel-authentication
```

> If you set `"symlink": true` in your path repository options, changes in the source package are reflected immediately with no reinstall needed. However, on Windows this requires running your terminal as Administrator due to junction creation permissions.

### Re-export the Module (Optional)

After updating the package, re-export the module to get the latest views and routes:

```bash
php artisan authentication:install-module --force
```

---

## 🎯 Benefits of Unified Single-Folder Mode

| Benefit | Description |
|---------|-------------|
| **Clean Project Root** | No clutter in `config/`, `database/migrations/`, or `resources/views/` |
| **Version Control** | Commit the entire `modules/Authentication/` folder as part of your project |
| **Easy Customization** | Edit Blade views, routes, and config in one localized directory |
| **Portability** | Copy or move `modules/Authentication/` between projects seamlessly |
| **Overridable** | Your module config takes precedence over the package default |
