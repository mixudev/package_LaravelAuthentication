# Unified Module Installation (Single-Folder Mode)

If you prefer keeping all authentication files (Config, Database Migrations, Blade Views, and Routes) bundled together in a **single clean `modules/Authentication/` (or `app/Modules/Authentication/`) folder** instead of scattering them across standard Laravel directories, the package provides a built-in one-command module exporter.

---

## 📁 Unified Module Directory Structure

When exported, everything lives inside one dedicated module folder:

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
│       └── otp-verify.blade.php
└── Routes/
    ├── web.php                               # Web form & callback routes
    └── api.php                               # REST API endpoints
```

---

## 🚀 Quick Setup Guide for New or Existing Projects

### Step 0: Require the Package via Composer
Before running any Artisan commands, make sure the package is installed in your Laravel project:

#### Option A: If Published to Packagist / GitHub
```bash
composer require mixudev/laravel-authentication
```

#### Option B: If Testing Locally (Path Repository)
Add the local package path to your new project's `composer.json`:
```json
"repositories": [
    {
        "type": "path",
        "url": "../packages/LaravelAuthentication",
        "options": {
            "symlink": true
        }
    }
]
```
*(Sesuaikan path `url` ke lokasi folder package Anda, misalnya `D:/WEBSITE/PACKAGE/LaravelAuthentication`)*

Lalu jalankan di terminal project baru Anda:
```bash
composer require mixudev/laravel-authentication:@dev
```

---

### Step 1: Run the Module Exporter Command
Once composer finishes installing the package, run:

```bash
# Default path: modules/Authentication
php artisan authentication:install-module

# Or specify a custom target path (e.g. app/Modules/Authentication):
php artisan authentication:install-module --path=app/Modules/Authentication
```

*Alternative via `vendor:publish`:*
```bash
php artisan vendor:publish --tag=authentication-module
```

---

### Step 2: Register the Module Service Provider

#### For Laravel 11, 12, and 13:
Add the generated provider to `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    Modules\Authentication\AuthenticationModuleServiceProvider::class, // <-- Tambahkan ini
];
```

#### For Laravel 10:
Add to the `providers` array in `config/app.php`:

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    Modules\Authentication\AuthenticationModuleServiceProvider::class,
])->toArray(),
```

---

### Step 3: Autoload the Module in `composer.json`
If your project does not already map the `Modules\` namespace, add it to the `autoload.psr-4` section in `composer.json`:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Database\\Factories\\": "database/factories/",
        "Database\\Seeders\\": "database/seeders/",
        "Modules\\": "modules/"
    }
}
```

Then regenerate the autoloader:
```bash
composer dump-autoload
```

---

### Step 4: Run Migrations
Run your database migrations:

```bash
php artisan migrate
```

---

## 🎯 Benefits of Unified Single-Folder Module Mode

1. **Clean Project Root**: No clutter in your main `config/`, `database/migrations/`, or `resources/views/` folders.
2. **Effortless Version Control & Maintenance**: You can edit Blade templates, tweak routes, or modify authentication policies in one localized directory.
3. **Modular Portability**: Copy or move `modules/Authentication/` between projects seamlessly.
