# Views & UI Customization Guide

The **`mixudev/laravel-authentication`** package gives you complete flexibility over your authentication user interface. You can:
1. **Switch Built-in Layout Templates** (`split` 2-column or `card` centered layout) with a single `.env` config.
2. **Publish & Modify Blade Components** via `vendor:publish`.
3. **Bring Your Own UI** by mapping package controllers directly to your own custom Blade templates.

---

## 🧭 3 Ways to Customize the UI

---

### Option 1: Switch Built-in Layout Templates (Quickest)

The package includes 2 pre-styled dark console templates powered by Tailwind CSS:
- **`split` (Default)**: 2-column layout (Brand & live telemetry sidebar on the left, interactive form on the right).
- **`card`**: Minimalist centered single-card layout with an ambient glow backdrop.

Switch layouts instantly in your `.env` file:
```env
# Choose: 'split' or 'card'
AUTH_UI_LAYOUT=card

# Brand settings
AUTH_UI_BRAND_NAME="My Application"
AUTH_UI_BRAND_TAGLINE="Enterprise Security Gateway"
AUTH_UI_BRAND_BADGE="SYSTEM LIVE // TLS 1.3"
```

Or configure directly in [`config/authentication.php`](file:///d:/WEBSITE/PACKAGE/LaravelAuthentication/config/authentication.php):
```php
'ui' => [
    'layout'        => env('AUTH_UI_LAYOUT', 'card'),
    'theme'         => env('AUTH_UI_THEME', 'dark'),
    'brand_name'    => 'My Application',
    'brand_tagline' => 'Enterprise Security Gateway',
],
```

---

### Option 2: Publish Built-in Views & Customize Components

If you like the structure but want to edit Tailwind classes, colors, or component layouts:

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
└── otp-verify.blade.php
```

Laravel will automatically give precedence to the published views in your application.

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
```

In `config/authentication.php`:
```php
'views' => [
    'login'           => env('AUTH_VIEW_LOGIN', 'auth.login'),
    'register'        => env('AUTH_VIEW_REGISTER', 'auth.register'),
    'forgot_password' => env('AUTH_VIEW_FORGOT_PASSWORD', 'auth.forgot-password'),
    'reset_password'  => env('AUTH_VIEW_RESET_PASSWORD', 'auth.reset-password'),
    'otp_request'     => env('AUTH_VIEW_OTP_REQUEST', 'auth.otp-request'),
    'otp_verify'      => env('AUTH_VIEW_OTP_VERIFY', 'auth.otp-verify'),
    'otp_email'       => env('AUTH_VIEW_OTP_EMAIL', 'authentication::emails.otp'),
],
```

---

## 📋 Page Technical Specifications

When building custom views, use the exact action route names and required input field names below:

### 1. Login Page (`login`)
- **GET View Route**: `/login`
- **POST Action Route**: `{{ route('login.perform') }}`
- **Required Inputs**:
  - `@csrf`
  - `identifier` (Text input for email or username)
  - `password` (Password input)
  - `remember` *(optional checkbox)*

### 2. OTP Request Page (`otp-request`)
- **GET View Route**: `/otp/login`
- **POST Action Route**: `{{ route('otp.send') }}`
- **Required Inputs**:
  - `@csrf`
  - `identifier` (Email or username receiving the OTP code)

### 3. OTP Verification Page (`otp-verify`)
- **GET View Route**: `/otp/verify`
- **POST Action Route**: `{{ route('otp.verify') }}`
- **View Data**: Controller passes `$identifier`
- **Required Inputs**:
  - `@csrf`
  - `identifier` (Hidden input with `$identifier` value)
  - `code` (6-digit verification code)
  - `remember` *(optional checkbox)*

### 4. Registration Page (`register`)
- **POST Action Route**: `{{ route('register.perform') }}`
- **Required Inputs**:
  - `@csrf`
  - `name`, `email`, `password`, `password_confirmation`

### 5. Password Reset Flow
- **Request Link Form**: `POST {{ route('password.email') }}` with `email`
- **Reset Password Form**: `POST {{ route('password.update') }}` with `token`, `email`, `password`, `password_confirmation`

---

## 🧩 Reusable Blade Components

Even in your own custom views, you can reuse the package's built-in Blade components:

```blade
{{-- Input with auto @error binding and password show/hide toggle --}}
<x-authentication::input name="email" type="email" label="Email Address" :required="true" />

{{-- Styled interactive button --}}
<x-authentication::button type="submit" variant="primary">Sign In</x-authentication::button>

{{-- Auto-focus 6-digit segmented OTP input --}}
<x-authentication::otp-input name="code" :length="6" />

{{-- Social OAuth buttons (Google & GitHub) --}}
<x-authentication::social-buttons />

{{-- Session status alert banners --}}
<x-authentication::alert type="success" message="Success message" />
```
