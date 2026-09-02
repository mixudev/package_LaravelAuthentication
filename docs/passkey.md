# FIDO2 / WebAuthn Passkey Authentication

## Overview

The `mixudev/laravel-authentication` package provides native, cryptographic FIDO2 / WebAuthn Level 2 / Level 3 passkey support without heavy third-party runtime bloat.

---

## Cryptographic Guarantees & Verification Pipeline

When a user registers or authenticates with a passkey, the verification layer enforces:

1. **Client Data Validation**:
   - Verification of `type` (`webauthn.create` for registration, `webauthn.get` for authentication).
   - Single-use, time-bound challenge verification (invalidated immediately after first consumption to prevent replay attacks).
   - Origin verification matching the configured Relying Party host (`rp_id`).
2. **Authenticator Data Inspection**:
   - SHA-256 hash match against configured RP ID (`rpIdHash`).
   - User Present (`UP`) bit flag validation (authenticator must confirm human presence).
   - User Verified (`UV`) bit flag validation against configured policy (`preferred` / `required`).
   - Monotonic sign counter tracking to detect and reject cloned hardware/software authenticators.
3. **Cryptographic Signature Verification**:
   - High-assurance OpenSSL verification of the assertion signature over `authDataRaw || SHA256(clientDataJSON)`.
   - Native support for ES256 (P-256 ECDSA), RS256 (RSA 2048/4096), and EdDSA (Ed25519) algorithm parameters.
4. **Discoverable Credentials / Passkey Privacy**:
   - Public unauthenticated `/passkey/login-options` endpoints default to empty `allowCredentials: []`, enabling native browser discoverable credentials while eliminating user enumeration and credential ID disclosure risks.

---

## Registration Workflow (Ceremony)

```
1. Client calls POST /auth/passkey/register-options (Authenticated)
2. Server generates challenge, sets 5-minute TTL cache, returns PasskeyCreationOptions DTO
3. Browser invokes navigator.credentials.create()
4. Client sends assertion payload to POST /auth/passkey/register
5. Server validates clientDataJSON, challenge, origin, parses & normalizes SPKI/COSE public key
6. Credential saved in database (credential_id, normalized PEM public_key, sign_count = 0)
```

---

## Authentication Workflow (Ceremony)

```
1. Client calls POST /auth/passkey/login-options
2. Server generates single-use challenge, returns PasskeyRequestOptions DTO
3. Browser invokes navigator.credentials.get()
4. Client sends assertion response to POST /auth/passkey/login
5. Server validates challenge (consumes immediately), verifies UP/UV flags & RP ID hash
6. Server cryptographically verifies signature using stored PEM public key
7. Sign counter updated, session/token created, LoginSucceeded event dispatched
```
