# Enterprise Authentication Package for Laravel

[![CI Tests](https://github.com/mixudev/package_LaravelAuthentication/actions/workflows/ci.yml/badge.svg)](https://github.com/mixudev/package_LaravelAuthentication/actions)
[![Latest Version](https://img.shields.io/github/v/tag/mixudev/package_LaravelAuthentication?label=version&color=blue)](https://github.com/mixudev/package_LaravelAuthentication/releases)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1%20%7C%20%5E8.2%20%7C%20%5E8.3%20%7C%20%5E8.4%20%7C%20%5E8.5-8892BF.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x%20%7C%2012.x%20%7C%2013.x-FF2D20.svg)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Arsitektur autentikasi enterprise siap produksi, modular, portable, dan aman (*secure-by-default*) untuk aplikasi **Laravel 10.x, 11.x, 12.x, dan 13.x**. 

Dirancang untuk monolit web, REST API, SPA, maupun arsitektur multi-tenant tanpa perlu modifikasi pada core code aplikasi host.

---

## Ringkasan Fitur Utama

| Modul & Fitur | Deskripsi Singkat |
| :--- | :--- |
| **Passkey (FIDO2 / WebAuthn)** | Login tanpa kata sandi via Touch ID, Face ID, Windows Hello, atau Security Key dengan dukungan autofill *Conditional UI*. |
| **Multi-Factor Auth (2FA / TOTP)** | Engine TOTP RFC 6238 mandiri (Google Auth, Authy, 1Password), kode pemulihan cadangan, dan fitur *Trust This Device* (30 hari). |
| **Optimasi Skala 10M+ Data** | *Smart Fast-Path Credential Resolver* dan *Composite Indexing* untuk performa pencarian < 1ms pada tabel puluhan juta baris. |
| **Active Session & Device Manager** | Deteksi platform/browser/IP, pencabutan sesi jarak jauh, dan *Logout All Other Devices* dengan konfirmasi password. |
| **Granular Rate Limiting** | Throttle counter independen per fitur (`login`, `registration`, `otp`, `2fa`, `forgot_password`) mencegah cross-feature DoS. |
| **Adaptive CAPTCHA & Bot Protection** | Cloudflare Turnstile, Google reCAPTCHA v2/v3, dan hCaptcha dengan threshold otomatis (muncul setelah $N$ kali gagal). |
| **OAuth Social Login** | Autentikasi instan Google dan GitHub dengan layout modern yang sejajar dan responsif. |
| **Passwordless OTP Email** | Kode verifikasi sekali pakai kriptografis dengan limit percobaan dan auto-expiry. |
| **Dual UI Template & Dark Mode** | Template `split` (2 kolom) & `card` (kartu minimalis) dengan transisi mulus *light*, *dark*, dan *auto* (OS match). |
| **100% REST API Ready** | Semua alur autentikasi tersedia dalam format JSON stateless (`/api/v1/auth/*`) dengan token Sanctum. |

---

## Panduan Instalasi Cepat

### 1. Instalasi via Composer

```bash
composer require mixudev/laravel-authentication
```

> [!NOTE]
> Untuk instalasi lokal / path repository (Monorepo), tambahkan repository path pada `composer.json` aplikasi Anda lalu jalankan `composer require mixudev/laravel-authentication:@dev`.

---

### 2. Setup Otomatis Satu Langkah *(Direkomendasikan)*

Jalankan perintah installer interaktif untuk mempublikasikan konfigurasi, migrasi, dan menyuntikkan class Tailwind CSS secara otomatis:

```bash
php artisan authentication:install
```

Atau jalankan manual jika ingin kontrol penuh:

```bash
# Publikasi konfigurasi & migrasi
php artisan vendor:publish --tag=authentication-config
php artisan vendor:publish --tag=authentication-migrations

# Jalankan migrasi database
php artisan migrate
```

---

## Panduan Update Versi Baru

Ketika package merilis pembaruan versi (bugfix, fitur baru, atau peningkatan performa), lakukan langkah berikut pada aplikasi Anda:

```bash
# 1. Tarik pembaruan package terbaru
composer update mixudev/laravel-authentication

# 2. Jalankan migrasi jika ada skema database baru
php artisan migrate

# 3. Bersihkan cache view & konfigurasi
php artisan view:clear
php artisan config:clear
```

> [!TIP]
> Jika Anda menggunakan **Mode Single-Folder Module** (`modules/Authentication/`), Anda dapat memperbarui modul dengan menjalankan `php artisan authentication:install-module --force`.

---

## Ringkasan Konfigurasi (`config/authentication.php`)

```php
return [
    // Model user aplikasi host (tanpa hardcode App\Models\User)
    'user_model' => \App\Models\User::class,
    'guard'      => 'web',

    // Fitur Kunci & Modul
    'features' => [
        // Passkey / WebAuthn FIDO2 Passwordless
        'passkey' => [
            'enabled'           => true,
            'rp_name'           => env('APP_NAME', 'Laravel'),
            'user_verification' => 'preferred',
            'timeout'           => 60000,
        ],

        // Multi-Factor Authentication (2FA TOTP)
        'two_factor' => [
            'enabled'      => true,
            'trust_device' => ['enabled' => true, 'duration_days' => 30],
        ],

        // Socialite OAuth (Google & GitHub)
        'social' => [
            'enabled' => true,
        ],

        // Proteksi Bot Adaptif
        'captcha' => [
            'enabled'                       => false,
            'driver'                        => 'turnstile', // 'turnstile', 'recaptcha_v2', 'hcaptcha'
            'trigger_after_failed_attempts' => 3,
        ],
    ],

    // Antarmuka & Tampilan UI
    'ui' => [
        'layout' => 'card', // 'card' atau 'split'
        'theme'  => 'auto', // 'light', 'dark', atau 'auto'
    ],
];
```

---

## Pusat Dokumentasi Lengkap (`docs/`)

Dokumentasi teknis mendalam telah disusun rapi di dalam direktori [`docs/`](docs/index.md):

* [**1. Panduan Memulai & Instalasi**](docs/getting-started.md)
* [**2. Mode Modul Mandiri (Single-Folder)**](docs/modular-installation.md)
* [**3. Penjelasan Lengkap Fitur & Saklar Konfigurasi**](docs/features.md)
* [**4. Panduan Kustomisasi Tampilan (Blade UI)**](docs/panduan-kustomisasi-view.md) / [English Guide](docs/views-customization.md)
* [**5. Strategi Autentikasi & Cara Membuat Custom Strategy**](docs/strategies-and-extending.md)
* [**6. Katalog Lengkap REST API JSON**](docs/api-reference.md)
* [**7. Keamanan & Mitigasi Ancaman Siber**](docs/security-and-best-practices.md)
* [**8. Daftar Kebutuhan Kunci API & Peta 49 Rute URL**](docs/prerequisites-and-checklist.md)
* [**9. Panduan Rilis & Standar Versioning**](docs/publishing-guide.md)

---

## Standar Keamanan & Kode

* **Zero Hardcoded Coupling**: Tidak ada ketergantungan langsung ke `App\Models\User`. Semua integrasi menggunakan dependency injection dan konfigurasi terpusat.
* **Sensitive Parameter Redaction**: Semua argumen password dan secret menggunakan atribut `#[SensitiveParameter]` PHP 8.2+ untuk mencegah kebocoran pada log dan *error stack trace*.
* **Fail-Closed by Design**: Setiap anomali strategi atau channel yang tidak dikenali melempar explicit typed exceptions (`AuthenticationException`).
* **Proteksi User Enumeration**: Timing serangan enumerasi dinormalisasi agar waktu respons kredensial valid dan tidak valid bernilai konsisten.
* **Pengujian Otomatis**: Dilengkapi dengan unit & feature tests PHPUnit serta static analysis PHPStan Level 8.

```bash
# Menjalankan unit & feature tests
vendor/bin/phpunit

# Menjalankan static analysis level 8
vendor/bin/phpstan analyse
```

---

## Lisensi

Package ini dirilis di bawah lisensi open-source [MIT License](LICENSE).
