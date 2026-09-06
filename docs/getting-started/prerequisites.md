# Prasyarat & API Keys

Daftar lengkap kebutuhan sistem autentikasi: layanan mana yang butuh
pendaftaran API pihak ketiga vs yang berjalan mandiri (offline), cara
mendapatkan kredensial, dan di mana menaruhnya.

---

## 1. Matriks Kebutuhan Layanan

| Fitur | Butuh API Luar? | Biaya | Tempat Kredensial |
| :--- | :---: | :---: | :--- |
| MFA / 2FA Authenticator (Google Auth / Authy) | TIDAK — berjalan 100% offline di server | Gratis | `config/authentication.php` (`features.two_factor`) |
| Manajemen Sesi & Perangkat (`/auth/sessions`) | TIDAK | Gratis | `config/authentication.php` (`features.session_management`) |
| Konfirmasi Password (`password.confirm`) | TIDAK | Gratis | `config/authentication.php` (`features.confirm_password`) |
| Rate Limiting Granular | TIDAK — pakai cache Laravel | Gratis | `config/authentication.php` (`security.rate_limits`) |
| CAPTCHA Cloudflare Turnstile (rekomendasi) | YA | Gratis | `.env`: `AUTH_CAPTCHA_SITE_KEY`, `AUTH_CAPTCHA_SECRET_KEY` |
| CAPTCHA Google reCAPTCHA v2/v3 | YA | Gratis | `.env`: `AUTH_CAPTCHA_SITE_KEY`, `AUTH_CAPTCHA_SECRET_KEY` |
| CAPTCHA hCaptcha | YA | Gratis | `.env`: `AUTH_CAPTCHA_SITE_KEY`, `AUTH_CAPTCHA_SECRET_KEY` |
| Social Login Google OAuth | YA | Gratis | `.env`: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` |
| Social Login GitHub OAuth | YA | Gratis | `.env`: `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` |
| Email (OTP & alert device baru) | YA | Sesuai SMTP | `.env`: `MAIL_*` |

> **Aturan**: saklar fitur (`enabled`, threshold, policy) hidup di
> `config/authentication.php`. `.env` HANYA untuk kredensial/secret pihak ketiga.

---

## 2. Cara Mendapatkan API Keys

### 2.1 Cloudflare Turnstile CAPTCHA (Rekomendasi Utama)

1. Buka [dash.cloudflare.com](https://dash.cloudflare.com) → login/daftar.
2. Sidebar → **Turnstile** → **Add Site**.
3. Isi **Site Name**, **Domain** (`localhost` untuk testing lokal), widget mode **Managed**.
4. **Create** → salin **Site Key** & **Secret Key**.
5. Tempel ke `.env`:
   ```env
   AUTH_CAPTCHA_SITE_KEY=0x4AAAAAA...
   AUTH_CAPTCHA_SECRET_KEY=0x4AAAAAA...
   ```
6. Pastikan di `config/authentication.php`:
   ```php
   'security' => [
       'captcha' => [
           'enabled'                       => true,
           'driver'                        => 'turnstile',
           'trigger_after_failed_attempts' => 3,
       ],
   ],
   ```

### 2.2 Google OAuth 2.0 (Login via Google)

1. Buka [console.cloud.google.com](https://console.cloud.google.com/).
2. Buat Project → **APIs & Services** → **Credentials**.
3. **Create Credentials** → **OAuth Client ID** (tipe *Web Application*).
4. **Authorized redirect URIs**:
   `http://localhost:8000/auth/google/callback`
5. Salin Client ID & Secret ke `.env`:
   ```env
   GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=GOCSPX-your-client-secret
   ```

### 2.3 GitHub OAuth (Login via GitHub)

1. Buka [github.com/settings/developers](https://github.com/settings/developers) → **OAuth Apps** → **New OAuth App**.
2. Isi **Application Name**, **Homepage URL** (`http://localhost:8000`),
   **Authorization callback URL**: `http://localhost:8000/auth/github/callback`.
3. **Register application** → **Generate a new client secret**.
4. Tempel ke `.env`:
   ```env
   GITHUB_CLIENT_ID=your-github-client-id
   GITHUB_CLIENT_SECRET=your-github-client-secret
   ```

### 2.4 SMTP Email (OTP & Alert)

Contoh Mailtrap untuk testing lokal:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Kirim email via background queue:

```php
// config/authentication.php
'mail' => [
    'queue' => true,
    'queue_name' => 'auth-emails',
],
```

```bash
php artisan queue:work --queue=auth-emails
```

---

## 3. Konfigurasi Awal yang Direkomendasikan

Setelah kredensial siap, pastikan setelan minimal ini di `config/authentication.php`:

```php
// 1. Model user aplikasi
'user_model' => \App\Models\User::class,

// 2. Akses route API
'routes' => [
    'api' => [
        'enabled'    => true,   // aktifkan jika pakai SPA / mobile
        'auth_middleware' => ['auth:sanctum'],
    ],
],

// 3. Fitur inti
'features' => [
    'two_factor'      => ['enabled' => true],
    'session_management' => ['enabled' => true],
    'confirm_password'   => ['enabled' => true],
    'registration'       => ['enabled' => true, 'auto_login_on_register' => true],
    'forgot_password'    => ['enabled' => true],
],
```

---

## 4. Checklist Sebelum Produksi

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `SESSION_SECURE_COOKIE=true` (dipaksa default oleh package jika belum diset)
- [ ] `SESSION_DRIVER=database` atau `redis` (bukan `file`) — wajib untuk manajemen sesi jarak jauh
- [ ] HTTPS aktif (Laravel Forge / Vercel / load balancer)
- [ ] CAPTCHA site/secret key terisi
- [ ] SMTP terkonfigurasi & teruji kirim
- [ ] `config:cache` + `route:cache` dijalankan
- [ ] Backup recovery code 2FA user admin tersimpan aman