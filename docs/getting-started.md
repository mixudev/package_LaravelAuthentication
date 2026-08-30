# Getting Started

## 1. Installation via Composer

### Option A: Standard Composer Require
```bash
composer require mixudev/laravel-authentication
```

### Option B: Local Path Repository (Development / Monorepo)
Add to host application's `composer.json`:
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

## 2. One-Step Automatic Setup (Recommended)

Run the interactive installer to automatically publish configuration, publish database migrations, execute migrations, and **automatically configure Tailwind CSS / `app.css`**:

```bash
php artisan authentication:install
```

### Optional Installer Flags:
* `--views`: Also publish Blade view templates to `resources/views/vendor/authentication/`.
* `--force`: Overwrite existing configuration and published assets.
* `--migrate`: Run database migrations immediately without interactive prompt.

---

## 3. Manual Asset Publishing (Alternative)

If you prefer publishing assets manually:

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
- `two_factor_authentications` (encrypted TOTP secrets & hashed recovery codes)
- `authentication_devices` (fingerprints, device names, and trust states)

### C. Publish Blade Views (Optional)
```bash
php artisan vendor:publish --tag=authentication-views
```
Copies templates to `resources/views/vendor/authentication/` for custom styling.

### D. Manual Tailwind CSS Configuration
* **Tailwind CSS v4 (`resources/css/app.css`):**
  ```css
  @import "tailwindcss";
  @source "../../vendor/mixudev/laravel-authentication/resources/views";
  @custom-variant dark (&:where(.dark, .dark *));
  ```
* **Tailwind CSS v3 (`tailwind.config.js`):**
  ```javascript
  export default {
    darkMode: 'class',
    content: [
      './resources/views/**/*.blade.php',
      './vendor/mixudev/laravel-authentication/resources/views/**/*.blade.php',
    ],
  }
  ```
