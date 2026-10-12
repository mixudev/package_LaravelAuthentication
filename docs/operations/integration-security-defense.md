# Integrasi dengan Security Defense Package

`mixudev/laravel-authentication` dapat diintegrasikan dengan `mixudev/security-defense` untuk meneruskan semua event autentikasi ke sistem SIEM/threat detection enterprise.

## Ringkasan

Security Defense package bertindak sebagai "kamera pengawas & perisai proaktif" yang mendeteksi:
- Brute force & credential stuffing attacks
- Impossible travel (login dari lokasi geografis berbeda dalam waktu singkat)
- Session hijacking & cookie theft
- Distributed spray attacks
- Path reconnaissance & payload injection (SQLi, XSS, command injection)
- Behavioral velocity anomalies

Integrasi ini meneruskan 12 auth events ke `SecurityDefense::record()` untuk analisis real-time dan alerting multi-channel (Telegram, Discord, Email, Webhook).

## Prasyarat

1. Package authentication sudah terinstall:
   ```bash
   composer require mixudev/laravel-authentication
   php artisan authentication:install
   ```

2. Package security-defense sudah terinstall:
   ```bash
   composer require mixudev/security-defense
   php artisan security-defense:install --with-opaque-path
   ```

## Setup Integrasi (1 Perintah)

```bash
php artisan auth:sync
```

Command ini akan:
1. ✓ Deteksi package authentication
2. ✓ Generate bridge subscriber: `app/Listeners/AuthenticationSecuritySubscriber.php`
3. ✓ Map 12 auth events → `SecurityDefense::record()`
4. ✓ Inject `RequestThreatScanner` middleware (WAF layer)
5. ✓ Verifikasi registrasi subscriber (auto-discovery `app/Listeners`, tanpa `Event::subscribe` manual)

## Registrasi Subscriber

Laravel 11+ mendaftarkan subscriber di `app/Listeners` otomatis (event discovery).
Tidak ada langkah manual:

```bash
php artisan auth:sync
php artisan event:list | grep AuthenticationSecuritySubscriber
```

Pastikan muncul satu baris untuk setiap dari 12 event. Menambahkan
`Event::subscribe(...)` di `AppServiceProvider` saat discovery aktif mendaftarkan
12 handler dua kali: `SecurityDefense::record()` jalan dua kali per event dan
counter brute force mencapai threshold pada setengah percobaan.

Registrasi manual hanya untuk aplikasi yang menonaktifkan event discovery
(Laravel lama / `withEvents(discover: false)`), dan hanya setelah dipastikan
`event:list` tidak menunjukkan listener sama sekali:

```php
Event::subscribe(\App\Listeners\AuthenticationSecuritySubscriber::class);
```

## Event Mapping

| Auth Event | SecurityDefense EventType | Metadata |
|-----------|--------------------------|----------|
| `LoginFailed` | LoginFailed | reason, user_id |
| `LoginSucceeded` | LoginSucceeded | strategy, user_id |
| `AccountLocked` | AccountLocked | lockout_duration_minutes, user_id |
| `NewDeviceLoginDetected` | NewDeviceLoginDetected | device_id, user_id |
| `OtpVerified` | OTP_VERIFIED | user_id |
| `PasswordChanged` | PasswordChanged | user_id |
| `SessionRevoked` | SessionRevoked | session_id, user_id |
| `LogoutPerformed` | LogoutPerformed | user_id |
| `UserRegistered` | UserRegistered | user_id |
| `PasswordResetRequested` | PasswordResetRequested | user_id |
| `PasswordResetCompleted` | PasswordResetCompleted | user_id |
| `EmailVerified` | EmailVerified | user_id |

## Verifikasi Integrasi

1. Trigger login failure:
   ```bash
   curl -X POST http://localhost:8000/login \
     -d "identifier=test@example.com&password=wrong"
   ```

2. Cek security dashboard:
   ```
   http://localhost:8000/security-defense/dashboard
   ```
   (URL opaque auto-generated dari APP_KEY)

3. Verifikasi alert di Telegram/Discord (jika channel enabled)

## Disable Auto-Detection

Edit `config/authentication.php`:

```php
'security_defense' => [
    'auto_detect' => false, // Disable installation hint
],
```

## Troubleshooting

**Bridge subscriber tidak ter-generate**:
- Pastikan `mixudev/laravel-authentication` installed: `composer show mixudev/laravel-authentication`
- Re-run dengan force: `php artisan auth:sync --force`

**Events tidak masuk ke dashboard**:
- Verifikasi subscriber registered: `grep -r "AuthenticationSecuritySubscriber" app/Providers/`
- Clear event cache: `php artisan event:clear`
- Check enabled rules: `config/security-defense.php` → `detection.rules`

**Alert tidak dikirim**:
- Verifikasi channel config: `config/security-defense.php` → `alerting.channels`
- Test channel: `php artisan security-defense:test-channel telegram`

## Referensi

- Security Defense Documentation: `vendor/mixudev/security-defense/docs/`
- Security Defense GitHub: https://github.com/mixudev/package_LaravelSecurityDefense
- Auth Package Documentation: `docs/index.md`
