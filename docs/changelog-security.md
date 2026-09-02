# Security Changelog — Comprehensive Remediation

All 13 security audit findings have been systematically resolved across all package layers.

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
