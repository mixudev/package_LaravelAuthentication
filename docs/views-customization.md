# Views & UI Customization Guide

The package gives you complete flexibility over the user interface. You can either customize the built-in **Sentra Console** dark theme or plug in your own custom UI templates (Tailwind CSS, Bootstrap, React/Vue, or custom Blade).

---

## 🧭 Customization Approaches

### Approach 1: Customize Built-in Sentra Console Theme
Publish the pre-styled Blade templates directly into your application:

```bash
# Standard Laravel structure:
php artisan vendor:publish --tag=authentication-views

# Or unified single-folder module structure:
php artisan authentication:install-module
```

Published templates will reside in:
- `resources/views/vendor/authentication/` (Standard mode)
- `modules/Authentication/Resources/Views/` (Single-Folder Module mode)

---

### Approach 2: Bring Your Own UI (Custom Views)
You can point the package to your own existing Blade views without touching package files.

In your `config/authentication.php` (or `.env` file):

```php
'views' => [
    'login'           => env('AUTH_VIEW_LOGIN', 'auth.login'),
    'register'        => env('AUTH_VIEW_REGISTER', 'auth.register'),
    'forgot_password' => env('AUTH_VIEW_FORGOT_PASSWORD', 'auth.forgot-password'),
    'reset_password'  => env('AUTH_VIEW_RESET_PASSWORD', 'auth.reset-password'),
    'otp_request'     => env('AUTH_VIEW_OTP_REQUEST', 'auth.otp-request'),
    'otp_verify'      => env('AUTH_VIEW_OTP_VERIFY', 'auth.otp-verify'),
],
```

Or configure via `.env`:
```env
AUTH_VIEW_LOGIN=auth.login
AUTH_VIEW_REGISTER=auth.register
AUTH_VIEW_FORGOT_PASSWORD=auth.forgot-password
AUTH_VIEW_RESET_PASSWORD=auth.reset-password
AUTH_VIEW_OTP_REQUEST=auth.otp-request
AUTH_VIEW_OTP_VERIFY=auth.otp-verify
```

---

## 📋 Page-by-Page Technical Specification

When creating custom views, use the exact route names and input field names below:

### 1. Login Page (`login`)
- **Controller Action**: `GET /login`
- **Form Action**: `POST {{ route('login.perform') }}`
- **Required Fields**:
  | Field Name | Type | Description |
  | :--- | :--- | :--- |
  | `_token` | hidden | `@csrf` protection token |
  | `identifier` | text / email | Username or Email address |
  | `password` | password | User account password |
  | `remember` | checkbox *(optional)* | Value `1` for persistent session |

- **Conditional Navigation Links**:
  ```blade
  {{-- Registration Link --}}
  @if (config('authentication.features.registration.enabled', true))
    <a href="{{ route('register') }}">Create an account</a>
  @endif

  {{-- Forgot Password Link --}}
  @if (config('authentication.features.forgot_password.enabled', true))
    <a href="{{ route('password.request') }}">Forgot password?</a>
  @endif

  {{-- Passwordless OTP Login Link --}}
  @if (config('authentication.features.otp.enabled', true))
    <a href="{{ route('otp.request.form') }}">Login with OTP Code</a>
  @endif

  {{-- Social OAuth Buttons --}}
  @php
    $socialEnabled = config('authentication.features.social.enabled', true);
    $hasGoogle = $socialEnabled && config('authentication.features.social.providers.google.enabled', true);
    $hasGithub = $socialEnabled && config('authentication.features.social.providers.github.enabled', true);
  @endphp

  @if ($hasGoogle)
    <a href="{{ route('social.redirect', 'google') }}">Login with Google</a>
  @endif
  @if ($hasGithub)
    <a href="{{ route('social.redirect', 'github') }}">Login with GitHub</a>
  @endif
  ```

---

### 2. Registration Page (`register`)
- **Controller Action**: `GET /register`
- **Form Action**: `POST {{ route('register.perform') }}`
- **Required Fields**:
  | Field Name | Type | Description |
  | :--- | :--- | :--- |
  | `_token` | hidden | `@csrf` protection token |
  | `name` | text | User full name |
  | `email` | email | Valid email address |
  | `password` | password | Password complying with password rules |
  | `password_confirmation` | password | Must match `password` field |

- **Available View Variables**:
  `$passwordPolicy` array is automatically passed to the view:
  ```php
  [
      'min_length'        => 8,
      'require_uppercase' => true,
      'require_lowercase' => true,
      'require_numbers'   => true,
      'require_symbols'   => true,
      'symbols_charset'   => '@$!%*#?&',
  ]
  ```

---

### 3. Forgot Password Page (`forgot-password`)
- **Controller Action**: `GET /forgot-password`
- **Form Action**: `POST {{ route('password.email') }}`
- **Required Fields**:
  | Field Name | Type | Description |
  | :--- | :--- | :--- |
  | `_token` | hidden | `@csrf` protection token |
  | `email` | email | User email address |

- **Security Note (Zero Enumeration)**:
  Always display `session('status')` if present. Never render per-field error messages for the email field, as this prevents malicious actors from discovering valid accounts.

---

### 4. Reset Password Page (`reset-password`)
- **Controller Action**: `GET /reset-password/{token}`
- **Form Action**: `POST {{ route('password.update') }}`
- **Required Fields**:
  | Field Name | Type | Description |
  | :--- | :--- | :--- |
  | `_token` | hidden | `@csrf` protection token |
  | `token` | hidden | Reset token (`value="{{ $token }}"`) |
  | `email` | email / hidden | User email address (`value="{{ $email }}"`) |
  | `password` | password | New password |
  | `password_confirmation` | password | New password confirmation |

---

### 5. OTP Request Page (`otp-request`)
- **Controller Action**: `GET /otp/login`
- **Form Action**: `POST {{ route('otp.send') }}`
- **Required Fields**:
  | Field Name | Type | Description |
  | :--- | :--- | :--- |
  | `_token` | hidden | `@csrf` protection token |
  | `identifier` | text / email | Email or account identifier |

---

### 6. OTP Verification Page (`otp-verify`)
- **Controller Action**: `GET /otp/verify?identifier=...`
- **Form Action**: `POST {{ route('otp.verify') }}`
- **Required Fields**:
  | Field Name | Type | Description |
  | :--- | :--- | :--- |
  | `_token` | hidden | `@csrf` protection token |
  | `identifier` | hidden | Identifier passed from query string (`$identifier`) |
  | `code` | text | Numeric/Alphanumeric OTP code |
  | `remember` | checkbox *(optional)* | Persist login session |

- **Resend Code Form**:
  ```blade
  <form method="POST" action="{{ route('otp.send') }}">
    @csrf
    <input type="hidden" name="identifier" value="{{ $identifier }}">
    <button type="submit">Resend Code</button>
  </form>
  ```

---

## 🎨 Minimal Starter Template (Tailwind CSS Example)

Here is a clean, minimal custom Login Blade template (`resources/views/auth/login.blade.php`):

```blade
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">

  <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Sign in</h2>
    <p class="text-sm text-gray-600 mb-6">Welcome back! Please enter your details.</p>

    {{-- Status Flash Alert --}}
    @if (session('status'))
      <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
        {{ session('status') }}
      </div>
    @endif

    {{-- Error Summary --}}
    @if ($errors->any())
      <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
        {{ $errors->first() }}
      </div>
    @endif

    <form method="POST" action="{{ route('login.perform') }}" class="space-y-4">
      @csrf

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email / Username</label>
        <input type="text" name="identifier" value="{{ old('identifier') }}" required autofocus
               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" name="password" required
               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
      </div>

      <div class="flex items-center justify-between text-sm">
        <label class="flex items-center">
          <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-blue-600">
          <span class="ml-2 text-gray-600">Remember me</span>
        </label>
        @if (config('authentication.features.forgot_password.enabled', true))
          <a href="{{ route('password.request') }}" class="text-blue-600 hover:underline">Forgot password?</a>
        @endif
      </div>

      <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">
        Sign in
      </button>

      @if (config('authentication.features.otp.enabled', true))
        <a href="{{ route('otp.request.form') }}" class="block text-center w-full py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium">
          Sign in with OTP Code
        </a>
      @endif
    </form>

    @if (config('authentication.features.registration.enabled', true))
      <p class="text-center text-sm text-gray-600 mt-6">
        Don't have an account? <a href="{{ route('register') }}" class="text-blue-600 font-semibold hover:underline">Sign up</a>
      </p>
    @endif
  </div>

</body>
</html>
```
