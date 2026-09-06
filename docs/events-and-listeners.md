# Events & Listeners

Package ini menyediakan **domain events** untuk seluruh lifecycle autentikasi.
Host application dapat mendengarkan event-event ini untuk integrasi:
notifikasi, audit trail kustom, SIEM, rate-limit response, dsb.

Tidak ada password plaintext, reset token, atau secret di payload event —
seluruh payload sudah direduksi (redacted).

---

## 1. Daftar Event

| Event | Dipicu saat | Payload penting |
|---|---|---|
| `LoginAttempted` | Setiap percobaan login dimulai (sebelum validasi) | `identifier`, `context`, `strategy?` |
| `LoginSucceeded` | Login berhasil (password + session/token dibuat) | `user`, `context`, `strategy` |
| `LoginFailed` | Kredensial salah / user tidak ditemukan | `identifier`, `context`, `reason`, `user?` |
| `AccountLocked` | Akun dikunci setelah gagal beruntun | `user`, `context`, `lockoutDurationMinutes` |
| `LogoutPerformed` | User logout (web: session dihapus, api: token dicabut) | `user?`, `context` |
| `SessionRevoked` | Sesi/device di-revoke via SessionController | `user?`, `context`, `sessionId` |
| `UserRegistered` | Registrasi user baru selesai | `user`, `context` |
| `EmailVerified` | Email berhasil diverifikasi | `user`, `context` |
| `PasswordChanged` | Password diubah (updatePassword / reset) | `user`, `context?` |
| `PasswordResetRequested` | Link reset dikirim (selalu, walau email tak ada — anti-enumeration) | `email`, `context`, `user?` |
| `PasswordResetCompleted` | Password berhasil direset | `user`, `context?` |
| `OtpGenerated` | Kode OTP dibuat | `user?`, `identifier`, `code` (SensitiveParameter), `context`, `expiryMinutes` |
| `OtpVerified` | Kode OTP terverifikasi | `user?`, `identifier`, `context` |
| `NewDeviceLoginDetected` | Login dari device/fingerprint baru | `user`, `device`, `context` |

Catatan:
- Event dengan `context?` (nullable `AuthenticationContext`) boleh dipicu dari luar
  HTTP (queue worker, CLI). Jika di-dispatch dari request HTTP, context terisi.
- `LoginFailed` juga dipicu untuk user yang **tidak ada** — payload `user` = `null`.
  Ini sengaja agar listener tidak bisa membedakan user valid vs tidak valid
  (anti user enumeration; **jangan** log `user_id` dari event ini).

---

## 2. Cara Mendaftarkan Listener

### A. Di `EventServiceProvider` host app

```php
// app/Providers/EventServiceProvider.php
use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Vendor\LaravelAuthentication\Events\LoginFailed;
use App\Listeners\SendLoginNotification;

protected $listen = [
    LoginSucceeded::class => [
        SendLoginNotification::class,
    ],
    LoginFailed::class => [
        // listener host app
    ],
];
```

### B. Registrasi manual di `AppServiceProvider::boot()`

```php
use Illuminate\Support\Facades\Event;
use Vendor\LaravelAuthentication\Events\AccountLocked;

Event::listen(AccountLocked::class, function ($event) {
    // kirim alert ke admin, quarantine IP, dsb
    info('account_locked', [
        'user_id' => $event->user->getAuthIdentifier(),
        'ips'     => $event->context->ipAddress,
        'mins'    => $event->lockoutDurationMinutes,
    ]);
});
```

### C. Listener bawaan package (opsional, opt-in)

Package menyediakan `SecurityAuditEventListener` yang menulis audit trail
terstruktur (redacted) ke log channel. Aktifkan di config:

```php
// config/authentication.php
'listeners' => [
    'default_audit_enabled' => true,
],
```

Listener ini menangani `LoginSucceeded`, `LoginFailed`, `AccountLocked`,
`NewDeviceLoginDetected` dan menulis JSON line ke
`config('authentication.audit.log_channel', 'stack')`.

Host app boleh set `false` dan mendaftarkan listener sendiri.

---

## 3. Event yang WAJIB dihindari di payload listener

- Jangan tulis `$event->user->getAuthPassword()` atau hash ke log/notification.
- `OtpGenerated->code` ditandai `#[SensitiveParameter]` — tidak boleh di-log.
  Jika listener perlu mengirim kode ke user, gunakan mailable bawaan package
  (`OtpMail`), jangan re-dispatch kode ke channel lain.
- Jangan bandingkan `LoginFailed->user` untuk membedakan user exist/tidak —
  ini membuka celah user enumeration di sisi integrasi.

---

## 4. Contoh: Alert ke Telegram/Discord untuk AccountLocked

```php
// app/Listeners/NotifyAdminsOnLockout.php
use Vendor\LaravelAuthentication\Events\AccountLocked;

class NotifyAdminsOnLockout
{
    public function handle(AccountLocked $event): void
    {
        $details = [
            'user_id'  => $event->user->getAuthIdentifier(),
            'ip'       => $event->context->ipAddress,
            'duration' => $event->lockoutDurationMinutes,
        ];

        // kirim via Telegram bot / Discord webhook host app
    }
}
```

---

## 5. Queue Listener

Event memakai `SerializesModels` — model user/device aman di-queue.
Listener bisa implements `ShouldQueue` biasa:

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLoginNotification implements ShouldQueue
{
    public $queue = 'notifications';
    // ...
}
```