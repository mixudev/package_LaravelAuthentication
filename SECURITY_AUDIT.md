# SECURITY AUDIT REPORT — mixudev/laravel-authentication

**Tanggal:** 3 September 2026 (revisi: fixes tahap 1 & 2 diterapkan)
**Auditor:** Hermes Agent (AI Code Security Review)
**Metodologi:** White-box source code audit, threat modeling sebagai attacker, review alur data dan trust boundary
**Coverage:** 100% PHP source files di `src/` + routes + config + tests
**Test Status:** 66 tests, 223 assertions — all pass | PHPStan: 10 pre-existing errors (tidak bertambah)

---

## RINGKASAN EKSEKUTIF

| Severity | Jumlah | Status |
|----------|--------|--------|
| CRITICAL | 3 | Perlu perbaikan segera |
| HIGH | 6 | Perlu perbaikan sebelum production |
| MEDIUM | 7 | Perlu perbaikan sebelum hardening |
| LOW | 5 | Rekomendasi perbaikan |

**Arsitektur Umum:**
- Pipeline autentikasi terstruktur dengan baik (rate limit → strategy → identity → credential → session)
- User Enumeration Protection konsisten di semua endpoint
- CSRF protection tersedia via Laravel `web` middleware group
- Session fixation protection aktif via `SessionSecurityService`
- Password hashing dengan rehash otomatis
- TOTP 2FA dengan recovery codes yang di-hash
- WebAuthn/Passkey implementation dengan challenge-response anti-replay
- Audit logging dengan PII masking

---

## CRITICAL SEVERITY

### SEC-01: Cache Race Condition pada OTP Attempt Counter

**File:** `src/Services/Otp/OtpService.php:161-167`
**Attack Vector:** Parallel request brute-force OTP

```php
$data = $this->cache->get($cacheKey);       // READ
$data['attempts']++;                         // MODIFY
// ... attacker sends N parallel requests ...
$this->cache->put($cacheKey, $data, ...);   // WRITE (stale)
```

Read-modify-write tanpa atomic operation. Attacker yang mengirim 5+ request paralel bersamaan bisa mendapatkan N x `max_attempts` percobaan sebelum counter benar-benar terisi.

**Impact:** Brute-force OTP 6 digit (1 juta kemungkinan) menjadi lebih mudah dengan parallel requests. Jika `max_attempts=3`, attacker bisa mendapatkan 3-5x lebih banyak percobaan.

**Fix:**
```php
// Gunakan atomic increment dari cache driver
$currentAttempts = (int) $this->cache->get("{$cacheKey}:attempts", 0);
$this->cache->increment("{$cacheKey}:attempts");
if ($currentAttempts + 1 > $data['max_attempts']) {
    $this->cache->forget($cacheKey);
    throw new AuthenticationException('Too many invalid attempts.');
}
```

---

### SEC-01.5: OTP Email Queue Conflict — ShouldQueue Hardcoded

**File:** `src/Mail/OtpMail.php`, `src/Mail/NewDeviceLoginMail.php`

Both `OtpMail` and `NewDeviceLoginMail` mailables implement `ShouldQueue` directly in their class declaration. This forces all dispatch into the queue regardless of `config('mail.queue')`. When no queue worker is running (common in development, small deployments, or misconfigured production), emails are silently queued but never delivered.

**Impact:** OTP verification emails and new-device-login alert emails silently fail to deliver when no queue worker is active. Users cannot complete OTP-based login or receive security alerts.

**Fix:** Remove the hardcoded `ShouldQueue` interface from both mailables. Queue/sync routing is already driven by `config('mail.queue')` at dispatch time (`Mail::to(...)->queue()` vs `Mail::to(...)->send()`), so removing the interface restores the intended config-driven behavior.

---

### SEC-02: Timing Attack pada API Password Reset

**File:** `src/Http/Controllers/PasswordResetController.php:107-123`

Versi Web (`sendResetLinkEmail` line 52) punya `usleep(random_int(50_000, 150_000))` untuk timing normalization. Versi API (`apiSendResetLink`) TIDAK punya timing normalization.

Meskipun response selalu generic, waktu respons yang berbeda (misalnya 5ms untuk user tidak ada vs 50ms untuk user ada + email sending) bisa diukur attacker untuk mengonfirmasi keberadaan email.

**Impact:** User enumeration via timing side-channel di API endpoint.

**Fix:**
```php
public function apiSendResetLink(ForgotPasswordRequest $request): JsonResponse
{
    // ... existing code ...
    $status = Password::broker()->sendResetLink($request->only('email'));
    
    // Tambahkan timing normalization seperti versi Web
    usleep(random_int(50_000, 150_000));
    
    return response()->json([
        'status'  => 'success',
        'message' => 'If an account exists with that email, a password reset link has been dispatched.',
    ]);
}
```

---

### SEC-03: Registration API Mengungkap User Object Lengkap

**File:** `src/Http/Controllers/RegisterController.php:103-108`

```php
return response()->json([
    'status'  => 'success',
    'message' => 'Account registered successfully.',
    'user'    => $user,        // <<< SELURUH USER MODEL
    'token'   => $token,
], 201);
```

User model dikembalikan tanpa filtering. Jika host app punya field sensitif di User model (phone, address, role, admin flag), semua field ini bocor ke client.

**Impact:** Information disclosure — attacker mendapatkan struktur data internal user.

**Fix:**
```php
'resource' => [
    'id'   => $user->getAuthIdentifier(),
    'name' => $user->name ?? null,
    'email' => $user->email ?? null,
],
```
Atau gunakan UserResource transformer yang didefinisikan host app.

---

## HIGH SEVERITY

### SEC-04: 2FA Device Trust Cookie — No Server-Side Revocation

**File:** `src/Services/Session/DeviceTrustService.php:46-48`

Cookie HMAC dibentuk dari `user_id|fingerprint`. Verifikasi hanya memeriksa HMAC match + device record exists + `trusted_until` tidak expired. Tidak ada server-side token yang unik — begitu cookie diketahui, attacker bisa menggunakannya sampai 30 hari.

**Attack Scenario:**
1. Attacker mencuri cookie dari user (XSS, shared computer, network sniffing)
2. Attacker membuat request dengan cookie + user-agent yang matching
3. 2FA bypass aktif selama durasi trust (default 30 hari)
4. User logout/revoke tidak mempengaruhi cookie karena tidak ada token revoke list

**Fix:**
- Simpan random token unik di database saat cookie dibuat
- Simpan hash token di cookie (bukan HMAC dari predictables)
- Revocation: hapus token dari DB saat user logout

---

### SEC-05: Passkey Registration Tidak Validasi Attestation Object

**File:** `src/Services/Passkey/PasskeyService.php:131-184`

Metode `registerPasskey()` menerima `response.publicKey` atau `response.attestationObject` dan langsung diproses sebagai public key tanpa memvalidasi bahwa itu benar-benar berasal dari WebAuthn attestation ceremony.

Meskipun `attestation: 'none'` dikonfigurasi (yang memang menonaktifkan attestation validation), host app mungkin mengharapkan validasi lebih kuat untuk use case tertentu.

**Impact:** Attacker yang sudah terautentikasi bisa mendaftarkan passkey dari perangkat apa pun (termasuk perangkat curi atau virtual). Ini acceptable untuk consumer apps tapi mungkin tidak untuk enterprise/high-security.

**Recommendation:** Dokumentasikan trade-off. Jika enterprise, set `attestation: 'direct'` dan validasi attestation statement.

---

### SEC-05b: QR Data URI Scan Failure — SVG Unsupported by Mobile Scanners

**File:** `src/Support/QrCodeGenerator.php`

`QrCodeGenerator::dataUri()` produced `data:image/svg+xml;base64` data URIs for 2FA QR codes. Mobile authenticator camera scanners (Google Authenticator, Authy, Microsoft Authenticator) do not support SVG data URIs — the scanner opens the URI in a browser instead of decoding the TOTP secret, causing setup to fail silently on mobile.

**Impact:** Users cannot complete 2FA setup via mobile camera scan. Only manual key entry works, degrading UX and reducing 2FA adoption.

**Fix:** `QrCodeGenerator::dataUri()` now prefers PNG via GD (`imagefilledrectangle` scaling) with SVG fallback only when GD extension is unavailable. The view blade is unchanged — the `src` attribute still receives a data URI, now PNG-encoded.

---

### SEC-06: OAuth Stateless Callback — Tidak Ada CSRF Protection

**File:** `src/Http/Controllers/SocialAuthController.php:99-155`

API OAuth callback (`apiCallback`) menggunakan Socialite `stateless()` mode. Dalam mode stateless, state parameter tidak divalidasi — ini menghilangkan CSRF protection pada OAuth flow.

**Attack Scenario:**
1. Attacker memulai OAuth flow sendiri, mendapatkan authorization code
2. Attacker mengirim authorization code ke callback endpoint target user
3. Jika user dengan email yang sama ada, attacker mendapatkan token

**Impact:** Account takeover via OAuth CSRF. Membutuhkan attacker tahu atau menebak email target.

**Fix:**
```php
$driver = $driver->stateless(); // HAPUS BARIS INI
// Biarkan Socialite menggunakan stateless session secara default,
// atau implementasi custom state validation
```
Atau pastikan `APP_KEY` kuat dan gunakan signed state parameter.

---

### SEC-07: Account Lockout Hanya di Cache — Bisa Bypass

**File:** `src/Services/Security/AccountLockService.php`

Lockout state (counter + lock flag) disimpan di cache. Jika attacker punya akses ke cache (Redis flush, cache driver manipulation, atau shared hosting), lockout bisa di-reset.

**Impact:** Brute force protection bisa di-bypass.

**Mitigation:** Saat ini acceptable untuk single-server deployments. Untuk production, tambahkan opsi persistent lockout di database.

---

### SEC-08: LoginData.extra Field Bisa Mengekspos Data Sensitif

**File:** `src/DTO/LoginData.php:42`
**File:** `src/Http/Requests/LoginRequest.php:125`

```php
extra: $this->except(['password'])  // Semua input selain password
```

Field `extra` menyimpan semua request input. Jika ada custom field yang dikirim attacker (misalnya `admin=true`, `debug=1`), field ini masuk ke LoginData dan bisa bocor ke event listeners atau audit logs.

**Impact:** Data injection — attacker bisa menyuntikkan data arbitrer ke event payload.

**Fix:**
```php
extra: $this->only(['remember', 'strategy'])
```

---

### SEC-09: 2FA Setup Endpoint Menyimpan Recovery Codes dalam Memory

**File:** `src/Http/Controllers/TwoFactorSetupController.php:49-64`

`TwoFactorService::setup()` mengembalikan plain recovery codes. Di web view, codes di-pass ke Blade template. Di API response, codes di-JSON.

Kode disimpan secara sementara dalam PHP memory. Jika ada memory dump (crash, profiling), codes bisa terekspos. Ini inherent limitation dari TOTP setup, tapi perlu dipahami.

**Recommendation:** Setelah user mengonfirmasi setup, pastikan recovery codes di-invalidate (generate ulang saat confirm).

---

## MEDIUM SEVERITY

### SEC-10: Password Reset Token Return di JSON Fallback

**File:** `src/Http/Controllers/PasswordResetController.php:82-86`

```php
return response()->json([
    'message' => 'Please reset password via POST.',
    'token'   => $token,        // <<< TOKEN TEREKSPS
    'email'   => $request->query('email'),
]);
```

Fallback JSON response mengekspos reset token ke URL dan potentially ke browser history / logs.

**Fix:**
```php
return response()->json([
    'message' => 'Please reset password via POST.',
]);
```

---

### SEC-11: TOTP Secret Plain Text di API Response

**File:** `src/Http/Controllers/TwoFactorSetupController.php:51-53`

```php
if ($request->expectsJson()) {
    return response()->json($setupData);
    // setupData['secret'] = plaintext TOTP secret
}
```

TOTP secret dikembalikan dalam plain text. Ini memang standar untuk setup flow (client butuh secret untuk QR code), tapi perlu diketahui sebagai potential data exposure.

**Recommendation:** Tambahkan warning di dokumentasi. Pertimbangkan signed/encrypted JWT untuk delivery secret.

---

### SEC-12: EnsureSessionSecurity Tidak Mengatur Cookie Flags

**File:** `src/Http/Middleware/EnsureSessionSecurity.php`

Middleware hanya menambahkan HTTP security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy). Tidak mengatur session cookie flags (HttpOnly, Secure, SameSite).

**Impact:** Bergantung pada host app's session configuration. Jika host app tidak konfigurasi cookie flags dengan benar, session bisa rentan.

**Recommendation:** Tambahkan di service provider:
```php
config(['session.cookie_secure' => true]);
config(['session.cookie_http_only' => true]);
config(['session.cookie_samesite' => 'lax']);
```

---

### SEC-13: AuthenticationContext Menyimpan Seluruh Headers

**File:** `src/DTO/AuthenticationContext.php:24`

```php
public readonly array $headers = []
```

Semua request headers disimpan. Jika ada custom header dengan data sensitif (misalnya `X-Forwarded-Authorization`, `X-Internal-Token`), headers ini masuk ke AuthenticationContext dan bisa bocor ke audit logs atau events.

**Fix:**
```php
headers: array_filter(
    $request->headers->all(),
    fn($key) => in_array(strtolower($key), ['user-agent', 'referer', 'origin']),
    ARRAY_FILTER_USE_KEY
)
```

---

### SEC-14: OAuth Auto-Register Tidak Memvalidasi Email Verifikasi

**File:** `src/Services/Social/SocialAuthService.php:115-133`

Social login dengan `auto_register: true` membuat user baru tanpa memverifikasi email dari OAuth provider. Meskipun email dari OAuth provider dianggap verified, tidak ada pengecekan `verified` flag dari Socialite.

**Impact:** Attacker bisa membuat akun dengan email orang lain via OAuth provider yang tidak memverifikasi email.

**Recommendation:**
```php
if (method_exists($socialUser, 'getEmailVerified') && !$socialUser->getEmailVerified()) {
    throw new AuthenticationException('Email verification is required.');
}
```

---

### SEC-15: Password History Check Bisa Dikombinasikan dengan Race Condition

**File:** `src/Services/Password/PasswordService.php:33-37`

Password history check dilakukan sebelum hash, dan hash + record dilakukan setelah save. Dalam kondisi concurrent, user bisa mengubah password dengan history yang sama.

**Impact:** Low — membutuhkan concurrent requests yang sangat spesifik.

---

### SEC-16: Rate Limiter Key Menggunakan SHA1

**File:** `src/Services/Security/FeatureRateLimiter.php:87-88`

```php
"auth_rl:{$feature}:id:" . sha1($normId)
"auth_rl:{$feature}:comp:" . sha1("{$normId}|{$ipAddress}")
```

SHA1 digunakan untuk hashing rate limit keys. Untuk use case ini (bukan kriptografi) ini aman, tapi konsistensi lebih baik dengan SHA256.

---

## LOW SEVERITY

### SEC-17: Default Configuration Tidak Optimal untuk Production

**File:** `config/authentication.php`

| Setting | Default | Risk |
|---------|---------|------|
| `captcha.enabled` | `false` | Bot/automated attacks tidak ter-block |
| `account_lockout.enabled` | `false` | Brute force tidak ter-limit per-user |
| `password.validation_rules` | minimal (hanya min:8) | Password lemah diizinkan |
| `password.history.enabled` | `false` | Password reuse diizinkan |
| `registration.require_email_verify` | `false` | Fake email bisa daftar |

**Recommendation:** Tambahkan "hardened" config preset untuk production:
```php
// config/authentication-hardened.php
'password' => [
    'validation_rules' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
    ],
],
'account_lockout' => ['enabled' => true],
'captcha' => ['enabled' => true],
```

---

### SEC-18: Token Fallback Tanpa Expiry

**File:** `src/Services/Core/TokenService.php:26`

```php
return Str::random(64);
```

Ketika Sanctum tidak tersedia, token dihasilkan via `Str::random(64)` tanpa expiry atau revocation tracking. Token ini valid selamanya sampai server restart (jika in-memory) atau sampai di-revoke secara manual.

**Impact:** Leaked token bisa digunakan tanpa batas waktu.

---

### SEC-19: Email Query Parameter di Password Reset URL

**File:** `src/Http/Controllers/PasswordResetController.php:78`

```php
'email' => $request->query('email'),
```

Email dari query parameter di-pass ke view tanpa sanitasi. Jika Blade view menggunakan `{!! $email !!}` (unescaped), ini XSS.

**Recommendation:** Pastikan view menggunakan `{{ $email }}` (escaped). Di JSON fallback, email juga perlu di-escape.

---

### SEC-20: Passkey ID di URL Path Bisa Diekspos

**File:** `src/Http/Controllers/PasskeyController.php:124`

```php
public function destroy(Request $request, int|string $id): JsonResponse|RedirectResponse
```

Passkey ID di-pass sebagai URL parameter. Jika predictable, attacker bisa enumerate atau delete passkeys user lain.

**Mitigation:** Route sudah dalam `auth` middleware, jadi hanya authenticated user yang bisa akses. `deletePasskey()` juga memfilter by `user_id`.

---

### SEC-21: WebAuthn Origin Validation Tidak Validasi Protocol

**File:** `src/Support/WebAuthn/WebAuthnHelper.php:81-92`

Origin validation hanya membandingkan hostname, tidak memvalidasi protocol (http vs https). Jika app running di HTTP (dev), origin `http://localhost:3000` akan match.

**Impact:** Low — hanya relevan di development. Production harus HTTPS.

---

## STATUS PERBAIKAN (Tahap 1) — YANG SUDAH DIFIX

| Ref | Temuan | Status | Cara Fix | Verifikasi |
|-----|--------|--------|----------|------------|
| SEC-01 | OTP attempt counter race condition | FIXED | Atomic `cache->increment()` pada key terpisah `:attempts` | OtpRateLimitBypassTest pass |
| SEC-02 | API password reset timing attack | FIXED | `usleep(random_int(50k,150k))` ditambahkan | manual review |
| SEC-03 | Registration API user object leak | FIXED | Response filter ke id/name/email | manual review |
| SEC-04 | 2FA trust cookie tanpa server-side revocation | FIXED | Random token + SHA256 hash di DB (`trust_token_hash`), revoke di logout & revoke-others | DeviceTrustSecurityTest (revocation test) pass |
| SEC-06/14 | OAuth stateless + email unverified | FIXED (partial) | Block email_verified=false saat auto-register | manual review |
| SEC-08 | LoginData.extra data injection | FIXED | Whitelist field email/username only | manual review |
| SEC-10 | Reset token exposed di JSON fallback | FIXED | Hapus token/email dari fallback response | manual review |
| SEC-12 | Cookie flags tidak diatur | FIXED | Provider set secure/http_only/samesite dari config | manual review |
| SEC-13 | Headers bocor di AuthenticationContext | FIXED | Whitelist header non-sensitif | manual review |
| SEC-16 | SHA1 di rate limiter key | FIXED | Ganti ke sha256 | manual review |
| SEC-21 | WebAuthn origin protocol tidak divalidasi | FIXED | Wajib https (kecuali localhost/loopback) | manual review |

### Belum Difix (perlu keputusan / butuh perubahan besar / inherent)

| Ref | Temuan | Alasan Belum Difix | Rekomendasi |
|-----|--------|--------------------|-------------|
| SEC-05 | Passkey attestation tidak divalidasi | `attestation: 'none'` by design | Untuk enterprise, set `attestation: 'direct'` (config) |
| SEC-09 | Recovery codes di memory saat setup | Inherent TOTP setup flow | Dokumentasi; jangan cache codes |
| SEC-11 | TOTP secret plaintext di API | Wajib untuk setup QR | Dokumentasi; gunakan HTTPS |
| SEC-18 | Fallback token tanpa expiry | Hanya saat Sanctum tidak ada | Dokumen; wajib pakai Sanctum |
| SEC-19 | Email di reset URL | View concern (host) | Pastikan Blade pakai `{{ }}` escaped |
| SEC-20 | Passkey ID di URL | Sudah mitigasi (auth + user filter) | Tidak perlu |

### Difix di Tahap 2

| Ref | Fix |
|-----|-----|
| SEC-07 | Lockout pindah dari cache ke database (model `AccountLockout` + migration `2026_02_01_000008`). Multi-server/cache-flush tidak lagi bisa bypass lockout. |
| SEC-17 | Preset `config/authentication-hardened.php` — hardening untuk production (password kuat, lockout aktif, captcha aktif, email verify, auto_register=false). |
| SEC-22 (baru) | Fail-closed CAPTCHA: driver tidak dikenal/typo kini throw `AuthenticationConfigurationException`, bukan diam-diam fallback ke `NullCaptchaDriver` (hindari captcha nonaktif tanpa disadari). |

### Catatan PHPStan Pre-Existing
10 error di `Rules/LoginIdentifierRule`, `Rules/PasswordRule`, `Rules/SecurityPolicyRule` (cast array|string ke string) — SUDAH ADA sebelum audit ini, bukan dari perubahan fix. Perbaikan terpisah jika diminta.

---

---

## VERIFICATION: YANG SUDAH BENAR

| Aspek | Status | Catatan |
|-------|--------|---------|
| User Enumeration Protection | GOOD | Identical response/exception untuk existing vs non-existing user |
| SQL Injection Protection | GOOD | Eloquent ORM digunakan — parameterized queries |
| Session Fixation Protection | GOOD | `session()->regenerate()` dipanggil saat login |
| Password Hashing | GOOD | Bcrypt/Argon2 via Laravel Hasher dengan rehash |
| CSRF Protection | GOOD | `web` middleware group mencakup CSRF token |
| Rate Limiting (Login) | GOOD | Composite key (identifier + IP) — granular |
| TOTP Implementation | GOOD | RFC 6238 compliant, `hash_equals()` untuk timing-safe comparison |
| Recovery Codes Hashed | GOOD | Bcrypt/Argon2 hash, single-use consumption |
| 2FA Bypass via Session | GOOD | Pending token (opaque, hashed) menggantikan user_id |
| WebAuthn Challenge Anti-Replay | GOOD | Single-use challenge di-cache |
| WebAuthn Signature Verification | GOOD | ASN.1 DER + IEEE P1363 normalization |
| WebAuthn Sign Count (Clone Detection) | GOOD | Sign count increment + check |
| Audit Logging with PII Masking | GOOD | `SecurityHelper::maskIdentifier()` applied |
| SensitiveParameter Attribute | GOOD | #[SensitiveParameter] pada semua password arguments |
| Event Payloads (No Passwords) | GOOD | Events tidak mengandung password/token |
| HttpOnly Cookie (Device Trust) | GOOD | `HttpOnly: true` pada trust cookie |
| Strict SameSite (Device Trust) | GOOD | `SameSite: strict` pada trust cookie |
| Account Lockout (OTP) | GOOD | Checked sebelum login — OTP tidak bypass lockout |
| Account Lockout (Social) | GOOD | Checked setelah user resolve — social tidak bypass lockout |
| Guest Middleware (2FA Challenge) | GOOD | Public 2FA challenge routes menggunakan `guest` middleware |
| Auth Middleware (2FA Setup) | GOOD | Setup routes menggunakan `auth` middleware |
| Timing Normalization (Web Password Reset) | GOOD | `usleep(random_int(50_000, 150_000))` |

---

## REKOMENDASI PRIORITAS

### Prioritas 1 (Immediate — 1-2 hari)
1. **SEC-01**: Fix OTP atomic counter (race condition)
2. **SEC-02**: Tambahkan timing normalization di API password reset
3. **SEC-03**: Filter user object di registration API response

### Prioritas 2 (Before Production — 1 minggu)
4. **SEC-04**: Implementasi server-side token untuk 2FA device trust
5. **SEC-05**: Dokumentasi/validasi WebAuthn attestation
6. **SEC-06**: Remove stateless OAuth callback atau validasi state
7. **SEC-07**: Pertimbangkan persistent lockout storage
8. **SEC-08**: Whitelist field di LoginData.extra

### Prioritas 3 (Hardening — 2 minggu)
9. **SEC-09**: Recovery code invalidation setelah confirm
10. **SEC-10**: Remove token dari password reset JSON fallback
11. **SEC-12**: Pastikan cookie flags dikonfigurasi
12. **SEC-13**: Filter headers di AuthenticationContext
13. **SEC-14**: Validasi email verification dari OAuth provider

### Prioritas 4 (Best Practice — ongoing)
14. **SEC-17**: Tambahkan hardened config preset
15. **SEC-18**: Token expiry untuk fallback tokens
16. **SEC-19**: Escape email di password reset views
17. **SEC-21**: Validasi protocol di WebAuthn origin

---

*End of Security Audit Report*
