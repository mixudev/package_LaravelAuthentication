# Views & UI Customization Guide

The **`mixudev/laravel-authentication`** package gives you complete flexibility over your authentication user interface. You can:
1. **Switch Built-in Layout Templates & Themes** (`split` 2-column or `card` centered layout, with `light`, `dark`, or `auto` theme modes).
2. **Publish & Modify Blade Components** via `php artisan vendor:publish --tag=authentication-views` or `php artisan authentication:install --views`.
3. **Bring Your Own UI** by mapping package controllers directly to your own custom Blade templates.

---

## 🎨 Theme Modes: Light, Dark, & Auto

The package features an intelligent, flicker-free Theme Engine configured via `config/authentication.php` or `.env`:

```env
# Options: 'light', 'dark', or 'auto'
AUTH_UI_THEME=auto
```

* **`light`**: Forces crisp high-contrast light mode with clean borders and dark typography.
* **`dark`**: Forces sleek deep dark mode (`#09090b` zinc backdrop and `#121215` cards).
* **`auto`**: Dynamically detects the user's OS/browser theme (`prefers-color-scheme: dark`) and automatically listens for real-time system changes.

---

## 🧭 3 Ways to Customize the UI

---

### Option 1: Switch Built-in Layout Templates (Quickest)

The package includes 2 pre-styled templates powered by Tailwind CSS:
- **`split` (Default)**: 2-column layout (Brand & telemetry sidebar on the left, interactive form on the right).
- **`card`**: Minimalist centered single-card layout.

Switch layouts in `.env`:
```env
# Choose: 'split' or 'card'
AUTH_UI_LAYOUT=card
AUTH_UI_THEME=light

# Brand settings
AUTH_UI_BRAND_NAME="My Application"
AUTH_UI_BRAND_TAGLINE="Enterprise Security Gateway"
AUTH_UI_BRAND_BADGE="SYSTEM LIVE // TLS 1.3"
```

Or configure directly in [`config/authentication.php`](file:///d:/WEBSITE/PACKAGE/LaravelAuthentication/config/authentication.php):
```php
'ui' => [
    'layout'        => env('AUTH_UI_LAYOUT', 'card'),
    'theme'         => env('AUTH_UI_THEME', 'light'), // 'light', 'dark', 'auto'
    'brand_name'    => 'My Application',
    'brand_tagline' => 'Enterprise Security Gateway',
],
```

---

### Option 2: Publish Built-in Views & Customize Components

If you want to customize Blade templates directly:

```bash
php artisan vendor:publish --tag=authentication-views
```

Templates will be published to `resources/views/vendor/authentication/`:
```
resources/views/vendor/authentication/
├── components/
│   ├── layouts/
│   │   ├── auth.blade.php
│   │   ├── split.blade.php
│   │   └── card.blade.php
│   ├── active-sessions.blade.php
│   ├── input.blade.php
│   ├── button.blade.php
│   ├── checkbox.blade.php
│   ├── alert.blade.php
│   ├── divider.blade.php
│   ├── otp-input.blade.php
│   ├── social-buttons.blade.php
│   └── brand-panel.blade.php
├── login.blade.php
├── register.blade.php
├── forgot-password.blade.php
├── reset-password.blade.php
├── otp-request.blade.php
├── otp-verify.blade.php
├── sessions.blade.php
├── two-factor-setup.blade.php
└── two-factor-challenge.blade.php
```

Laravel will automatically give precedence to the published views in your host application.

---

### Option 3: Bring Your Own UI (Custom Blade Views)

Point the package controllers directly to your own views without modifying backend routes or services.

#### 1. Configure in `.env` or `config/authentication.php`:

```env
AUTH_VIEW_LOGIN=auth.login
AUTH_VIEW_REGISTER=auth.register
AUTH_VIEW_FORGOT_PASSWORD=auth.forgot-password
AUTH_VIEW_RESET_PASSWORD=auth.reset-password
AUTH_VIEW_OTP_REQUEST=auth.otp-request
AUTH_VIEW_OTP_VERIFY=auth.otp-verify
AUTH_VIEW_SESSIONS=auth.sessions
AUTH_VIEW_TWO_FACTOR_SETUP=auth.two-factor-setup
AUTH_VIEW_TWO_FACTOR_CHALLENGE=auth.two-factor-challenge
```

In `config/authentication.php`:
```php
'views' => [
    'login'                => env('AUTH_VIEW_LOGIN', 'auth.login'),
    'register'             => env('AUTH_VIEW_REGISTER', 'auth.register'),
    'forgot_password'      => env('AUTH_VIEW_FORGOT_PASSWORD', 'auth.forgot-password'),
    'reset_password'       => env('AUTH_VIEW_RESET_PASSWORD', 'auth.reset-password'),
    'otp_request'          => env('AUTH_VIEW_OTP_REQUEST', 'auth.otp-request'),
    'otp_verify'           => env('AUTH_VIEW_OTP_VERIFY', 'auth.otp-verify'),
    'sessions'             => env('AUTH_VIEW_SESSIONS', 'auth.sessions'),
    'two_factor_setup'     => env('AUTH_VIEW_TWO_FACTOR_SETUP', 'auth.two-factor-setup'),
    'two_factor_challenge' => env('AUTH_VIEW_TWO_FACTOR_CHALLENGE', 'auth.two-factor-challenge'),
],
```

---

## ⚡ Tailwind CSS & Alpine.js Integration

The package works standalone out of the box with CDN fallbacks, or seamlessly integrates with Vite:

* **Automatic Setup**: Run `php artisan authentication:install` to automatically inject package views into Tailwind sources.
* **Tailwind v4 Setup (`resources/css/app.css`)**:
  ```css
  @import "tailwindcss";
  @source "../../vendor/mixudev/laravel-authentication/resources/views";
  @custom-variant dark (&:where(.dark, .dark *));
  ```
* **Alpine.js**: Modals (like 2FA Disable Confirmation) and interactive OTP inputs use Alpine.js, which is loaded automatically in the base layout.
