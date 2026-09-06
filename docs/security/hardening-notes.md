# Security Changelog — Comprehensive Remediation

All security audit findings have been systematically resolved across all package layers.

---

### [Remediated Findings]

- **SA-01 (HIGH) — Passkey Cryptographic Signature & Ceremony Verification**:
  - Implemented strict OpenSSL cryptographic signature verification over `authenticatorData || SHA256(clientDataJSON)`.
  - Added single-use anti-replay challenge consumption, `rpIdHash` checks, `UP` and `UV` flag checks, and cloned authenticator sign counter tracking.
- **SA-02 (HIGH) — Passkey Registration Fallback Removal & Public Key Validation**:
  - Removed arbitrary JSON fallback on registration.
  - Implemented strict PEM/SPKI/COSE normalization and validation.
- **SA-03 (MEDIUM) — OTP User Enumeration Mitigation**:
  - Normalized `sendOtp` and `verifyOtp` response payloads and timings for existing vs non-existent accounts.
  - Dispatches OTP emails and events only when an account is associated.
- **SA-04 (MEDIUM) — Social Auth Exception Message Sanitization**:
  - Replaced raw `$e->getMessage()` leaks in redirects and API responses with normalized error messages and internal logging (`report($e)`).
- **SA-05 (MEDIUM) — Multi-Tenant Session Ownership Verification**:
  - Enforced strict user scoping across session revocation and added cross-user deletion regression tests (IDOR resistance).
- **SA-06 (MEDIUM) — Device Trust Defense-in-Depth**:
  - Subnet-bound fingerprinting, multi-column validation, and strict cryptographic token comparison via `hash_equals()`.
- **SA-07 (MEDIUM) — Passkey Login Options Credential & User Enumeration**:
  - Standardized on discoverable credentials (`allowCredentials: []`) on unauthenticated endpoints to prevent leaking registered credential IDs.
- **SA-08 (LOW) — OTP Verification Rate Limit Persistence**:
  - Added independent rate limit protection preventing attempts bypass across consecutive OTP requests.
- **SA-09 (LOW) — 2FA Recovery Code Entropy & Hashed Storage**:
  - Increased recovery code length to 10 characters formatted (`XXXXX-XXXXX`).
  - Stored recovery codes hashed using Bcrypt/Argon2 with backward compatibility for legacy plaintext codes.
- **SA-10 (LOW) — `apiResetPassword` Translation Leak Mitigation**:
  - Normalized password reset API failure responses to generic message without revealing user existence.
- **SA-11 (LOW) — Strategy Whitelisting & Probing Prevention**:
  - Added strict validation rule to `LoginRequest` against configured strategies.
  - Caught `InvalidStrategyException` cleanly as generic invalid credentials.
- **SA-12 (LOW) — Session Introspection Sanitization**:
  - Replaced raw Eloquent model serialization in `SessionController::index()` with a safe, whitelisted user attribute representation.
- **SA-13 (INFO) — Device Trust Cookie SameSite Hardening**:
  - Upgraded cookie `SameSite` attribute to `Strict`.
- **SA-14 (MEDIUM) — API User Payload Sanitization (SEC-03 sweep)**:
  - `LoginController`, `PasskeyController`, `TwoFactorChallengeController`, `OtpController`, `SocialAuthController` no longer serialize raw Eloquent models (which exposed `password` hash + `remember_token`) in JSON responses.
  - New `Support\SafeUserPresenter` whitelist: `id`, `name`, `email`, `username` only.
- **SA-15 (MEDIUM) — Internal Exception Message Leaks Stopped**:
  - Removed `$e->getMessage()` passthrough from OTP, Social Auth, Passkey, and Registration controllers (web + API). Clients now receive generic messages; internals go to `report()`.
  - Added `passkey_registration_failed` lang key (en + id).
- **SA-16 (MEDIUM) — Fail-Closed API Token Generation**:
  - `TokenService` no longer returns a dummy `Str::random(64)` token that was never persisted. Without Sanctum `HasApiTokens` it throws `AuthenticationConfigurationException` (fail-closed per AGENTS.md invariant).
  - `AuthenticationService` + `PasskeyService` now type-hint `TokenManagerInterface` (decoupled, testable).
- **SA-17 (MEDIUM) — Opaque Single-Use 2FA Pending Token**:
  - New `Support\TwoFactorPendingToken` replaces inline `Str::random` + raw cache keys in `LoginController`, `OtpController`, `SocialAuthController`, `TwoFactorChallengeController`.
  - Cache key is `sha256(token)` — raw token never used as key; token consumed after verification (anti-replay).
  - TTL configurable via `authentication.features.two_factor.pending_token_ttl_minutes` (default 10).
  - `two_factor` rate limit strategy upgraded from `ip` to `composite` (user+IP) to stop brute force across rotating IPs.
- **SA-18 (MEDIUM) — Dead Middleware Revived**:
  - `EnsureSessionSecurity`, `CheckAccountLockout`, `AuthenticateWithCustomGuard` existed but were never registered/attached (dead code). Now aliased as `authentication.session-security`, `authentication.lockout`, `authentication.guard`.
  - `authentication.session-security` auto-attached to package web + api route groups — all auth pages now send `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`.
  - Host apps should attach `authentication.lockout` to their authenticated route groups.
- **SA-19 (INFO) — TwoFactor Recovery Code Timing Note**:
  - Reviewed: bcrypt check vs legacy plaintext comparison in recovery-code loop is not exploitable due to composite rate limiting; noted for future hardening.
