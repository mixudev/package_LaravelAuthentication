# Security Policy & Architecture

## Core Security Controls & Design Invariants

The `mixudev/laravel-authentication` package provides enterprise-grade authentication and identity protection adhering to strict zero-trust security invariants:

1. **Zero Hardcoded Coupling**: Never binds to `App\Models\User` or concrete application implementations. All integrations resolve through `config('authentication.user_model')`, contracts, and dependency injection.
2. **Fail-Closed by Design**: Unregistered login strategies, malformed signatures, or corrupted parameters reject immediately with normalized error states.
3. **Sensitive Parameter Redaction**: Raw passwords and credentials use PHP 8.2+ `#[\SensitiveParameter]` attributes and automated scrubbing in audit logs.
4. **Timing-Attack Resistance**: Timing normalization and generic responses for user enumeration mitigations (`usleep(random_int(...))`, `hash_equals()`).
5. **Stateful Session Security**: Automatic session ID regeneration upon login (`session()->regenerate()`), session fixation defense, and secure session destruction.
6. **Multi-Factor & WebAuthn / Passkeys**:
   - WebAuthn Level 2/3 cryptographic verification (ES256, RS256, EdDSA).
   - Single-use anti-replay challenges with TTL cache expiration.
   - Cloned authenticator detection via monotonic sign counter checks.
   - Hashed single-use backup recovery codes.
7. **Device Trust & Fingerprinting**:
   - `SameSite=Strict`, `HttpOnly=true`, and `Secure` cookie flags.
   - Multi-tenant ownership enforcement preventing cross-user revocation (IDOR resistance).
8. **Multi-Vector Rate Limiting**:
   - Composite rate limits (SHA1 hash of normalized identifier + IP).
   - Persistent OTP request and verify rate limiting.

---

## Security Vulnerability Reporting

If you discover a potential security issue within this package, please contact the maintainer team directly via security advisory rather than opening a public issue.
