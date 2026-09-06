# Panduan Rilis & Publikasi Package (GitHub & Packagist)

Dokumen ini menjelaskan prosedur standar pemeliharaan, pembuatan rilis versi baru (*SemVer*), dan publikasi package **`mixudev/laravel-authentication`** ke GitHub dan Packagist.org.

---

## 1. Konfigurasi Package & Composer

Pastikan file [`composer.json`](file:///d:/WEBSITE/PACKAGE/LaravelAuthentication/composer.json) memiliki informasi metadata yang valid:
- **Nama Package**: `mixudev/laravel-authentication` (huruf kecil).
- **Lisensi**: `MIT`
- **Autoload**: PSR-4 mapping `Vendor\\LaravelAuthentication\\` &rarr; `src/`.

---

## 2. Prosedur Git Commit & Semantic Versioning

Ikuti standar **Conventional Commits**:
- `feat: <deskripsi>` (Fitur baru)
- `fix: <deskripsi>` (Perbaikan bug)
- `docs: <deskripsi>` (Pembaruan dokumentasi)
- `refactor: <deskripsi>` (Refaktorisasi kode)

### Alur Rilis Versi Baru:
1. Update file [`CHANGELOG.md`](file:///d:/WEBSITE/PACKAGE/LaravelAuthentication/CHANGELOG.md) dengan catatan perubahan versi baru.
2. Lakukan validasi dan commit:
   ```bash
   composer validate --strict
   git add -A
   git commit -m "feat: deskripsi rilis versi baru"
   ```
3. Buat Tag Semantic Versioning:
   ```bash
   git tag v1.3.0
   ```
4. Push branch utama dan tag rilis ke GitHub:
   ```bash
   git push origin main
   git push origin v1.3.0
   ```

---

## 3. Sinkronisasi dengan Packagist.org

1. Login ke akun Anda di [Packagist.org](https://packagist.org).
2. Kunjungi halaman package: `https://packagist.org/packages/mixudev/laravel-authentication`.
3. Klik tombol **Update** untuk menarik tag rilis terbaru dari GitHub.
4. *(Disarankan)* Pasang GitHub Webhook di repository GitHub (`Settings` &rarr; `Webhooks` &rarr; Packagist Auto-Update) agar setiap kali Anda melakukan `git push`, Packagist akan otomatis memperbarui rilis versi terbaru dalam hitungan detik.

---

## 4. Verifikasi Instalasi Pengguna

Setelah versi baru terdaftar di Packagist, pengguna dapat langsung mengupdate package dengan:
```bash
composer update mixudev/laravel-authentication
```
