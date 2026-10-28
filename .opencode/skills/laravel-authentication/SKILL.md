---
name: laravel-authentication
platform: opencode
description: Package-specific architecture, security, extension, testing, and maintenance guide for mixudev/laravel-authentication. Load when working anywhere in this repository.
---

# Laravel Authentication Package Skill

## Language

Respond to the user in Bahasa Indonesia unless the user explicitly requests another language. Keep code identifiers, file paths, commands, URLs, and error messages unchanged.

## Package identity

- Composer: `mixudev/laravel-authentication`
- Namespace: `Vendor\\LaravelAuthentication\\`
- Type: portable Laravel library
- Runtime: PHP 8.2–8.5+, Laravel Illuminate 11–13
- Main provider: `Vendor\\LaravelAuthentication\\Providers\\AuthenticationServiceProvider`
- Main config: `config/authentication.php`
- Package documentation index: `docs/index.md`
- Agent policy: `AGENTS.md`

## First actions in every session

1. Read `AGENTS.md` and this skill.
2. Inspect `git status --short` before editing.
3. Locate the relevant service, contract, controller, route, config section, and tests before changing code.
4. Prefer the smallest existing extension point; do not add an abstraction with one implementation.
5. Preserve package portability: never couple source code to a host application's namespace.
6. Check the current configuration and documentation because this package evolves frequently.

## Source architecture

- `src/Contracts/`: public interfaces and host integration seams.
- `src/DTO/`: immutable, readonly request/result/context objects.
- `src/Enums/`: domain constants and statuses.
- `src/Events/`: lifecycle events; payloads must not contain passwords, raw reset tokens, OTPs, or API secrets.
- `src/Exceptions/`: typed authentication failures; fail closed.
- `src/Http/Controllers/`: web and API endpoints.
- `src/Http/Requests/`: validation at HTTP trust boundaries.
- `src/Http/Middleware/`: session security, lockout, custom guard, throttling, and password confirmation.
- `src/Models/`: package-owned persistence models.
- `src/Repositories/`: persistence abstractions.
- `src/Rules/`: reusable Laravel validation rules.
- `src/Services/Core/`: authentication orchestration, credential resolution/validation, registration, tokens.
- `src/Services/Security/`: rate limits, abuse policy, CAPTCHA, lockout, audit.
- `src/Services/Session/`: device detection/trust, sessions, security.
- `src/Services/TwoFactor/`: TOTP and 2FA lifecycle.
- `src/Services/Otp/`: OTP workflows.
- `src/Services/Passkey/`: WebAuthn/passkey workflows.
- `src/Services/Password/`: password policy, history, reset-related logic.
- `src/Services/Social/`: optional social authentication.
- `src/Strategies/`: identifier-based authentication strategies.
- `src/Support/`: config, registry, route naming, normalization, pending tokens.
- `routes/`, `database/`, `resources/`: package-owned integration assets.
- `tests/Unit/`: isolated behavior; `tests/Feature/`: Laravel workflows; `tests/Security/`: adversarial/security guarantees; `tests/Concurrency/`: race and atomicity behavior.

## Request and login flow

The intended login pipeline is:

1. Form/JSON request validation.
2. Feature and abuse rate limiting.
3. `AuthenticationService` orchestration and `LoginAttempted` dispatch.
4. Strategy lookup through `AuthenticationStrategyRegistry`.
5. Identifier normalization.
6. User lookup through `CredentialResolver`.
7. Account lockout check.
8. Password verification and optional automatic rehash.
9. On failure: record attempt, evaluate lockout, dispatch failure event, throw `InvalidCredentialsException`.
10. On success: clear counters, regenerate web session or issue API token, dispatch success event, audit, return `AuthenticationResult`.

Other major flows include registration, password reset, OTP passwordless auth, TOTP 2FA, trusted devices, session management, social login, and passkeys/WebAuthn.

## Non-negotiable invariants

- Every PHP file starts with `declare(strict_types=1);`.
- Never import `App\\Models\\User`, `App\\Http\\*`, or any host namespace in package source. Resolve the user model through `authentication.user_model`, contracts, or dependency injection.
- Unknown strategies, malformed configuration, unknown channels, and invalid states throw explicit typed exceptions. Never silently choose an insecure fallback.
- Every raw password, token, secret, or equivalent sensitive argument uses PHP `#[\\SensitiveParameter]` where applicable.
- Never put plaintext passwords, reset tokens, OTPs, or API secrets in logs, events, exception messages, mail data, or DTOs.
- Non-existent users and bad credentials have the same externally observable failure type/message and normalized timing.
- DTOs remain readonly and immutable.
- Session login regenerates the session ID; logout invalidates session data according to config.
- Token/OTP/TOTP/passkey comparisons use constant-time verification such as `hash_equals` or the library's equivalent.
- Rate-limit keys must not trust spoofable forwarded headers unless the peer is configured as a trusted proxy.
- Security state transitions must be atomic where races are possible; inspect concurrency tests before changing them.

## Configuration map

`config/authentication.php` controls:

- package enablement, guard, and `user_model`
- database migration loading and table names
- queued mail
- login strategies, identifier columns, and normalization
- password rules, rehash, and password history
- granular feature rate limits and abuse-policy dimensions
- global throttle, trusted proxies, CAPTCHA, new-device notifications
- account lockout and session security
- 2FA, OTP, passkey, social, routes, views, API, audit, and operational settings

`config/authentication.php` is the single package configuration source. Its defaults are hardened; host applications may override values in their published copy.

## Extension patterns

### New authentication strategy

1. Implement or extend the existing strategy contract/base class in `src/Strategies/`.
2. Use configured identifier columns and the existing normalizer/resolver behavior.
3. Register the strategy in the provider/config path; do not hardcode it in the orchestrator.
4. Add unit coverage for resolution and failure behavior plus a feature/security test when it crosses HTTP or trust boundaries.

### New security rule

Implement Laravel's validation rule contract in `src/Rules/`, attach it at the request boundary, and test both valid and malicious inputs.

### New service capability

Reuse existing contracts, config helpers, rate limiters, audit flow, and exception hierarchy. Add a new interface only when it is a real host integration seam or multiple implementations are expected.

## Testing and verification

Canonical commands from `AGENTS.md`:

- `php -l <modified-file>` for every modified PHP file
- `composer validate --strict`
- `vendor/bin/phpunit`
- `vendor/bin/phpstan analyse`

Use focused PHPUnit tests first, then the full suite. Security or concurrency changes require the relevant `tests/Security` and `tests/Concurrency` suites. Inspect `phpunit.xml` for active suites and environment. Do not weaken assertions, add forced passes, or introduce conditional skips to make a test pass.

## Change discipline

- No comments unless explicitly requested; preserve existing comments only when editing surrounding code.
- No unrelated refactors, dependency additions, generated artifacts, or documentation churn.
- Update `CHANGELOG.md` only when the repository's release/change protocol requires it.
- Never commit, amend, push, or create tags unless explicitly requested.
- Before finalizing, inspect `git diff` and `git status --short`.

## Quick navigation

- Architecture and threat model: `docs/security/architecture.md`
- Feature overview: `docs/features/overview.md`
- Passkeys: `docs/features/passkey.md`
- Installation: `docs/getting-started/installation.md`
- API routes: `docs/api/api-reference.md`
- Custom strategies: `docs/development/custom-strategies.md`
- Events: `docs/development/events-and-listeners.md`
- Hardening history: `docs/security/hardening-notes.md`
- Release process: `docs/operations/publishing-guide.md`
