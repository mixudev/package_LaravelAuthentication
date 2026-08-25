# Panduan Lengkap: Upload ke GitHub, Rilis di Packagist, Update, dan Penggunaan Package

Panduan ini ditujukan untuk **`mixudev/laravel-authentication`** yang akan dipublikasikan ke GitHub dan Packagist.org.

---

## 1. Persiapan Awal (Yang Diperlukan)

Sebelum mengupload, pastikan hal-hal berikut:
1. **Nama Package di `composer.json`**:
   - Harus berupa huruf kecil (`lowercase`): `mixudev/laravel-authentication`.
2. **Akun GitHub & Packagist**:
   - Repository di GitHub: `https://github.com/mixudev/package_LaravelAuthentication.git`
   - Akun di [Packagist.org](https://packagist.org) dengan username `mixudev`.
3. **Apakah perlu Git Tag?**
   - **YA, SANGAT WAJIB.** Packagist dan Composer menentukan versi package (misal `v1.0.0`, `1.0.1`, `1.1.0`) berdasarkan **Git Tag**. Jika tidak membuat tag, user hanya bisa menginstall via `dev-main` yang kurang stabil untuk production.

---

## 2. Langkah Upload Pertama Kali (First Release)

Jalankan perintah berikut di terminal root folder package (`d:\WEBSITE\PACKAGE\LaravelAuthentication`):

### Langkah 2.1: Inisialisasi Git dan Commit Semua File
```bash
# Inisialisasi git jika belum
git init

# Tambahkan seluruh file package (bukan hanya README)
git add .

# Commit perdana
git commit -m "feat: initial release v1.0.0 production-grade modular auth package"

# Pastikan branch utama bernama main
git branch -M main

# Hubungkan dengan repository GitHub Anda
git remote add origin https://github.com/mixudev/package_LaravelAuthentication.git

# Push source code ke GitHub
git push -u origin main
```

### Langkah 2.2: Buat Tag Versi Perdana (v1.0.0)
```bash
# Buat semantic versioning tag
git tag v1.0.0

# Push tag ke GitHub
git push origin v1.0.0
```

---

## 3. Mendaftarkan Package ke Packagist.org

1. Buka browser dan login ke **[https://packagist.org](https://packagist.org)**.
2. Klik tombol **Submit** di menu navigasi atas (atau kunjungi `https://packagist.org/packages/submit`).
3. Masukkan URL Repository GitHub Anda:
   ```text
   https://github.com/mixudev/package_LaravelAuthentication
   ```
4. Klik **Check**. Packagist akan membaca file `composer.json`.
5. Jika nama package sudah sesuai (`mixudev/laravel-authentication`), klik **Submit**.
6. Sekarang package Anda sudah resmi terdaftar di Packagist!

### Langkah 3.1: Pasang Auto-Update via GitHub Webhook (Sangat Disarankan)
Agar setiap kali Anda melakukan `git push` atau rilis tag baru di GitHub, Packagist langsung otomatis ter-update tanpa harus klik manual:
1. Di halaman package Anda di Packagist, perhatikan notifikasi atau menu **"Auto-updated"**.
2. Salin **Packagist API Token** dari profil Packagist Anda (`https://packagist.org/profile/`).
3. Buka repository GitHub Anda -> **Settings** -> **Webhooks** -> **Add webhook**.
4. Isi **Payload URL**: `https://packagist.org/api/github?username=mixudev`
5. **Content type**: `application/json`
6. **Secret**: Masukkan Packagist API Token Anda.
7. Pilih event: *Just the push event*.
8. Klik **Add webhook**.

---

## 4. Cara Menggunakan Package di Aplikasi Laravel Lain

Setelah terdaftar di Packagist, Anda atau developer lain bisa menginstallnya di sembarang project Laravel:

### 1. Install via Composer
```bash
composer require mixudev/laravel-authentication
```

### 2. Publish Konfigurasi dan Migrasi
```bash
# Publish config
php artisan vendor:publish --tag=authentication-config

# Publish database migrations
php artisan vendor:publish --tag=authentication-migrations

# Jalankan migrasi
php artisan migrate
```

### 3. Konfigurasi `config/authentication.php`
Sesuaikan model User jika diperlukan:
```php
'user_model' => App\Models\User::class,
```

---

## 5. Cara Melakukan Update & Merilis Versi Baru (Maintenance Workflow)

Setiap kali Anda memperbaiki bug atau menambah fitur:

### Skenario A: Perbaikan Bug (Patch Release: v1.0.1)
1. Lakukan perubahan kode.
2. Update catatan di [`CHANGELOG.md`](file:///d:/WEBSITE/PACKAGE/LaravelAuthentication/CHANGELOG.md).
3. Commit dan push:
   ```bash
   git add .
   git commit -m "fix: resolve edge-case on identifier normalization"
   git push origin main
   ```
4. Buat tag baru dan push tag:
   ```bash
   git tag v1.0.1
   git push origin v1.0.1
   ```

### Skenario B: Penambahan Fitur Baru yang Backward Compatible (Minor Release: v1.1.0)
```bash
git add .
git commit -m "feat: add phone number authentication strategy"
git push origin main

git tag v1.1.0
git push origin v1.1.0
```

### Skenario C: Perubahan Besar / Breaking Changes (Major Release: v2.0.0)
```bash
git add .
git commit -m "feat!: major architecture upgrade for Laravel 13"
git push origin main

git tag v2.0.0
git push origin v2.0.0
```

---

## 6. Cara Menarik Update di Aplikasi Laravel Konsumen

Pada project Laravel yang sudah terpasang package ini, cukup jalankan:

```bash
composer update mixudev/laravel-authentication
```

Jika ada migrasi baru dari versi update:
```bash
php artisan vendor:publish --tag=authentication-migrations --force
php artisan migrate
```
