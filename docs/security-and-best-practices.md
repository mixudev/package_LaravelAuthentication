# Security Architecture & Threat Mitigations

The package is engineered following zero-trust, fail-closed principles to defend against common authentication attack vectors.

---

## 1. Threat Mitigation Matrix

| Threat | Attack Vector | Package Defense Mechanism |
| :--- | :--- | :--- |
| **Credential Guessing / Brute Force** | High-velocity password guessing | Granular per-feature rate limiting (`login`, `otp`, `registration`, `forgot_password`, `two_factor`) with composite SHA-1 keys. |
| **User Enumeration** | Timing discrepancy or error differential probing | Uniform timing, identical `InvalidCredentialsException`, and identical user-facing messages. |
| **Credential Stuffing & Botnets** | Automated credential spraying from leaked DBs | Adaptive CAPTCHA protection (Turnstile, reCAPTCHA, hCaptcha) triggered upon $N$ consecutive failures. |
| **Session Hijacking & Forgotten Devices** | Stale sessions on public or compromised devices | Active session & device manager (`/auth/sessions`) with remote revocation and *Logout All Other Devices*. |
| **Credential Interception (Password Stolen)** | Attacker possesses valid user password | Multi-Factor Authentication (RFC 6238 TOTP & encrypted backup codes) with Device Trust cookies. |
| **Session Fixation** | Attacker pre-determines victim's session ID | Native `session()->regenerate()` immediately upon valid login and complete cookie invalidation on logout. |
| **Privilege Escalation on Sensitive Actions** | Attacker accesses an open authenticated dashboard | Re-authentication middleware (`password.confirm`) requiring password re-verification every 15 minutes. |
| **Credential Leakage in Stack Traces/Logs** | Plaintext secrets in exceptions or logs | PHP 8.2+ `#[\SensitiveParameter]` attributes across all password arguments and automated PII redaction. |
| **Password Reuse** | Immediate revert to compromised passwords | Encrypted `password_histories` tracking the last *N* previous passwords. |
| **Timing Attacks** | Non-constant time string comparisons | Cryptographic `hash_equals` across all token, hash, OTP, and 2FA verifications. |
| **Suspicious Location / Device Logins** | Unauthorized access from unknown device or foreign IP | Automated device fingerprinting (`DeviceDetector`) and immediate email alert dispatch (`NewDeviceLoginMail`). |

---

## 2. Granular Rate Limiting Architecture

Unlike basic auth packages that share a single login throttle counter, this package provides dedicated, isolated rate limiters for every authentication pathway in `config/authentication.php` under `security.rate_limits`:

- **`login`**: Protects standard credential verification.
- **`registration`**: Prevents fake account creation and bot spamming.
- **`otp_request`**: Eliminates OTP-bombing attacks and SMS/email quota exhaustion.
- **`otp_verify`**: Prevents brute-forcing numeric OTP tokens.
- **`forgot_password`**: Throttles password reset email spam.
- **`two_factor`**: Protects against 6-digit TOTP guessing.
- **`confirm_password`**: Protects sensitive action re-auth screens.

### Strategy Options:
- **`composite`** *(Recommended)*: Combines `sha1($normalizedIdentifier . '|' . $ip)`. Protects legitimate users on shared corporate/NAT networks from being affected by brute-force attacks on other accounts.
- **`ip`**: Throttles requests based purely on client IP address.
- **`identifier`**: Throttles requests based solely on username/email.

---

## 3. Adaptive CAPTCHA & Abuse Thresholds

Rather than imposing intrusive CAPTCHAs on every login attempt (which degrades user experience), the package features an **Adaptive Abuse Threshold**:

```php
'captcha' => [
    'enabled'                       => true,
    'driver'                        => 'turnstile', // 'turnstile', 'recaptcha_v2', 'recaptcha_v3', 'hcaptcha'
    'trigger_after_failed_attempts' => 3,           // Only required after 3 consecutive failures
    'site_key'                      => env('AUTH_CAPTCHA_SITE_KEY', ''),
    'secret_key'                    => env('AUTH_CAPTCHA_SECRET_KEY', ''),
],
```

- Legitimate users log in seamlessly without solving CAPTCHAs.
- Repeated failed attempts immediately activate the CAPTCHA requirement before reaching hard account lockout.

---

## 4. Multi-Factor Authentication & Device Trust

- **RFC 6238 Standard**: 100% pure PHP implementation with zero external API dependencies.
- **Single-Use Recovery Codes**: Encrypted in the database; automatically consumed and removed once used.
- **Device Trust (Remember Device)**: Sets an encrypted, HttpOnly HMAC-signed cookie (`auth_trusted_device`) tied to the user ID and hardware/network fingerprint. Even if the cookie is stolen, it cannot be reused from an unmatched IP subnet or device profile.

---

## 5. Sensitive Action Re-Authentication (`password.confirm`)

For critical application routes (e.g. billing, API keys, security settings, user deletion):

```php
Route::middleware(['auth', 'password.confirm'])->group(function () {
    Route::get('/admin/security', [AdminSecurityController::class, 'index']);
    Route::post('/user/delete', [UserController::class, 'destroy']);
});
```

The middleware checks the `auth.password_confirmed_at` session timestamp against the configured timeout (default: 900 seconds / 15 minutes). If expired, Web users are smoothly redirected to `/confirm-password` and returned to their intended URL upon success, while API clients receive a `423 Locked` status code with `password_confirmation_required: true`.
