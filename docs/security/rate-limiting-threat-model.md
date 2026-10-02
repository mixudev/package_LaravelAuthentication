# Rate Limiting Threat Model

## Purpose

This document defines the threat model, attack scenarios, and architectural invariants for the enterprise-grade multi-layer authentication abuse-resistance system in `mixudev/laravel-authentication`.

## Attack Scenarios

### 1. Single Account, Single IP (Credential Guessing)
**Attack**: Attacker attempts to guess password for `user@example.com` from `203.0.113.5`.

**Expected Controls**:
- Account+IP composite limiter (current): 5 attempts per 1 minute.
- Account-only limiter: Independent budget to prevent guessing even if IP rotates.
- Adaptive challenge: CAPTCHA after N failures from same context.

**Bypass Attempts**: None effective; single-dimension attack blocked by current composite strategy.

---

### 2. Single Account, Many IPs (Distributed Brute Force)
**Attack**: Attacker attempts `user@example.com` from 100 different IPs (rotating proxies, VPN, botnet).

**Expected Controls**:
- Account-only limiter: Hard cap on attempts per identifier regardless of source IP.
- Velocity detection: Rapid attempts from diverse IPs trigger challenge/lockout.
- Account lockout: After total failures exceed threshold across all IPs.

**Current Vulnerability**: Composite `hash(identifier|ip)` allows attacker to bypass limit by rotating IP. Each new IP gets fresh 5-attempt budget.

**Mitigation Priority**: **CRITICAL** — Add account-dimension limiter.

---

### 3. Many Accounts, Single IP (Credential Stuffing / Enumeration)
**Attack**: Attacker tries 10,000 username/password pairs from one IP (credential stuffing list).

**Expected Controls**:
- IP-only or network-prefix limiter: Hard cap on total login attempts from one IP.
- Distributed account detector: High distinct-identifier count from one IP triggers block.
- CAPTCHA: Universal challenge for this IP after threshold.

**Current Vulnerability**: Composite strategy allows attacker to rotate identifiers. Each email gets independent 5-attempt budget from same IP.

**Mitigation Priority**: **CRITICAL** — Add network-dimension limiter and distributed-attack detection.

---

### 4. Many Accounts, Many IPs (Large Botnet)
**Attack**: 10,000 bots each try different credentials from different IPs.

**Expected Controls**:
- Global platform capacity limiter: Emergency circuit breaker for extreme load.
- Client/application isolation: One compromised client app cannot exhaust platform budget.
- Velocity baseline: Sudden spike in total failures across system triggers alert.
- Challenge escalation: Adaptive CAPTCHA or device trust verification.
- Reputation signals: ASN/IP reputation, known proxy/VPN/Tor exit nodes (optional).

**Inherent Limitation**: No server-side key alone defeats a large, distributed botnet. Defense requires upstream WAF, CDN rate limiting, MFA, and reputation feeds.

**Mitigation Priority**: **HIGH** — Multi-layer defense; no single silver bullet.

---

### 5. Rotating IPv6 Privacy Addresses
**Attack**: User or attacker rotates IPv6 address every request due to privacy extensions (RFC 4941).

**Expected Controls**:
- IPv6 prefix normalization: Key on `/64` or `/48` prefix, not full address.
- Account-dimension limiter: Primary control is per-identifier, not per-IP.
- Shared-network protection: Do not hard-block entire IPv6 prefix; use account limits.

**Current Behavior**: Composite key uses full IP; legitimate user rotating IPv6 gets fresh budgets but is still limited per account.

**Mitigation Priority**: **MEDIUM** — Document and test IPv6 normalization strategy.

---

### 6. Forged `X-Forwarded-For` Header
**Attack**: Attacker behind reverse proxy sends custom `X-Forwarded-For: 1.1.1.1` to spoof IP.

**Expected Controls**:
- Trusted proxy configuration: Package NEVER parses forwarded headers directly.
- Host responsibility: Laravel `TrustedProxies` middleware must sanitize headers.
- Socket peer fallback: If no trusted proxy configured, use direct connection IP.
- Documentation: Explicitly warn about misconfigured proxies.

**Current Behavior**: Package uses `$request->ip()` which respects Laravel trusted-proxy config.

**Mitigation Priority**: **CRITICAL** — Document trust boundary; add integration test.

---

### 7. Shared NAT / Corporate Network
**Scenario**: 500 legitimate users share one office IP `198.51.100.10`.

**Expected Controls**:
- Account-dimension primary: Limit per user, not per IP.
- Network budget higher threshold: IP limiter allows more attempts than single-user budget.
- No blanket IP block: One bad actor does not lock out entire office.

**Current Risk**: Pure IP strategy would create denial-of-service for entire office.

**Mitigation Priority**: **MEDIUM** — Ensure account limiter is primary; IP limiter is secondary capacity protection.

---

### 8. Credential Stuffing with Valid-Looking Identifiers
**Attack**: Attacker tests real email addresses from data breach against common passwords.

**Expected Controls**:
- All layer limits apply (account, IP, network, global).
- CAPTCHA after threshold: Friction to slow automation.
- Account enumeration protection: Identical error messages and timing for valid/invalid accounts.

**Current Behavior**: Composite limiter slows attack but does not prevent rotation.

**Mitigation Priority**: **HIGH** — Add enumeration-equivalent responses and distributed detection.

---

### 9. CAPTCHA Farming / Replay
**Attack**: Attacker solves CAPTCHA once, replays token, or uses solving service.

**Expected Controls**:
- Single-use CAPTCHA token: Consumed on first verification.
- Token expiry: Short-lived (5 minutes).
- Token bound to session/IP: Cannot transfer between clients.
- Challenge rate-limited: CAPTCHA endpoint itself has budget.

**Mitigation Priority**: **MEDIUM** — Implement when adding adaptive challenge.

---

### 10. Redis / Cache Outage
**Scenario**: Distributed cache becomes unavailable; all counter state lost.

**Expected Controls**:
- Fail mode per feature: Security-critical features (OTP, password reset, token issuance) fail closed (deny).
- Login availability mode: Configurable fail-safe to allow login without counters (accept risk).
- Health check: Detect cache unavailability and alert/log.
- Graceful degradation: Document trade-off between security and availability.

**Current Risk**: Cache outage allows unlimited attempts until restored.

**Mitigation Priority**: **HIGH** — Define fail-mode policy per feature.

---

### 11. Multi-Tenant / Multi-Client Isolation
**Scenario**: Package deployed for 100 client applications sharing one authentication backend.

**Expected Controls**:
- Client namespace isolation: Client A's abuse does not consume Client B's budget.
- Per-client limits: Each client has independent account/network/global budgets.
- Platform circuit breaker: Separate emergency global cap protects infrastructure.

**Current Risk**: Single global counter shared across all clients.

**Mitigation Priority**: **HIGH** — Add client-scoped counters.

---

### 12. Account Enumeration via Timing / CAPTCHA / Error Messages
**Attack**: Attacker infers account existence through different response times, CAPTCHA requirement, or error messages.

**Expected Controls**:
- Constant-time lookup: Hash-based identifier resolution.
- Identical errors: "Invalid credentials" for wrong password, non-existent account, locked account.
- Consistent CAPTCHA: Challenge applies to IP/network, not specific account.
- Timing normalization: Dummy hash operations for non-existent accounts.

**Current Behavior**: Package implements timing-equivalent validation and generic errors.

**Mitigation Priority**: **LOW** — Already mitigated; verify no regressions.

---

## Architectural Invariants

### 1. Atomic Counters
All rate-limit counters MUST be atomic operations. No separate read-check-write race conditions.

**Implementation**: Use Redis `INCR`, Laravel `RateLimiter` atomic methods, or database pessimistic locks.

---

### 2. Namespace Isolation
Counter keys MUST include:
- Feature name (login, otp_request, password_reset, etc.)
- Client/application ID (when provided by host)
- Dimension (account, account_ip, network, global)

**Format**: `auth_rl:{feature}:{client_id}:{dimension}:{hashed_subject}`

---

### 3. No Raw Secrets in Keys or Logs
Counter keys, event payloads, and logs MUST NOT contain:
- Raw email addresses or usernames
- Raw IP addresses (use hashed or network prefix)
- Passwords, tokens, CAPTCHA responses
- Full user agents or fingerprints

**Allowed**: SHA-256 hashes, network prefixes (`/24`, `/64`), bounded enums.

---

### 4. Success Clears Only Relevant State
Successful login clears:
- ✅ Account+IP composite counter for that user
- ✅ Account-only counter for that user
- ❌ NOT network-wide IP counter (other users unaffected)
- ❌ NOT global platform counter

---

### 5. Fail-Closed by Default for Security-Critical Features
When cache/store is unavailable:
- **Fail closed** (deny): OTP issuance, password reset tokens, 2FA setup, API token creation
- **Fail safe** (allow): User login (configurable; documented risk trade-off)

---

### 6. No Client-Provided Fingerprint as Primary Identity
Browser fingerprints, device IDs, and client-supplied tokens:
- ✅ MAY be used as weak correlation signals
- ❌ MUST NOT be sole deny/allow decision
- ❌ MUST NOT bypass account or network limits
- ✅ Server-issued device tokens preferred over client-computed fingerprints

---

### 7. Identical Public Errors
User-facing authentication errors MUST be:
- Generic: "Invalid credentials" or "Too many attempts"
- Timing-equivalent: Same response time for valid/invalid accounts
- CAPTCHA-agnostic: Challenge requirement not tied to account existence

Internal reason codes for metrics only; never exposed in API/HTML.

---

### 8. Defense in Depth, Not Single Point of Failure
Rate limiting is ONE layer. Other required controls:
- Account lockout (persistent, separate from rate limiting)
- MFA (TOTP, passkey, email OTP)
- Password complexity and breach detection
- Session management and device trust
- Upstream WAF / CDN protection
- Anomaly detection and alerting

---

## Out of Scope (Not Addressed by Package)

1. **Infrastructure DDoS**: Application-layer rate limiting cannot stop network-layer floods. Use CDN/WAF.
2. **Perfect Botnet Detection**: Large distributed botnets with diverse IPs/fingerprints require reputation feeds, behavioral analysis, and ML models beyond this package.
3. **Client-Side Security**: XSS, CSRF, clickjacking protection is separate concern.
4. **Data Breach Response**: Credential rotation, forced password resets after breach disclosure.

---

## References

- OWASP Authentication Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
- NIST SP 800-63B: Digital Identity Guidelines (Authentication)
- RFC 6749: OAuth 2.0 (rate limiting and token issuance)
- Laravel Trusted Proxies: https://laravel.com/docs/requests#configuring-trusted-proxies

## Trusted Client IP Boundary

### Responsibility

The package relies on Laravel's `Request::ip()` method (`src/DTO/AuthenticationContext.php`) to determine the client IP address. The package does not parse `X-Forwarded-For`, `Forwarded`, `X-Real-IP`, or any other proxy header directly.

### Security Implications

1. Trust is infrastructure-controlled through the host application's `TrustProxies` middleware.
2. If the host trusts all public proxies, an attacker can spoof forwarded headers. This is a host deployment error, not a package-level trust decision.
3. When no trusted proxy is configured, the framework uses the direct TCP peer address for completed HTTP connections.
4. IP-based limits assume the host has configured trusted proxies correctly; the package cannot detect an incorrect proxy allowlist.

### Host Deployment Requirements

- Configure only known load balancer, CDN, or reverse-proxy CIDRs. Do not trust `*` on an untrusted public path.
- Ensure the proxy tier strips and rewrites forwarded headers before forwarding requests.
- Configure `TrustProxies` when the application is behind a load balancer; otherwise the limiter sees the proxy address rather than the client address.
- Do not log raw IPs or forwarded-header values. Use hashed network buckets for telemetry.

### Package Boundary

`AuthenticationContext::fromRequest()` calls `(string) $request->ip()` and carries that canonical value to security services. All future rate-limit key factories must consume this value and must never re-parse request headers. Laravel's trusted-proxy configuration is therefore the single authority for client-IP trust.

| Deployment state | Risk | Required action |
|---|---|---|
| Trusts every proxy on a public path | High: attacker can choose the apparent IP | Restrict trusted proxy CIDRs |
| No trusted proxy behind a load balancer | High: all users appear as the balancer | Configure the host middleware |
| Explicit trusted proxy allowlist | Low: trust is limited to infrastructure | Keep the allowlist current |
