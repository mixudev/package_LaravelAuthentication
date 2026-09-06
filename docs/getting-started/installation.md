# Instalasi & Setup Lengkap

Panduan end-to-end instalasi package `mixudev/laravel-authentication`, dari
`composer require` sampai aplikasi siap dipakai (publish, migrate, Tailwind,
verifikasi, dan events).

---

## 1. Persyaratan Sistem

| Kebutuhan | Versi |
| :--- | :--- |
| PHP | 8.1+ (rekomendasi 8.2+) |
| Laravel | 10.x, 11.x, 12.x, 13.x |
| Database | MySQL, MariaDB, PostgreSQL, SQLite |
| Composer | 2.x |
| Extensions PHP | `bcmath`, `openssl`, `json`, `mbstring` |

---

## 2. Instalasi via Composer

### Opsi A — Packagist (Produksi)

```bash
composer require mixudev/laravel-authentication
```

### Opsi B — Local Path Repository (Pengembangan / Monorepo)

Agar perubahan source package langsung terlihat tanpa menunggu Packagist,
tambahkan ke `composer.json` aplikasi host:

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

Lalu install:

```bash
composer require mixudev/laravel-authentication:@dev
```

> [!NOTE]
> **Windows**: gunakan `"symlink": false` jika ada masalah permission
> junction/symlink. Package akan di-copy ke `vendor/`; setelah setiap
> perubahan source, jalankan `composer reinstall mixudev/laravel-authentication`.

### Opsi C — Private Git / VCS Repository

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://git.example.com/team/laravel-authentication.git"
        }
    ]
}
```

```bash
composer require mixudev/laravel-authentication:^1.0
```

---

## 3. Setup Otomatis Satu Langkah (Direkomendasikan)

Package menyediakan installer interaktif yang otomatis:
- Mempublikasikan konfigurasi
- Mempublikasikan dan menjalankan migrasi
- Menyuntikkan class Tailwind CSS / `app.css` (dark mode variant)
- Menyiapkan route dan view

```bash
php artisan authentication:install
```

### Flag Opsional:

| Flag | Fungsi |
| :--- | :--- |
| `--views` | Juga publish Blade view ke `resources/views/vendor/authentication/` |
| `--force` | Timpa file konfigurasi/assets yang sudah ada |
| `--migrate` | Jalankan migrasi langsung tanpa prompt interaktif |

---

## 4. Setup Manual (Alternatif)

### 4.1 Publish Konfigurasi

```bash
php artisan vendor:publish --tag=authentication-config
```

Menghasilkan `config/authentication.php`. Konfigurasi ini berisi semua saklar
fitur, kebijakan keamanan, rate limit, dan nama tabel.

### 4.2 Publish & Jalankan Migrasi

```bash
php artisan vendor:publish --tag=authentication-migrations
php artisan migrate
```

Tabel yang dibuat:

| Tabel | Fungsi |
| :--- | :--- |
| `authentication_attempts` | Mencatat IP, identifier, status sukses/gagal (audit & lockout) |
| `authentication_login_histories` | Riwayat sesi login, user agent, channel |
| `authentication_password_histories` | Hash password lama (anti reuse) |
| `authentication_two_factors` | Secret TOTP terenkripsi & recovery codes ter-hash |
| `authentication_devices` | Fingerprint perangkat, nama, status trust |
| `authentication_passkeys` | Credential FIDO2 / WebAuthn |

### 4.3 Publish Blade Views (Opsional)

```bash
php artisan vendor:publish --tag=authentication-views
```

Menyalin template ke `resources/views/vendor/authentication/` untuk kustomisasi.

### 4.4 Konfigurasi Tailwind CSS

**Tailwind v4** (`resources/css/app.css`):

```css
@import "tailwindcss";
@source "../../vendor/mixudev/laravel-authentication/resources/views";
@custom-variant dark (&:where(.dark, .dark *));
```

**Tailwind v3** (`tailwind.config.js`):

```javascript
export default {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './vendor/mixudev/laravel-authentication/resources/views/**/*.blade.php',
    ],
}
```

---

## 5. Arahkan ke User Model Aplikasi

Di `config/authentication.php`:

```php
'user_model' => \App\Models\User::class,
```

Pastikan model `App\Models\User` meng-extend kelas dengan trait
`Illuminate\Foundation\Auth\User` atau setidaknya mengimplementasikan
`Illuminate\Contracts\Auth\Authenticatable` dan `MustVerifyEmail` (jika ingin
verifikasi email).

---

## 6. Daftarkan Kredensial API (Opsional)

Fitur tertentu butuh kredensial pihak ketiga. Isi di `.env` — **bukan** di config:

```env
# CAPTCHA (Turnstile / reCAPTCHA / hCaptcha)
AUTH_CAPTCHA_SITE_KEY=
AUTH_CAPTCHA_SECRET_KEY=

# Social Login
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=

# SMTP Email (OTP & notifikasi device baru)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=no-reply@example.com
```

Panduan lengkap mendapatkan tiap kredensial: [Prerequisites & API Keys](prerequisites.md).

---

## 7. Verifikasi Instalasi

### 7.1 Cek Service Provider Terdaftar

```bash
php artisan about
```

Pastikan `Vendor\LaravelAuthentication\Providers\AuthenticationServiceProvider`
muncul di daftar provider.

### 7.2 Cek Route Terdaftar

```bash
php artisan route:list | grep auth
```

Harus muncul route login, register, OTP, 2FA, sessions, passkey, dll.

### 7.3 Cek Endpoint Login

```bash
curl -X POST /api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"identifier": "user@example.com", "password": "secret"}'
```

Jika kredensial salah, harusnya mendapat response 401 `InvalidCredentialsException`
(generik — tidak membedakan user ada/tidak).

### 7.4 Test Manual di Browser

1. Buka `http://localhost:8000/login`
2. Login dengan kredensial salah → harus ditolak dengan pesan generik
3. Setelah 5x gagal → rate limit (`Too many attempts`)
4. Setelah threshold lockout → `Account is locked`
5. Login benar → redirect ke dashboard, session ter-regenerate

---

## 8. Langkah Berikutnya

Setelah instalasi sukses:

| Topik | Dokumen |
| :--- | :--- |
| Daftar lengkap fitur & konfigurasi | [Fitur & Konfigurasi](../features/overview.md) |
| Events yang bisa di-listen | [Events & Listeners](../development/events-and-listeners.md) |
| Endpoint REST API | [API Reference](../api/api-reference.md) |
| Kustomisasi tampilan | [Views Customization](../development/views-customization.md) |
| Buat controller sendiri | [Custom Controller Guide](../development/custom-controller-guide.md) |
| Keamanan & threat model | [Security Architecture](../security/architecture.md) |