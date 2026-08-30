# Laravel Authentication Package Documentation

Selamat datang di dokumentasi resmi **`mixudev/laravel-authentication`** (`Vendor\LaravelAuthentication\`).

Package ini menyediakan arsitektur autentikasi modular, portable, enterprise-grade, dan aman untuk aplikasi **Laravel 10.x, 11.x, 12.x, dan 13.x**.

---

## 📚 Daftar Isi Dokumentasi

1. [**Panduan Memulai (Getting Started)**](getting-started.md)
   - Instalasi otomatis satu langkah via `php artisan authentication:install`
   - Otomatisasi injeksi Tailwind CSS & dark mode variant
   - Publikasi konfigurasi, migrasi, dan view
   - Struktur database & migrasi otomatis

2. [**Mode Modul Tunggal (Single-Folder Module Mode)**](modular-installation.md)
   - Perintah instan `php artisan authentication:install-module`
   - Struktur modul mandiri di `modules/Authentication/`

3. [**Fitur & Modul Utama (Features & Modules)**](features.md)
   - Multi-Factor Authentication (MFA/2FA TOTP & Recovery Codes)
   - Manajemen Sesi & Perangkat Aktif (Session & Device Management)
   - Rate Limiting Granular per Fitur
   - Notifikasi Login dari Perangkat Baru / Mencurigakan
   - Konfigurasi Nama Tabel Database & Migrasi Dinamis
   - CAPTCHA & Proteksi Bot Adaptif (Turnstile, reCAPTCHA, hCaptcha)
   - Konfirmasi Password untuk Aksi Sensitif (Re-Auth)
   - Pengiriman Email & OTP Asinkron (Queue)
   - Modul registrasi akun & auto-login
   - Modul autentikasi tanpa password via Kode OTP
   - Modul OAuth Social Login (Google & GitHub)
   - Modul pemulihan & reset kata sandi
   - Kebijakan kekuatan password & riwayat password

4. [**Kustomisasi Tampilan & Template UI**](panduan-kustomisasi-view.md) / [🇬🇧 English](views-customization.md)
   - Penggunaan 2 template layout bawaan: `split` (2-kolom) & `card` (kartu tengah)
   - Cara memodifikasi komponen Blade bawaan (`vendor:publish`)
   - Cara membuat tampilan sendiri dari nol (*Bring Your Own UI*)
   - Spesifikasi form, nama input wajib, route actions, dan token CSRF

5. [**Strategi Autentikasi & Ekstensi Kustom**](strategies-and-extending.md)
   - Strategi bawaan (`username_or_email`, `email_password`, `username_password`, `custom_identifier`)
   - Cara membuat strategi autentikasi kustom (NIP, Nomor HP, RFID, SSO)
   - Event listening & penanganan payload

6. [**Referensi REST API (API Reference)**](api-reference.md)
   - Katalog lengkap endpoint API JSON (`/api/v1/auth/*`)
   - 2FA Challenge, Session Management, dan Confirm Password endpoints
   - Skema payload request & response

7. [**Keamanan & Praktik Terbaik (Security & Best Practices)**](security-and-best-practices.md)
   - Matriks mitigasi ancaman & proteksi zero-trust
   - Rate limiting komposit per fitur & adaptif CAPTCHA
   - MFA/2FA & Device Trust cookies
   - Mitigasi User Enumeration & timing normalization
   - Proteksi session fixation & rehash password otomatis
   - Audit logging & penyamaran data sensitif (`#[\SensitiveParameter]`)

8. [**Panduan Rilis & Publikasi (Publishing Guide)**](publishing-guide.md)
   - Standar Git commit & Semantic Versioning
   - Publikasi ke GitHub & Packagist.org

9. [**Checklist Prasyarat & Peta URL (Prerequisites & Sitemap)**](prerequisites-and-checklist.md)
   - Matriks kebutuhan layanan & kunci API (mana yang butuh daftar vs mandiri)
   - Panduan langkah mendapatkan API keys (Cloudflare Turnstile, Google & GitHub OAuth)
   - Peta lengkap 49 Rute Web & REST API beserta hak aksesnya
