# Features & Modular Switches

The package architecture uses strict feature switches in `config/authentication.php`. When any feature is set to `false`, its Web routes, API endpoints, and UI buttons are automatically disabled (fail-closed).

---

## 1. User Registration (`features.registration`)

Provides streamlined user registration for both Web forms and API requests.

### Configuration:
```php
'features' => [
    'registration' => [
        'enabled'                => env('AUTH_REGISTRATION_ENABLED', true),
        'auto_login_on_register' => env('AUTH_AUTO_LOGIN_ON_REGISTER', true),
        'require_email_verify'   => env('AUTH_REQUIRE_EMAIL_VERIFY', false),
    ],
],
```

- **Web Endpoint**: `GET /register`, `POST /register`
- **API Endpoint**: `POST /api/v1/auth/register`
- **Fields**: `name`, `email`, `password`, `password_confirmation`
- **Event Dispatched**: `Vendor\LaravelAuthentication\Events\UserRegistered`

---

## 2. Passwordless OTP Login (`features.otp`)

Enables login via one-time verification codes sent to the user's email or identifier.

### Configuration:
```php
'features' => [
    'otp' => [
        'enabled'          => env('AUTH_OTP_ENABLED', true),
        'length'           => (int) env('AUTH_OTP_LENGTH', 6),
        'expiry_minutes'   => (int) env('AUTH_OTP_EXPIRY_MINUTES', 10),
        'max_attempts'     => (int) env('AUTH_OTP_MAX_ATTEMPTS', 3),
        'throttle_seconds' => (int) env('AUTH_OTP_THROTTLE_SECONDS', 60),
        'type'             => 'numeric', // 'numeric' or 'alphanumeric'
    ],
],
```

- **Web Endpoints**:
  - Request Code: `GET /otp/login`, `POST /otp/send`
  - Verify Code: `GET /otp/verify?identifier=...`, `POST /otp/verify`
- **API Endpoints**:
  - `POST /api/v1/auth/otp/send`
  - `POST /api/v1/auth/otp/verify`
- **Events**:
  - `Vendor\LaravelAuthentication\Events\OtpGenerated` (Contains the unhashed code to send via Mail/SMS/WhatsApp notification)
  - `Vendor\LaravelAuthentication\Events\OtpVerified`

---

## 3. Social / OAuth Login (`features.social`)

Provides OAuth 2.0 social login via **Laravel Socialite** with auto-provisioning.

### Prerequisites:
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

### Configuration:
```php
'features' => [
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

- **Web Routes**:
  - Redirect: `GET /auth/{provider}/redirect`
  - Callback: `GET /auth/{provider}/callback`
- **API Endpoint**:
  - `POST /api/v1/auth/social/{provider}`

---

## 4. Self-Service Password Recovery (`features.forgot_password`)

Provides secure password reset links with user enumeration protection.

### Configuration:
```php
'features' => [
    'forgot_password' => [
        'enabled' => env('AUTH_FORGOT_PASSWORD_ENABLED', true),
    ],
],
```

- **Web Routes**:
  - Request Form: `GET /forgot-password`, `POST /forgot-password`
  - Reset Form: `GET /reset-password/{token}`, `POST /reset-password`
- **API Endpoints**:
  - `POST /api/v1/auth/forgot-password`
  - `POST /api/v1/auth/reset-password`
