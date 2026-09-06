# Pruning Audit Logs (`authentication:prune`)

Audit trail (`authentication_attempts`, `authentication_login_histories`,
`authentication_password_histories`) tumbuh tanpa batas jika tidak dipangkas.
Command artisan ini menghapus data lebih tua dari masa retensi — sehingga
database tidak membengkak dan GDPR-style data-retention dapat dipenuhi.

---

## Cara kerja

```
php artisan authentication:prune
```

- **Default cutoff**: `config/authentication.php` → `audit.retention_days` (default `90` hari).
- **Sumber waktu per tabel** (tidak seragam — setiap tabel punya kolom waktunya sendiri):

| Tabel | Kolom | Model |
|---|---|---|
| `authentication_attempts` | `attempted_at` | `AuthenticationAttempt` (`$timestamps = false`) |
| `authentication_login_histories` | `login_at` | `LoginHistory` |
| `authentication_password_histories` | `created_at` | `PasswordHistory` |

- Hanya baris **lebih tua** dari cutoff yang dihapus (data baru selalu aman).
- Data akun **tidak** disentuh: `authentication_devices`, `authentication_passkeys`,
  `authentication_two_factor` — prunes hanya data audit.

## Opsi

| Opsi | Deskripsi |
|---|---|
| `--days=N` | Override retensi (mis. `--days=30`). Mengalahkan config. |
| `--dry-run` | Preview: tampilkan jumlah baris yang akan dihapus per tabel, tanpa menghapus apa pun. |

### Contoh

```bash
# Preview tanpa menghapus
php artisan authentication:prune --dry-run

# Hapus data lebih tua dari 30 hari (sekali jalan)
php artisan authentication:prune --days=30
```

## Scheduling (produksi)

Jadwalkan command ini setiap hari. Contoh Task Scheduler Windows:

```
schtasks /Create /SC DAILY /TN "laravel-auth-prune" /TR "php D:\path\to\artisan authentication:prune" /ST 03:00
```

Atau Linux cron:

```
0 3 * * * cd /var/www/app && php artisan authentication:prune --no-interaction
```

> Catatan: pastikan command berjalan dengan user yang punya akses tulis
> ke storage/logs (jika `audit.driver = log` atau `all`).

## Verifikasi

```bash
php artisan authentication:prune --dry-run
```

Output (format aktual command):

```
Retensi: 90 hari (cutoff 2026-06-08 03:00:00)
Mode: DRY-RUN (tidak ada data dihapus)
  AuthenticationAttempt   : 12 records
  LoginHistory            : 8 records
  PasswordHistory         : 0 records
Siap dihapus: 20 record. Jalankan tanpa --dry-run untuk eksekusi.
```

Setelah prune:

```php
// Di aplikasi host — jumlah attempt tersisa
\Vendor\LaravelAuthentication\Models\AuthenticationAttempt::count();
```