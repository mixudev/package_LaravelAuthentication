# Panduan Lengkap Fitur & Konfigurasi Modular

Package `mixudev/laravel-authentication` dibangun dengan arsitektur **Fail-Closed & Fully Config-Driven**. Setiap fitur memiliki saklar independen di dalam `config/authentication.php` sehingga route, controller, middleware, dan view yang dinonaktifkan tidak akan dieksekusi atau membebani aplikasi host.

---

## 📑 Daftar Isi Fitur

1. [Multi-Factor Authentication (MFA / 2FA TOTP & Recovery Codes)](#1-multi-factor-authentication-mfa--2fa-totp--recovery-codes)
2. [Manajemen Sesi & Perangkat Aktif (Session & Device Management)](#2-manajemen-sesi--perangkat-aktif)
3. [Rate Limiting Granular per Fitur](#3-rate-limiting-granular-per-fitur)
4. [Notifikasi Login dari Perangkat Baru / Mencurigakan](#4-notifikasi-login-dari-perangkat-baru)
5. [Konfigurasi Nama Tabel Database & Migrasi Dinamis](#5-konfigurasi-nama-tabel-database--migrasi-dinamis)
6. [CAPTCHA & Proteksi Bot Adaptif](#6-captcha--proteksi-bot-adaptif)
7. [Konfirmasi Password untuk Aksi Sensitif (Re-Auth)](#7-konfirmasi-password-untuk-aksi-sensitif-re-auth)
8. [Pengiriman Email & OTP Asinkron (Queue)](#8-pengiriman-email--otp-asinkron-queue)
9. [Autentikasi OTP Tanpa Password (Passwordless OTP)](#9-autentikasi-otp-tanpa-password)
10. [Registrasi Pengguna Baru](#10-registrasi-pengguna-baru)
11. [Lupa & Reset Kata Sandi](#11-lupa--reset-kata-sandi)
12. [Social / OAuth Login (Google & GitHub)](#12-social--oauth-login-google--github)
13. [Kebijakan Password & Riwayat Password](#13-kebijakan-password--riwayat-password)
14. [Autentikasi Kunci Sandi (Passkeys / WebAuthn FIDO2)](#14-autentikasi-kunci-sandi-passkeys--webauthn-fido2)
15. [Optimasi Skala Besar (10M+ Data & Performa Tinggi)](#15-optimasi-skala-besar-10m-data--performa-tinggi)

---

## 1. Multi-Factor Authentication (MFA / 2FA TOTP & Recovery Codes)

Menyediakan autentikasi dua langkah berbasis standar RFC 6238 TOTP (kompatibel dengan Google Authenticator, Authy, Microsoft Authenticator, 1Password) dan kode pemulihan cadangan (*recovery codes*) sekali pakai.

### Konfigurasi di `config/authentication.php`:
```php
'features' => [
    'two_factor' => [
        'enabled'            => true,
        'digits'             => 6,    // Jumlah digit TOTP
        'period'             => 30,   // Durasi pembaruan kode (detik)
        'window'             => 1,    // Toleransi clock drift (+-1 interval = +-30s)
        'backup_codes_count' => 8,    // Jumlah kode cadangan pemulihan sekali pakai
        'issuer'             => env('APP_NAME', 'Laravel'),

        // Fitur Remember This Device untuk bypass 2FA pada perangkat tepercaya
        'trust_device' => [
            'enabled'       => true,
            'duration_days' => 30,    // Masa berlaku perangkat tepercaya (hari)
            'cookie_name'   => 'auth_trusted_device',
        ],
    ],
],
```

### Cara Kerja & Alur Pemakaian:
1. **Aktivasi 2FA oleh Pengguna**:
   - Web: Kunjungi rute `GET /auth/two-factor/setup` (nama rute: `two-factor.setup`).
   - Halaman akan menampilkan Secret Key Base32, URL URI `otpauth://`, dan 8 kode cadangan pemulihan terenkripsi.
   - Masukkan kode 6-digit dari aplikasi authenticator dan submit ke `POST /auth/two-factor/confirm` (nama rute: `two-factor.enable`).
2. **Saat Login**:
   - Jika user telah mengaktifkan 2FA dan perangkat belum tepercaya, alur login otomatis dialihkan ke `GET /two-factor-challenge` (Web) atau mengembalikan payload JSON `status: two_factor_required` (API).
   - Masukkan kode TOTP 6-digit atau salah satu kode pemulihan cadangan (format `ABCD-1234`).
   - Opsi *"Percayai perangkat ini selama 30 hari"* akan menyetel cookie terenkripsi sehingga user tidak perlu memasukkan kode 2FA lagi di perangkat tersebut selama 30 hari.
3. **Deaktivasi 2FA**:
   - Kirim request `DELETE /auth/two-factor/disable` dengan menyertakan parameter `password`.

---

## 2. Manajemen Sesi & Perangkat Aktif

Memungkinkan pengguna melihat daftar perangkat/browser yang sedang login ke akun mereka dan mencabut akses sesi secara selektif maupun serentak.

### Konfigurasi di `config/authentication.php`:
```php
'features' => [
    'session_management' => [
        'enabled'             => true,
        'max_active_sessions' => 5, // 0 = tidak dibatasi
    ],
],
```

### Cara Kerja & 3 Cara Integrasi ke Project:

#### A. Opsi 1: Halaman Standalone Siap Pakai
* **Web**: Buka `GET /auth/sessions` (nama rute: `auth.sessions.index`).
* Cocok untuk project yang ingin langsung memiliki halaman manajemen keamanan tanpa perlu merancang layout baru.

#### B. Opsi 2: Reusable Blade Component (Disisipkan ke Dashboard Kustom)
Cukup tempelkan tag komponen Blade ini di halaman dashboard atau profile aplikasi host Anda:
```blade
{{-- Halaman profile/dashboard host application --}}
<div class="max-w-4xl mx-auto py-6 space-y-6">
    <x-authentication::active-sessions />
</div>
```
Komponen ini otomatis merender daftar perangkat, badge *"Perangkat Ini"*, tombol cabut sesi individu, dan form konfirmasi password untuk keluar dari semua perangkat lain.

#### C. Opsi 3: Headless REST API (Inertia, Vue, React, Flutter)
* **List Sesi**: `GET /api/v1/auth/sessions` (Header `Authorization: Bearer <token>`).
* **Cabut 1 Sesi**: `DELETE /api/v1/auth/sessions/{id}`.
* **Cabut Semua Sesi Lain**: `POST /api/v1/auth/sessions/revoke-others` dengan payload JSON `{"password": "your-password"}`.

#### D. Opsi 4: Programmatic PHP Service (Untuk Custom Admin Dashboard / Widget)
```php
use Vendor\LaravelAuthentication\Services\SessionManagerService;
use Vendor\LaravelAuthentication\Services\AuthenticationAuditService;

$sessionService = app(SessionManagerService::class);
$auditService   = app(AuthenticationAuditService::class);

// Ringkasan metrik sesi aktif
$summary = $sessionService->getSummary(auth()->user());
// Output: ['total_sessions' => 2, 'current_device' => [...], 'other_sessions_count' => 1]

// Riwayat login terakhir user
$recentLogins = $auditService->getRecentLogins(auth()->user(), limit: 5);
```

---

## 3. Rate Limiting Granular per Fitur

Mencegah serangan brute-force, OTP-bombing, spam reset password, dan credential stuffing dengan memisahkan penghitung (*throttle counters*) secara independen untuk tiap fitur.

### Konfigurasi di `config/authentication.php`:
```php
'security' => [
    'rate_limits' => [
        'login' => [
            'enabled'       => true,
            'max_attempts'  => 5,
            'decay_minutes' => 1,
            'strategy'      => 'composite', // 'ip', 'identifier', atau 'composite'
        ],
        'registration' => [
            'enabled'       => true,
            'max_attempts'  => 5,
            'decay_minutes' => 60,
            'strategy'      => 'ip',
        ],
        'otp_request' => [
            'enabled'       => true,
            'max_attempts'  => 3,
            'decay_minutes' => 5,
            'strategy'      => 'composite',
        ],
        'otp_verify' => [
            'enabled'       => true,
            'max_attempts'  => 5,
            'decay_minutes' => 10,
            'strategy'      => 'composite',
        ],
        'forgot_password' => [
            'enabled'       => true,
            'max_attempts'  => 3,
            'decay_minutes' => 60,
            'strategy'      => 'composite',
        ],
        'two_factor' => [
            'enabled'       => true,
            'max_attempts'  => 5,
            'decay_minutes' => 5,
            'strategy'      => 'ip',
        ],
        'confirm_password' => [
            'enabled'       => true,
            'max_attempts'  => 5,
            'decay_minutes' => 1,
            'strategy'      => 'ip',
        ],
    ],
],
```

---

## 4. Notifikasi Login dari Perangkat Baru

Mendeteksi secara otomatis ketika pengguna login dari browser, perangkat, atau subnet IP yang belum pernah tercatat sebelumnya, lalu mengirimkan email peringatan keamanan instan.

### Konfigurasi di `config/authentication.php`:
```php
'security' => [
    'new_device_notification' => [
        'enabled'          => true,
        'mail_subject'     => null, // null = '{App Name} — Deteksi Masuk dari Perangkat Baru'
        'include_location' => true,
    ],
],
```

### Informasi dalam Email:
- Sistem Operasi & Browser (mis. *Google Chrome on Windows 10/11*).
- Alamat IP & Perkiraan Lokasi (berdasarkan Cloudflare / GeoIP headers).
- Waktu kejadian.
- Tombol langsung: **"Amankan Akun & Cabut Sesi"** yang mengarahkan user ke halaman manajemen sesi.

---

## 5. Konfigurasi Nama Tabel Database & Migrasi Dinamis

Pengguna package dapat mengkustomisasi nama tabel database tanpa perlu mem-fork repository atau merusak relasi model.

### Konfigurasi di `config/authentication.php`:
```php
'database' => [
    'load_migrations' => true, // Set false jika mengelola migrasi aplikasi sendiri

    'table_names' => [
        'attempts'           => 'authentication_attempts',
        'login_histories'    => 'authentication_login_histories',
        'password_histories' => 'authentication_password_histories',
        'two_factor'         => 'authentication_two_factors',
        'devices'            => 'authentication_devices',
        'sessions'           => 'authentication_sessions',
    ],
],
```

Seluruh model Eloquent package (`AuthenticationAttempt`, `LoginHistory`, `PasswordHistory`, `TwoFactorAuthentication`, `AuthenticationDevice`) dan file migrasi membaca nama tabel secara dinamis melalui helper `AuthenticationConfig::tableName('<key>')`.

---

## 6. CAPTCHA & Proteksi Bot Adaptif

Mendukung proteksi bot multi-driver dengan konsep **Adaptive Threshold**: pengguna normal dapat login dengan mulus tanpa CAPTCHA, namun jika terjadi $N$ kali percobaan gagal berturut-turut dari IP/akun tersebut, CAPTCHA wajib diisi sebelum request diproses.

### Konfigurasi di `config/authentication.php`:
```php
'security' => [
    'captcha' => [
        'enabled'                       => true,
        'driver'                        => 'turnstile', // 'turnstile', 'recaptcha_v2', 'recaptcha_v3', 'hcaptcha'
        'trigger_after_failed_attempts' => 3,           // 0 = selalu wajib, >0 = adaptif setelah N kali gagal
        'site_key'                      => env('AUTH_CAPTCHA_SITE_KEY', ''),
        'secret_key'                    => env('AUTH_CAPTCHA_SECRET_KEY', ''),
    ],
],
```

### Pemakaian di Form Blade Kustom:
Gunakan service helper atau render langsung widget CAPTCHA:
```blade
@inject('captcha', 'Vendor\LaravelAuthentication\Services\CaptchaService')

@if ($captcha->shouldShowCaptcha(old('identifier'), request()->ip()))
    {!! $captcha->renderWidget() !!}
@endif
```

---

## 7. Konfirmasi Password untuk Aksi Sensitif (Re-Auth)

Memproteksi halaman atau aksi sensitif (seperti mengubah email, melihat API key, mengelola 2FA, atau transfer dana) dengan mewajibkan user memasukkan ulang kata sandi jika belum dikonfirmasi dalam kurun waktu tertentu.

### Konfigurasi di `config/authentication.php`:
```php
'features' => [
    'confirm_password' => [
        'enabled'         => true,
        'timeout_seconds' => 900, // 15 menit
    ],
],
```

### Cara Memasang pada Rute Aplikasi Host:
Cukup tambahkan middleware `password.confirm` atau `\Vendor\LaravelAuthentication\Http\Middleware\RequirePasswordConfirmation::class`:
```php
Route::middleware(['auth', 'password.confirm'])->group(function () {
    Route::get('/settings/security', [SecurityController::class, 'index']);
    Route::post('/settings/api-keys', [ApiKeyController::class, 'generate']);
});
```

---

## 8. Pengiriman Email & OTP Asinkron (Queue)

Mencegah request login, register, atau request OTP terblokir/lambat saat koneksi SMTP mail server mengalami latensi tinggi.

### Konfigurasi di `config/authentication.php`:
```php
'mail' => [
    'queue'            => true, // Set true untuk dispatch email lewat background worker queue
    'queue_connection' => null, // null = mengikuti default queue connection Laravel
    'queue_name'       => 'auth-emails',
],
```

Saat `mail.queue => true`, mailable `OtpMail` dan `NewDeviceLoginMail` otomatis dikirim via antrean worker `php artisan queue:work --queue=auth-emails`.

---

## 9. Autentikasi OTP Tanpa Password

Memungkinkan login instan menggunakan kode sekali pakai 6-digit numerik yang dikirim ke email user.

### Konfigurasi di `config/authentication.php`:
```php
'features' => [
    'otp' => [
        'enabled'          => true,
        'length'           => 6,
        'expiry_minutes'   => 10,
        'max_attempts'     => 3,
        'throttle_seconds' => 60,
        'type'             => 'numeric', // 'numeric' atau 'alphanumeric'
        'send_email'       => true,
        'email_subject'    => null,
        'email_view'       => 'authentication::emails.otp',
    ],
],
```

### Rute Web & API:
- **Minta Kode OTP**: `GET /otp/login`, `POST /otp/send` (API: `POST /api/v1/auth/otp/send`)
- **Verifikasi Kode OTP**: `GET /otp/verify`, `POST /otp/verify` (API: `POST /api/v1/auth/otp/verify`)

---

## 10. Registrasi Pengguna Baru

Menyediakan alur pendaftaran user baru baik melalui Web form maupun API JSON.

### Konfigurasi di `config/authentication.php`:
```php
'features' => [
    'registration' => [
        'enabled'                => true,
        'auto_login_on_register' => true,
        'require_email_verify'   => false,
    ],
],
```

### Rute Web & API:
- Web: `GET /register`, `POST /register`
- API: `POST /api/v1/auth/register` (Payload: `name`, `email`, `password`, `password_confirmation`)

---

## 11. Lupa & Reset Kata Sandi

Alur pemulihan kata sandi mandiri yang aman dari enumerasi akun (*user enumeration mitigation*).

### Konfigurasi di `config/authentication.php`:
```php
'features' => [
    'forgot_password' => [
        'enabled' => true,
    ],
],
```

### Rute Web & API:
- **Request Link Reset**: `GET /forgot-password`, `POST /forgot-password` (API: `POST /api/v1/auth/forgot-password`)
- **Eksekusi Reset Password**: `GET /reset-password/{token}`, `POST /reset-password` (API: `POST /api/v1/auth/reset-password`)

---

## 12. Social / OAuth Login (Google & GitHub)

Integrasi login sosial menggunakan Laravel Socialite dengan provisi user otomatis.

### Konfigurasi di `config/authentication.php`:
```php
'features' => [
    'social' => [
        'enabled'       => true,
        'auto_register' => true,
        'providers'     => [
            'google' => [
                'enabled'       => true,
                'client_id'     => env('GOOGLE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                'redirect'      => env('APP_URL') . '/auth/google/callback',
                'scopes'        => ['openid', 'profile', 'email'],
            ],
            'github' => [
                'enabled'       => true,
                'client_id'     => env('GITHUB_CLIENT_ID'),
                'client_secret' => env('GITHUB_CLIENT_SECRET'),
                'redirect'      => env('APP_URL') . '/auth/github/callback',
                'scopes'        => ['user:email', 'read:user'],
            ],
        ],
    ],
],
```

---

## 13. Kebijakan Password & Riwayat Password

Menetapkan standar kompleksitas password, pencegahan pemakaian ulang password lama, dan hashing otomatis ke algoritma terbaru.

### Konfigurasi di `config/authentication.php`:
```php
'password' => [
    'rehash' => true, // Rehash otomatis ke Argon2id/Bcrypt terbaru saat login

    'validation_rules' => [
        'min_length'         => 8,
        'require_uppercase'  => true,
        'require_lowercase'  => true,
        'require_mixed_case' => true,
        'require_numbers'    => true,
        'require_symbols'    => true,
        'symbols_charset'    => '@$!%*#?&_-+=[]{}|;:,.<>',
        'uncompromised'      => false,
    ],

    'history' => [
        'enabled'  => true, // Cegah pemakaian ulang password lama
        'remember' => 5,    // 5 password terakhir tidak boleh dipakai ulang
    ],
],
```

---

## 14. Autentikasi Kunci Sandi (Passkeys / WebAuthn FIDO2)

Autentikasi biometrik modern tanpa kata sandi menggunakan sensor perangkat (Touch ID, Face ID, Windows Hello, atau Security Key USB/NFC FIDO2) berbasis standar W3C WebAuthn.

### Konfigurasi di `config/authentication.php`:
```php
'features' => [
    'passkey' => [
        'enabled'           => true,
        'rp_name'           => env('APP_NAME', 'Laravel'),
        'rp_id'             => null, // null = auto detect domain/host
        'user_verification' => 'preferred', // 'required', 'preferred', 'discouraged'
        'timeout'           => 60000, // 60 detik
    ],
],
```

### Cara Kerja:
1. **Pendaftaran Passkey**:
   - User yang sudah login membuka halaman *Pusat Keamanan* (`/auth/sessions`).
   - Klik *"Daftarkan Passkey Baru"*. Browser akan memanggil `navigator.credentials.create()` untuk menggenerasi pasangan kunci kriptografis di hardware enklaf perangkat.
   - Public key dan credential ID disimpan di database (`authentication_passkeys`), sedangkan private key tetap aman di hardware perangkat user dan tidak pernah dikirim ke server.
2. **Login dengan Passkey**:
   - Klik tombol *"Login with Passkey"* di halaman `/login`.
   - Browser meminta verifikasi sidik jari / wajah / PIN.
   - Server memvalidasi signature kriptografis dan secara otomatis mengautentikasi sesi web atau membuat token API.
3. **Conditional UI / Autofill**:
   - Field input otomatis mendeteksi passkey yang tersimpan di browser (`autocomplete="webauthn"`).

---

## 15. Optimasi Skala Besar (10M+ Data & Performa Tinggi)

Didesain untuk menangani beban data puluhan juta baris dengan latensi kueri rendah:

### 1. Smart Fast-Path Credential Resolver
Pada saat proses login, sistem mendeteksi tipe input:
- Jika input berformat email: Query langsung membidik indeks kolom `email` tunggal ($O(\log N)$).
- Jika input berformat username: Query langsung membidik indeks kolom `username`.
- Menghindari kueri `OR` yang lambat atau *table scan* pada tabel dengan jutaan baris data.

### 2. Composite Database Indexing
Semua tabel log dan sesi dilengkapi composite index:
- `authentication_attempts`: `idx_attempts_id_time`, `idx_attempts_ip_time`, `idx_attempts_status_time`.
- `authentication_login_histories`: `idx_histories_user_login`, `idx_histories_user_logout`.
- `authentication_devices`: `idx_devices_user_last_seen`.
- `authentication_passkeys`: Indeks unik `credential_id` dan composite `['user_id', 'created_at']`.

