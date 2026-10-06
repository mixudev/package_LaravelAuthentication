# Security Audit Report: Authentication Package

Date: 2026-10-06
Package: mixudev/laravel-authentication
Scope: localization, views, authentication flows, concurrency, sensitive data, queued mail, configuration compatibility

## Result

Status: PASSED WITH DOCUMENTED RESIDUAL RISKS

Verification:

- PHPUnit: 444 tests, 1242 assertions, passed
- PHPStan level 8: passed with no errors
- PHP syntax: modified PHP files validated through the test and analysis gate
- `git diff --check`: clean before commit
- No secrets or credentials inspected or committed

## Confirmed issues fixed

1. Stale published view aliases returned API JSON for GET pages. Canonical view fallback now preserves HTML rendering.
2. Email verification referenced a missing view. The canonical view and config entry now exist.
3. An unterminated layout `<style>` block consumed the document body. The layout contract test now guards tag balance.
4. User-facing English literals bypassed `APP_LOCALE`, including controllers, middleware, services, validation labels, DTOs, and exceptions. The localization catalogue now has matching professional `en` and `id` keys.
5. Queued mail rendered in the worker locale instead of the request locale. Mailables now capture and restore locale at render time.
6. Empty configured mail subjects produced empty subjects because Laravel config defaults do not replace an existing null value. Localized defaults now apply unless a non-empty override exists.
7. Localization scanning missed nested `src/Services` files and thrown exception messages. The contract test is recursive and covers those exception patterns.
8. Duplicate translation keys silently shadowed earlier definitions. Duplicate-key detection was added and the duplicate definitions were removed.
9. Circuit-breaker counters used non-atomic read-modify-write operations. Counters now use `Cache::add` for TTL seeding followed by `Cache::increment`.
10. Sensitive OTP, CAPTCHA, 2FA, recovery, and opaque pending-token parameters lacked `#[SensitiveParameter]`. The attribute is now applied across interfaces and implementations.

## Attack-surface checks

- Login, registration, password reset, OTP, 2FA, passkey, social login, email verification, session management, and custom-guard middleware were reviewed through routes, controllers, services, and security tests.
- Anti-enumeration response assertions remain byte-for-byte and were kept green.
- Social login uses session-backed state; no `stateless()` bypass was found.
- Event and public DTO scans found no password, raw token, OTP, or secret payloads.
- No cache-lock implementation used the known `method_exists` lock-detection pitfall.
- View resolution remains O(1) after Laravel view/config caches are warm and performs no database or network I/O.
- Localization calls add catalogue lookup only; no authentication, hashing, rate-limit, session, or queue algorithm changed.

## Residual risks and follow-up

- Circuit-breaker atomicity is proven by implementation and sequential tests. A multi-process Redis/Memcached test would provide runtime proof of contention behavior.
- The package's existing rate-limit model should be reviewed separately for account-only and IP-only dimensions if the deployment threat model includes distributed brute force or credential stuffing.
- `php artisan serve` could not be used in this CLI environment because the process requires a TTY. HTTP behavior is covered by the feature/security test suite.
- No tag or remote push was performed.

## Commit

9a0b3f7 fix(security): apply SensitiveParameter to OTP, CAPTCHA, 2FA, and token parameters
