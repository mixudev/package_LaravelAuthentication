# Security Architecture & Threat Mitigations

The package is engineered following zero-trust, fail-closed principles to defend against common authentication attack vectors.

---

## 1. Threat Mitigation Matrix

| Threat | Attack Vector | Package Defense Mechanism |
| :--- | :--- | :--- |
| **Brute Force** | High-velocity password guessing | Composite rate limiting (IP + Identifier SHA-1 key) with decay windows. |
| **User Enumeration** | Timing discrepancy or error differential probing | Uniform timing, identical `InvalidCredentialsException`, and uniform user-facing messages. |
| **Session Fixation** | Attacker pre-determines victim's session ID | Native `session()->regenerate()` immediately upon valid login. |
| **Credential Stuffing** | Automated credential spraying from leaked DBs | Account lockout policies with exponential backoff & rate limiting. |
| **Credential Leakage** | Plaintext secrets in exceptions or logs | `#[\SensitiveParameter]` attributes and automated PII redaction sinks. |
| **Password Reuse** | Immediate revert to compromised passwords | Encrypted `password_histories` tracking last *N* passwords. |
| **Timing Attacks** | Non-constant time string comparisons | Cryptographic `hash_equals` across all token, hash, and OTP verifications. |

---

## 2. Rate Limiting Strategies

Configure rate limiting strategy in `config/authentication.php`:

- **`composite`** *(Recommended)*: Combines `sha1($ip . '|' . $identifier)`. Protects valid users on shared NAT networks from being locked out by malicious actors.
- **`ip`**: Throttles based solely on client IP.
- **`identifier`**: Throttles based solely on account username/email.

---

## 3. Account Lockout

When enabled (`config/authentication.php` -> `security.account_lockout.enabled`), consecutive failed login attempts trigger an automated account lockout:

```php
'account_lockout' => [
    'enabled'               => env('AUTH_LOCKOUT_ENABLED', true),
    'max_failed_attempts'   => 5,
    'lockout_duration_mins' => 15,
    'auto_unlock'           => true,
],
```
When locked out, the package dispatches `Vendor\LaravelAuthentication\Events\AccountLocked` and safely rejects further attempts without hitting the database hasher.
