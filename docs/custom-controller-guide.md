# Custom Controller & View Guide

Panduan lengkap menggunakan controller dan view custom di project Laravel kamu, sambil tetap membiarkan package `mixudev/laravel-authentication` mengurus seluruh logika autentikasi secara aman.

---

## Daftar Isi

1. [Konsep Dasar](#1-konsep-dasar)
2. [Setup Awal di Project Laravel](#2-setup-awal-di-project-laravel)
3. [Login — Web Controller](#3-login--web-controller)
4. [Logout](#4-logout)
5. [Registrasi Custom](#5-registrasi-custom)
6. [Two-Factor Authentication 2FA](#6-two-factor-authentication-2fa)
7. [Ganti Password](#7-ganti-password)
8. [Login via API JSON Sanctum](#8-login-via-api-json--sanctum)
9. [Integrasi Role System](#9-integrasi-role-system)
10. [Mendengarkan Events Package](#10-mendengarkan-events-package)
11. [Referensi DTO dan Exception](#11-referensi-dto--exception)

---

## 1. Konsep Dasar

Package ini **hanya mengurus autentikasi** bukan otorisasi. Kamu tetap bebas membuat controller, view, dan route sendiri. Yang perlu kamu lakukan hanyalah:

1. **Inject service** dari package via constructor injection
2. **Bangun DTO** (`LoginData`, `AuthenticationContext`) dari request
3. **Panggil service** - package akan mengurus rate limit, lockout, session, audit, device detection, dll.
4. **Handle exception** untuk response yang sesuai

> **Penting:** Jangan pernah memanggil `Auth::login()` secara manual setelah `authenticate()`.
> Package sudah mengurus ini sepenuhnya di dalam pipeline-nya.

---

## 2. Setup Awal di Project Laravel

### Install dan Publish Config

```bash
composer require mixudev/laravel-authentication
php artisan vendor:publish --tag=authentication-config
php artisan vendor:publish --tag=authentication-migrations
php artisan migrate
```

### Arahkan ke User Model Kamu

Di `config/authentication.php`:

```php
'user_model' => \App\Models\User::class,
```

### Nonaktifkan Route Bawaan Package (jika pakai controller custom)

```php
// config/authentication.php
'routes' => [
    'web' => ['enabled' => false],
    'api' => ['enabled' => false],
],
```

---

## 3. Login — Web Controller

### View

```html
<!-- resources/views/auth/login.blade.php -->
<form method="POST" action="{{ route('login') }}">
    @csrf
    <input type="text" name="identifier" value="{{ old('identifier') }}" placeholder="Email atau Username" required>
    @error('identifier') <span>{{ $message }}</span> @enderror

    <input type="password" name="password" required>

    <label><input type="checkbox" name="remember"> Ingat saya</label>

    <button type="submit">Masuk</button>
</form>
```

### Controller

```php
<?php
// app/Http/Controllers/Auth/LoginController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\TwoFactorChallengeRequiredException;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuthenticationServiceInterface $authService
    ) {}

    public function create(): \Illuminate\View\View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password'   => ['required', 'string'],
        ]);

        // 1. Buat DTO dari input
        $loginData = LoginData::fromArray([
            'identifier' => $request->input('identifier'),
            'password'   => $request->input('password'),
            'remember'   => $request->boolean('remember'),
            // 'strategy' => 'email', // opsional, default dari config
        ]);

        // 2. Buat context dari request (IP, user agent, channel, guard)
        $context = AuthenticationContext::fromRequest($request);

        try {
            // 3. Panggil package - semua pipeline diurus otomatis:
            //    ✓ Rate limiting (per IP + identifier)
            //    ✓ Brute force lockout check
            //    ✓ Credential verification + auto-rehash
            //    ✓ 2FA check (throw jika diperlukan)
            //    ✓ Session login + session()->regenerate()
            //    ✓ New device detection + email notif
            //    ✓ Security audit log
            $this->authService->authenticate($loginData, $context);

            return redirect()->intended(route('dashboard'));

        } catch (TwoFactorChallengeRequiredException $e) {
            // Credential benar, tapi user harus input kode 2FA
            // User ID sudah disimpan di session: session('auth.2fa.user_id')
            return redirect()->route('auth.2fa.challenge');

        } catch (AuthenticationThrottledException $e) {
            return back()
                ->withInput($request->only('identifier'))
                ->withErrors(['identifier' => "Terlalu banyak percobaan. Coba lagi dalam {$e->secondsRemaining} detik."]);

        } catch (AccountLockedException $e) {
            return back()
                ->withInput($request->only('identifier'))
                ->withErrors(['identifier' => "Akun dikunci selama {$e->lockoutMinutes} menit."]);

        } catch (InvalidCredentialsException $e) {
            // Pesan SENGAJA generik untuk mencegah user enumeration
            return back()
                ->withInput($request->only('identifier'))
                ->withErrors(['identifier' => 'Email/username atau password salah.']);
        }
    }
}
```

### Route

```php
// routes/web.php
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
```

---

## 4. Logout

```php
public function destroy(Request $request): RedirectResponse
{
    $context = AuthenticationContext::fromRequest($request);

    // Package akan: invalidate session, revoke token (API), dispatch event, catat audit
    $this->authService->logout($context);

    return redirect('/login');
}
```

---

## 5. Registrasi Custom

```php
<?php
// app/Http/Controllers/Auth/RegisterController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Vendor\LaravelAuthentication\Contracts\RegistrationServiceInterface;
use Vendor\LaravelAuthentication\DTO\RegisterData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;

class RegisterController extends Controller
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService
    ) {}

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $registerData = RegisterData::fromArray($request->only('name', 'email', 'password'));
        $context = AuthenticationContext::fromRequest($request);

        try {
            // Package akan: hash password, simpan ke DB, catat password history,
            // dispatch UserRegistered event, catat audit log
            $user = $this->registrationService->register($registerData, $context);

            // Assign role default jika pakai Spatie
            // $user->assignRole('user');

            auth()->login($user);
            return redirect()->route('dashboard');

        } catch (AuthenticationException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }
    }
}
```

---

## 6. Two-Factor Authentication 2FA

### Flow 2FA

```
Login -> TwoFactorChallengeRequiredException -> Halaman Challenge -> Input Kode -> Login Sukses
```

### Controller

```php
<?php
// app/Http/Controllers/Auth/TwoFactorController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Vendor\LaravelAuthentication\Services\TwoFactor\TwoFactorService;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService
    ) {}

    /** Halaman input kode 2FA setelah throw TwoFactorChallengeRequiredException */
    public function challenge(): \Illuminate\View\View
    {
        abort_unless(session()->has('auth.2fa.user_id'), 403);
        return view('auth.2fa-challenge');
    }

    /** Verifikasi kode TOTP 6-digit ATAU recovery code */
    public function verify(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $userId = session('auth.2fa.user_id');
        abort_unless($userId, 403);

        $userModel = config('authentication.user_model');
        $user = $userModel::findOrFail($userId);

        $verified = $this->twoFactorService->verifyChallenge($user, $request->input('code'));

        if (!$verified) {
            return back()->withErrors(['code' => 'Kode tidak valid atau sudah kadaluarsa.']);
        }

        session()->forget(['auth.2fa.user_id', 'auth.2fa.remember']);
        auth()->login($user);
        return redirect()->intended(route('dashboard'));
    }

    /** Setup 2FA - tampilkan QR code dan recovery codes */
    public function setup(): \Illuminate\View\View
    {
        // Mengembalikan: secret, otpauth_url, qr_code_svg, recovery_codes (plaintext, tampil 1x)
        $setup = $this->twoFactorService->setup(auth()->user());
        return view('auth.2fa-setup', compact('setup'));
    }

    /** Konfirmasi setup dengan kode TOTP pertama */
    public function confirm(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $confirmed = $this->twoFactorService->confirm(auth()->user(), $request->input('code'));

        if (!$confirmed) {
            return back()->withErrors(['code' => 'Kode tidak cocok. Pastikan waktu perangkat akurat.']);
        }

        return redirect()->route('profile')->with('status', '2FA berhasil diaktifkan.');
    }

    /** Nonaktifkan 2FA (perlu konfirmasi password) */
    public function disable(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        try {
            $this->twoFactorService->disable(auth()->user(), $request->input('password'));
            return redirect()->route('profile')->with('status', '2FA dinonaktifkan.');
        } catch (InvalidCredentialsException $e) {
            return back()->withErrors(['password' => 'Password tidak sesuai.']);
        }
    }
}
```

---

## 7. Ganti Password

```php
<?php
// app/Http/Controllers/Auth/PasswordController.php

use Vendor\LaravelAuthentication\Services\Password\PasswordService;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;

class PasswordController extends Controller
{
    public function __construct(private readonly PasswordService $passwordService) {}

    public function update(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            // Package akan: cek history, hash, update DB, catat history
            $this->passwordService->updatePassword(auth()->user(), $request->input('password'));
            return back()->with('status', 'Password berhasil diperbarui.');

        } catch (AuthenticationException $e) {
            // Contoh: "You cannot reuse any of your last 5 passwords."
            return back()->withErrors(['password' => $e->getMessage()]);
        }
    }
}
```

---

## 8. Login via API JSON Sanctum

Satu-satunya perbedaan dari web: set `channel` ke `API`.

```php
<?php
// app/Http/Controllers/Api/Auth/ApiLoginController.php

namespace App\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;

class ApiLoginController extends Controller
{
    public function __construct(
        private readonly AuthenticationServiceInterface $authService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string'],
            'password'   => ['required', 'string'],
        ]);

        $loginData = LoginData::fromArray($request->only('identifier', 'password'));

        // Set channel API - package buat Sanctum token, bukan session
        $context = AuthenticationContext::fromRequest(
            $request,
            guard: 'api',
            channel: AuthenticationChannel::API
        );

        try {
            $result = $this->authService->authenticate($loginData, $context);

            return response()->json([
                'success' => true,
                'token'   => $result->token, // Sanctum token
                'user'    => $result->user,
            ]);

        } catch (InvalidCredentialsException) {
            return response()->json(['message' => 'Kredensial tidak valid.'], 401);

        } catch (AuthenticationThrottledException $e) {
            return response()->json([
                'message'     => 'Terlalu banyak percobaan.',
                'retry_after' => $e->secondsRemaining,
            ], 429);

        } catch (AccountLockedException $e) {
            return response()->json(['message' => 'Akun dikunci.'], 423);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $context = AuthenticationContext::fromRequest(
            $request,
            guard: 'api',
            channel: AuthenticationChannel::API
        );
        $this->authService->logout($context); // Revoke Sanctum token saat ini
        return response()->json(['success' => true]);
    }
}
```

---

## 9. Integrasi Role System

Package ini **tidak menyentuh otorisasi** sama sekali. Role system apapun dapat dipakai berdampingan tanpa konflik.

### 9.1 Spatie Permission

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

```php
// app/Models/User.php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles; // tidak perlu mengubah apapun terkait package autentikasi
}
```

Setelah login berhasil:

```php
$result = $this->authService->authenticate($loginData, $context);

// Redirect berdasarkan role
return match (true) {
    auth()->user()->hasRole('admin')   => redirect('/admin/dashboard'),
    auth()->user()->hasRole('manager') => redirect('/manager'),
    default                            => redirect('/dashboard'),
};
```

Middleware route:

```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
```

Assign role saat registrasi:

```php
$user = $this->registrationService->register($registerData, $context);
$user->assignRole('user');
```

### 9.2 Custom Role (kolom di tabel users)

```php
// app/Models/User.php
class User extends Authenticatable
{
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
```

```php
// Di controller setelah login
$this->authService->authenticate($loginData, $context);

return auth()->user()->isAdmin()
    ? redirect('/admin')
    : redirect('/dashboard');
```

### 9.3 Tabel yang Dikelola Masing-masing

| Package | Tabel |
|---|---|
| `mixudev/laravel-authentication` | `authentication_attempts`, `authentication_devices`, `authentication_login_history`, `authentication_two_factors` |
| `spatie/laravel-permission` | `roles`, `permissions`, `model_has_roles`, `role_has_permissions` |
| Custom role | `users.role` / tabel kustom milikmu |

Tidak ada konflik. Keduanya berjalan di layer yang sepenuhnya berbeda.

---

## 10. Mendengarkan Events Package

```php
// app/Providers/EventServiceProvider.php
use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Vendor\LaravelAuthentication\Events\UserRegistered;
use Vendor\LaravelAuthentication\Events\NewDeviceLoginDetected;

protected $listen = [
    LoginSucceeded::class         => [App\Listeners\RedirectByRole::class],
    UserRegistered::class         => [App\Listeners\SendWelcomeEmail::class],
    NewDeviceLoginDetected::class => [App\Listeners\NotifySecurityTeam::class],
];
```

### Semua Events yang Tersedia

| Event Class | Kapan Dilempar | Properties |
|---|---|---|
| `LoginAttempted` | Setiap percobaan login | `identifier`, `context`, `strategy` |
| `LoginSucceeded` | Login berhasil | `user`, `context`, `strategy` |
| `LoginFailed` | Credential salah | `identifier`, `context`, `reason`, `user` |
| `AccountLocked` | Akun dikunci | `user`, `context` |
| `LogoutPerformed` | Logout | `user`, `context` |
| `UserRegistered` | Registrasi baru | `user`, `context` |
| `NewDeviceLoginDetected` | Device baru terdeteksi | `user`, `device`, `context` |
| `PasswordChanged` | Password diubah | `user` |
| `PasswordResetRequested` | Request reset password | `user`, `context` |
| `PasswordResetCompleted` | Reset selesai | `user` |

Semua events ada di namespace `Vendor\LaravelAuthentication\Events\`.

---

## 11. Referensi DTO dan Exception

### `LoginData`

```php
use Vendor\LaravelAuthentication\DTO\LoginData;

$data = LoginData::fromArray([
    'identifier' => 'user@example.com', // email / username / dll
    'password'   => 'secret',
    'remember'   => true,
    'strategy'   => 'email', // opsional - null = gunakan default dari config
]);
```

### `AuthenticationContext`

```php
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;

$context = AuthenticationContext::fromRequest($request); // Web (auto-detect)

$context = AuthenticationContext::fromRequest(
    $request,
    guard: 'api',
    channel: AuthenticationChannel::API // WEB | API | CLI | WEBHOOK
);
```

### `AuthenticationResult`

```php
$result = $this->authService->authenticate($loginData, $context);

$result->isSuccess();  // bool
$result->user;         // Authenticatable
$result->token;        // string|null (hanya API/Sanctum)
$result->status;       // AuthenticationStatus enum
$result->metadata;     // array ['strategy' => 'email', 'channel' => 'web']
```

### Exception Reference

| Exception | Kapan | Property |
|---|---|---|
| `InvalidCredentialsException` | Credential salah / user tidak ada | - |
| `AuthenticationThrottledException` | Rate limit tercapai | `$e->secondsRemaining` |
| `AccountLockedException` | Akun dikunci | `$e->lockoutMinutes` |
| `TwoFactorChallengeRequiredException` | Perlu verifikasi 2FA | `$e->user` |
| `AuthenticationException` | Base exception semua error auth | `$e->getMessage()` |
| `InvalidStrategyException` | Strategy tidak terdaftar | - |

Semua exceptions ada di namespace `Vendor\LaravelAuthentication\Exceptions\`.

### Services yang Dapat Di-inject

| Interface / Class | Kegunaan |
|---|---|
| `AuthenticationServiceInterface` | `authenticate()`, `logout()`, `isEnabled()` |
| `RegistrationServiceInterface` | `register()`, `isEnabled()` |
| `TwoFactorService` | `setup()`, `confirm()`, `verifyChallenge()`, `disable()` |
| `PasswordService` | `updatePassword()`, `hashPassword()` |

---

## Ringkasan

```
Controller Kamu
  1. LoginData::fromArray([identifier, password, remember])
  2. AuthenticationContext::fromRequest($request)
  3. $authService->authenticate($data, $context)
     catch TwoFactorChallengeRequiredException -> redirect ke 2FA
     catch AuthenticationThrottledException   -> tampilkan pesan throttle
     catch AccountLockedException             -> tampilkan pesan kunci
     catch InvalidCredentialsException        -> tampilkan pesan generik

  Setelah sukses:
  auth()->user()->hasRole('admin')  // Spatie Permission
  auth()->user()->role === 'admin'  // Custom Role

Package mengurus:              Kamu mengurus:
  Rate limiting                  View / form HTML
  Brute force lockout            Redirect logic
  Credential verification        Role / permission check
  Password rehash                Business logic pasca login
  Session login + regenerate
  2FA pipeline
  Device detection
  Security audit log
```
