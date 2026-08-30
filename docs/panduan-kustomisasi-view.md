# Panduan Lengkap Kustomisasi Tampilan (Custom Auth Views)

Package **`mixudev/laravel-authentication`** dirancang sangat fleksibel dan tidak mengikat developer ke tampilan tertentu. Anda memiliki kebebasan penuh untuk:
1. **Mengatur Mode Tema** (`light`, `dark`, atau `auto` mengikuti sistem operasi).
2. **Mengganti Template Layout Bawaan** (`split` 2-kolom atau `card` terpusat).
3. **Memodifikasi Komponen & View Bawaan** via `vendor:publish`.
4. **Membuat Tampilan Sendiri dari Nol (*Bring Your Own UI*)** dan mengarahkannya lewat `config/authentication.php` atau `.env`.

---

## 🎨 Dukungan Penuh Mode Tema: Light, Dark, & Auto

Package dilengkapi dengan *Theme Engine* pintar tanpa kedip (*flicker-free*) yang dapat diatur melalui `.env` atau `config/authentication.php`:

```env
# Pilihan tema: 'light', 'dark', atau 'auto'
AUTH_UI_THEME=auto
```

* **`light`**: Memaksa tampilan mode terang bersih dengan kontras tinggi dan teks tajam.
* **`dark`**: Memaksa tampilan mode gelap elegan bernuansa *modern dark slate/zinc*.
* **`auto`**: Secara cerdas mendeteksi pengaturan tema perangkat/browser pengguna (`prefers-color-scheme: dark`) dan mendengarkan perubahan tema secara *real-time* tanpa perlu reload halaman.

---

## 🚀 3 Cara Melakukan Kustomisasi Tampilan

---

### CARA 1: Ganti Template Layout Bawaan (Paling Mudah)

Package menyediakan 2 template siap pakai dengan estetika profesional:
- **`split` (Default)**: Tampilan 2-kolom (Panel branding & monitor telemetri di kiri, formulir di kanan).
- **`card`**: Tampilan 1-kolom kartu tengah minimalis.

Cukup ubah variabel di file `.env` Anda:

```env
# Pilihan: 'split' atau 'card'
AUTH_UI_LAYOUT=card
AUTH_UI_THEME=light

# Ubah informasi branding
AUTH_UI_BRAND_NAME="Aplikasi Saya"
AUTH_UI_BRAND_TAGLINE="Platform Keamanan Enterprise"
AUTH_UI_BRAND_BADGE="LIVE SECURE TLS 1.3"
```

Atau atur langsung di file [`config/authentication.php`](file:///d:/WEBSITE/PACKAGE/LaravelAuthentication/config/authentication.php):

```php
'ui' => [
    'layout'        => env('AUTH_UI_LAYOUT', 'card'),
    'theme'         => env('AUTH_UI_THEME', 'light'), // 'light', 'dark', atau 'auto'
    'brand_name'    => 'Portal Perusahaan',
    'brand_tagline' => 'Masuk untuk mengelola layanan',
],
```

---

### CARA 2: Publish View Bawaan & Edit Komponennya

Jika Anda menyukai struktur yang ada namun ingin mengubah teks, warna Tailwind, atau susunan komponen:

```bash
# Publish file view ke resources/views/vendor/authentication
php artisan vendor:publish --tag=authentication-views
```

Setelah dipublish, file akan berada di `resources/views/vendor/authentication/`:
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
Laravel akan otomatis memprioritaskan view di folder `resources/views/vendor/authentication/` daripada file bawaan package.

---

### CARA 3: Buat Halaman Tampilan Sendiri dari Nol (*Bring Your Own UI*)

Jika Anda ingin membuat halaman login atau OTP sendiri (misalnya di `resources/views/auth/login.blade.php`), Anda **tidak perlu** mengubah controller atau alur backend package. Cukup daftarkan path view Anda di config!

#### 1. Atur di `.env` atau `config/authentication.php`:

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

Pada [`config/authentication.php`](file:///d:/WEBSITE/PACKAGE/LaravelAuthentication/config/authentication.php):
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

## ⚡ Integrasi Otomatis Tailwind CSS & Alpine.js

* **Instalasi Otomatis Satu Langkah**:
  Jalankan `php artisan authentication:install` untuk otomatis menginjeksi path views package ke dalam CSS aplikasi host.
* **Tailwind CSS v4 (`resources/css/app.css`)**:
  ```css
  @import "tailwindcss";
  @source "../../vendor/mixudev/laravel-authentication/resources/views";
  @custom-variant dark (&:where(.dark, .dark *));
  ```
* **Alpine.js**:
  Layout bawaan package telah menyertakan Alpine.js sehingga modal konfirmasi (seperti tombol **Matikan 2FA**) dan form interaktif bekerja *out of the box* tanpa perlu setup manual.
