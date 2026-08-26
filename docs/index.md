# Laravel Authentication Package Documentation

Selamat datang di dokumentasi resmi **`mixudev/laravel-authentication`** (`Vendor\LaravelAuthentication\`).

Package ini menyediakan arsitektur autentikasi modular, portable, enterprise-grade, dan aman untuk aplikasi **Laravel 10.x, 11.x, 12.x, dan 13.x**.

---

## 📚 Daftar Isi Dokumentasi

1. [**Panduan Memulai (Getting Started)**](getting-started.md)
   - Cara instalasi via Composer & Path repository
   - Publikasi konfigurasi, migrasi, dan view
   - Struktur database & migrasi otomatis

2. [**Mode Modul Tunggal (Single-Folder Module Mode)**](modular-installation.md)
   - Perintah instan `php artisan authentication:install-module`
   - Struktur modul rapi di `modules/Authentication/`

3. [**Fitur & Modul Utama (Features & Modules)**](features.md)
   - Modular feature toggles (`config/authentication.php`)
   - Modul registrasi akun & auto-login
   - Modul autentikasi tanpa password via Kode OTP
   - Modul OAuth Social Login (Google & GitHub)
   - Modul pemulihan & reset kata sandi
   - Kebijakan kekuatan password dinamis

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
   - Skema payload request & response
   - Manajemen token Bearer dengan Laravel Sanctum

7. [**Keamanan & Praktik Terbaik (Security & Best Practices)**](security-and-best-practices.md)
   - Mitigasi User Enumeration & timing normalization
   - Rate limiting komposit (`sha1(ip + identifier)`) & account lockout
   - Proteksi session fixation & rehash password otomatis
   - Audit logging & penyamaran data sensitif (`#[\SensitiveParameter]`)

8. [**Panduan Rilis & Publikasi (Publishing Guide)**](publishing-guide.md)
   - Standar Git commit & Semantic Versioning
   - Publikasi ke GitHub & Packagist.org
