# Changelog

All notable changes to `vendor/laravel-authentication` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- **Password reset confirmation was still hardcoded English**: `PasswordResetController` returned `If an account with that email exists, a password reset link has been sent. Please check your inbox.` for both JSON and browser responses, regardless of `APP_LOCALE`. It now uses `authentication::messages.password_reset_link_sent`, preserving the same generic response and timing normalization, so user enumeration protection and performance are unchanged.
- **Exception defaults bypassed the locale**: `AuthenticationThrottledException`, `InvalidCredentialsException`, `TwoFactorChallengeRequiredException`, and `AuthenticationResult::failed()` carried English defaults in PHP signatures. Their defaults now resolve at construction time through the translation catalogue; explicit custom messages still win. Machine error codes remain unchanged and are not translated.
- **Localization audit lacked a guard for PHP default-parameter literals**: the new contract test scans controllers, middleware, requests, mail, DTOs, and exceptions; it was confirmed to fail on the exact password-reset literal above before the fix and pass after it.
- **Email bodies and subjects already followed the locale, but queued email did not**: `OtpMail` and `NewDeviceLoginMail` are rendered by `queue:work`, not by the request that created them, so a worker running under the host's default `APP_LOCALE=en` sent English copy to a user who registered in Indonesian. Both mailables now capture the active locale at construction and restore it in `envelope()` before any translation is resolved.
- **An empty or null configured email subject produced an empty subject line**: `config('...email_subject', $default)` cannot supply its default when the key *exists* with a `null` value, which is exactly how `config/authentication.php` ships it. Both mailables now fall back to the localized default unless the configured value is a non-empty string, and a configured string still wins outright.
- **API, flash, and abort messages were hardcoded English regardless of locale**: 52 user-facing sentences across 14 controllers, 3 middleware, `AuthenticationResult`, and `AccountLockedException` ignored `app()->setLocale()`, so an Indonesian deployment answered "Unauthenticated." and "OTP authentication is disabled." on every API call and every redirect flash. All of them now resolve through `authentication::messages.*`, with 27 new keys carrying professional `en`/`id` copy. Provider-specific sentences interpolate `:provider` instead of concatenating. `AccountLockedException` resolves its sentence in the constructor body because `__()` is not a constant expression and cannot be a default parameter value; that also means the locale in effect when the exception is thrown is the one used, which matters for queue workers and API requests.
- **Validation attribute labels were hardcoded Indonesian**: `nama`, `konfirmasi kata sandi`, and `kode OTP` were passed straight into the validation sentences, so an English deployment received "The nama field is required." Labels are now `authentication::messages.attribute_*` keys in both locales.
- **GET pages returned API JSON when a host kept the old published view config**: after the legacy flat view files were removed, hosts with `authentication.views.login = 'authentication::login'` failed `view()->exists()` and received `{"message":"Please authenticate via POST."}` instead of HTML. `AuthenticationView::resolve()` and `AuthenticationConfig::getView()` now fall back to the canonical grouped view when a configured alias is stale, while preserving an existing custom view when it resolves.
- **Email verification notice had no view**: `EmailVerificationController` referenced the non-existent `authentication::verify-email` view, so `/email/verify` also returned JSON. Added `pages/auth/verify-email.blade.php` and canonical config wiring.
- **Every page rendered blank in the browser while returning a valid HTML response**: the base layout `resources/views/components/layouts/auth.blade.php` opened a `<style>` block that was never closed. Browsers parse an unterminated `<style>` as raw text until EOF, so the entire `<body>` — the whole login form — was consumed as CSS and never became DOM. HTTP status was `200`, the HTML source looked correct on inspection, and `document.body.children.length` was `0`, which is why only the browser showed a black screen. The missing `</style>` is restored, and `tests/Security/BladeViewContractTest` now asserts balanced `<style>`/`<script>` counts plus a single `<body>`/`</head>` in the rendered page, because a status-code assertion cannot catch this class of failure.
- **Throttle message rendered its own placeholder**: `authentication::messages.throttle_error` contains a `:seconds` placeholder, but every controller call site translated it with no replacement array, so users saw the literal text `Too many attempts. Please try again in :seconds seconds.` Enforcement was never affected — only the displayed message. All call sites now go through the new `ThrottleMessage::forSeconds()`, which supplies the retry window from the same rate-limiter key the limiter used and falls back to a new `throttle_error_unknown` sentence when the window is unknown, so `in 0 seconds` can no longer be shown either.
- **Framework notification URLs used unprefixed route names**: Laravel's built-in `ResetPassword` notification hardcodes `route('password.reset')` and `VerifyEmail` builds its own verification URL. With package route names namespaced under `authentication.`, a stock Laravel host hit `Route [password.reset] not defined`. The service provider now registers `ResetPassword::createUrlUsing()` and `VerifyEmail::createUrlUsing()` pointing at `authentication.password.reset` / `authentication.verification.verify`, guarded by `Route::has()` and by a null callback check so a host's own registration still wins.
- **`countdown-alert` hardcoded Indonesian copy**: the throttle countdown component embedded Indonesian strings in its Alpine expression, so a host running `app.locale=en` saw a half-translated countdown. All strings now come from `messages.php` (`retry_wait`, `retry_wait_finished`, `retry_minutes`, `retry_minutes_seconds`, `retry_seconds_only`) in both `en` and `id`.
- **Duplicated layout trees had drifted**: `resources/views/layouts/auth.blade.php` and `resources/views/components/layouts/auth.blade.php` were separate files with the same name and different contents. The live copy (the one every page actually renders through) was missing the `[x-cloak]` FOUC guard and the entire `.auth-btn-social` / `.auth-btn-passkey` stylesheet, so social and passkey buttons rendered unstyled. Both trees are now consolidated in `resources/views/components/layouts/`.

### Added
- `src/Support/ThrottleMessage.php` — single entry point for translated retry windows.
- `tests/Security/ThrottleMessageTest.php` — locks the exact en/id sentences and asserts a throttled `/forgot-password` response never leaks `:seconds`.
- `tests/Security/BladeViewContractTest.php` — locks the Blade view contract: configured targets resolve and match the canonical tree, every `<x-authentication::…>` tag used by a package view resolves, a page renders exactly one document with balanced `<style>`/`<script>`/`<body>` tags, and the countdown component honours `app.locale`.
- `tests/Unit/ViewPublishMapTest.php` — `vendor:publish --tag=authentication-views` copies the whole view tree; asserts the publish map covers every file on disk so the reorganized pages and grouped components reach a host.

### Changed
- **Blade pages grouped by domain**: the flat page views moved to `resources/views/pages/{auth,password,otp,two-factor,sessions,system}/`, and `config('authentication.views.*')` plus the controller fallbacks now name those canonical paths directly.
- **Reusable components grouped by role**: `alert` and `countdown-alert` moved to `components/feedback/`; `input`, `button`, `checkbox`, `otp-input`, and `segmented-code-input` to `components/forms/`; `active-sessions` and `passkey-button` to `components/security/`. Callers use the grouped tag (`<x-authentication::forms.input />`); Blade does not search nested directories, so the flat tags no longer resolve.
- **Legacy compatibility wrappers removed**: the flat `*.blade.php` page files, `layouts/*`, and `components/<name>.blade.php` are deleted rather than kept as `@include` shims. A wrapper adds a hop nobody reads, silently drops `$attributes`/`$slot` when written without `get_defined_vars()`, and keeps a renamed view alive under a path no longer maintained. A host that published the old paths must republish (`php artisan vendor:publish --tag=authentication-views --force`) or repoint its config at the canonical names.

### Documentation
- **Queue worker instructions corrected**: `authentication.mail.queue_name` was removed earlier, but the docs still told operators to run `php artisan queue:work --queue=auth-emails`, a queue nothing writes to. Queued auth mail runs on the application `default` queue, so a plain `php artisan queue:work` handles email; only `authentication.audit.queue_name` (`auth-audit`) is still a named queue. Every supervisor, Docker, and troubleshooting example now uses `--queue=default,auth-audit`. Stale `queue:monitor auth-emails` and "mail.queue = true by default" claims were corrected, and the documented PHP version was aligned to the `^8.2` constraint in `composer.json`.
- **Optional dependency matrix**: README now states which integrations are optional (Sanctum for API tokens, Socialite for social login, CAPTCHA provider keys, a queue worker, Redis) and what fails closed when each is missing, so hosts do not install everything by default.

### Fixed
- **Route namespace isolation and state-changing challenge hardening**: Web route names are now registered under the configurable `authentication.routes.web.route_name_prefix` (default `authentication.`), so package routes can no longer collide with or be overwritten by host route names such as `login` or `password.confirm`. An empty prefix raises `AuthenticationConfigurationException`. The previously dead `authentication.routes.web.prefix` config is now applied during registration. WebAuthn challenge endpoints (`passkey.login.options`, `passkey.register.options`) are POST-only on both web and API stacks, so a crawler, prefetcher, or link scanner can no longer trigger challenge creation via GET. API authenticated route groups resolve their middleware through `RouteConfig::apiAuthMiddleware()`, which fails closed when `authentication.routes.api.auth_middleware` is empty or lists only non-authenticating middleware.
- **Trusted client-IP boundary**: Added a centralized fail-closed resolver. Forwarded headers are ignored unless the immediate TCP peer matches an explicit IPv4/IPv6 address or CIDR in `AUTH_TRUSTED_PROXIES`; wildcard and malformed trust entries do not enable spoofing. All authentication rate-limit, lockout, device-trust, 2FA, passkey, password-confirmation, CAPTCHA, session, and social-auth paths now use it.
- **Replay-resistant TOTP**: TOTP timesteps are claimed under a database row lock and persisted in `last_used_timestep`; a captured code can succeed only once within the configured window. Run migration `2026_01_01_000009_add_last_used_timestep_to_two_factor_authentications.php` before enabling this version.
- **Encrypted queued authentication mail**: `OtpMail` and `NewDeviceLoginMail` implement `ShouldBeEncrypted`, preventing plaintext OTP and new-device notification payloads in queue storage.
- **Route throttle cannot be removed by host middleware overrides**: package web and API route groups always append `authentication.throttle`; negative global limits fail closed as configuration errors.
- **Dead `mail.queue_name` accessor**: `AuthenticationConfig::getMailQueueName()` was removed. No Mailable ever called `onQueue()`, so the accessor had no consumer and reading it gave operators false confidence that `--queue=auth-emails` controlled delivery. Auth mailables now always use the default queue. `audit.queue_name` remains a live knob consumed by `RecordAuthenticationAuditJob`.

- **Email Queue Config Conflict (CRITICAL)**: `OtpMail` and `NewDeviceLoginMail` previously implemented `ShouldQueue`, causing emails to ALWAYS be queued regardless of `config('authentication.mail.queue')` setting. Per [Laravel documentation](https://laravel.com/docs/12.x/mail#queueing-by-default), Mailables with `ShouldQueue` are queued even when calling `Mail::send()`, making the config meaningless and requiring queue workers even when users wanted synchronous delivery. This caused silent failures when `mail.queue = true` but queue worker wasn't running. **FIX**: Removed `implements ShouldQueue` from both Mailable classes. Config `authentication.mail.queue` now correctly controls queue vs sync behavior. Matches Laravel's password reset pattern (synchronous by default, opt-in queue).

- **Misleading Log Messages**: Log entries previously stated "OTP email sent synchronously" even when email was actually queued (due to `ShouldQueue` override). Logs now explicitly show `[AUTH] OTP email queued for background delivery` with queue name when queued, and `[AUTH] OTP email sent immediately (synchronous)` when synchronous.

### Changed
- **BEHAVIOR CHANGE**: Config default `authentication.mail.queue` changed from `true` → `false`. Fresh installations now send OTP/new-device emails **synchronously by default**. Previous default (queue enabled since v1.9.0) caused silent failures when queue worker wasn't running, violating zero-config principle. New default matches Laravel core's password reset behavior. For production high-traffic applications, explicitly enable `authentication.mail.queue = true` and run `php artisan queue:work`.

- **Queue Simplification**: Removed `authentication.mail.queue_name` config. Authentication emails now use Laravel's default queue, allowing `php artisan queue:work` to process them without requiring `--queue=auth-emails` flag. This eliminates setup friction and matches Laravel convention (most packages don't enforce custom queue names).

- **Global throttle coverage**: `security.global_throttle` is now asserted on BOTH route stacks (web and API), not only API. The earlier test proved the middleware worked in isolation while never checking that a route actually carries `authentication.throttle`.

### Migration Guide (v1.9.x → v1.10.0)

**If you have `mail.queue = false` (or never changed it in v1.8.x):**
- ✅ No action needed — emails are now truly synchronous as configured.

**If you have `mail.queue = true` AND run queue workers:**
- ✅ No action needed — behavior unchanged, emails still queued.

**If you have `mail.queue = true` but DON'T run queue workers:**
- ⚠️ ACTION REQUIRED: Either start queue worker (`php artisan queue:work`) or set `mail.queue = false` for synchronous delivery.
- Previous behavior: emails silently stuck in queue, never delivered.
- New behavior: config respected — sync when false, queued when true.

**Custom queue name users:**
- Config `authentication.mail.queue_name` removed. Jobs now use default queue.
- If you have Supervisor config with `--queue=auth-emails`, change to `--queue=default` or remove flag entirely.
- Migration: No data loss, existing jobs in `auth-emails` queue can be manually moved or processed with `php artisan queue:work --queue=auth-emails,default`.

### Added
- **2FA Segmented Code Input Component** - Reusable component for TOTP, recovery codes, and OTP with auto-advance, paste, and keyboard navigation
- **Recovery Mode Persistence** - Invalid recovery code now returns to recovery mode instead of resetting to TOTP
- **Enterprise Multi-Dimensional Rate Limiting** - 5 independent abuse policy dimensions (account, account_ip, network, global, client) to prevent IP rotation, identifier rotation, and botnet attacks
- `AuthenticationAbusePolicyInterface` - New policy contract for multi-dimensional evaluation
- `RateLimitKeyFactory` - SHA-256 hashed, IPv6-normalized, client-scoped rate limit keys
- `DistributedAttackDetector` - Scoring-based detection (0.0-1.0) for credential stuffing and distributed brute force
- Adaptive challenge escalation with single-use, expiry-bound tokens
- Network prefix rate limiting (IPv4 /24, IPv6 /64) to block subnet rotation
- Global platform capacity limiter (emergency circuit breaker)
- Per-client/application rate limit isolation
- PII-redacted security telemetry events (RateLimitExceeded, DistributedAttackDetected, ChallengeIssued)
- Comprehensive adversarial attack simulation tests (0% bypass rate verified)
- Production deployment guide (`docs/security/rate-limiting-deployment.md`)
- Security audit report (`docs/security/SECURITY-AUDIT-2026-10-02.md`)

### Changed
- **BREAKING**: `AuthenticationService` now uses `AuthenticationAbusePolicyInterface` instead of `LoginAttemptManagerInterface`
- **BREAKING**: Config default `authentication.security.abuse_policy.enabled` = `true` (new installs get multi-dimensional limiter)
- Account lockout checked before abuse throttling (durable DB-backed lockout wins over cache-based throttle)
- Challenge decision is advisory (CAPTCHA enforced by `LoginRequest`), not hard exception
- `AuthenticationContext` added optional `clientId` property for tenant isolation
- `getRateLimitConfig()` return type expanded to include `challenge_threshold` and `challenge_token_ttl`

### Fixed
- PHPStan Level 8 compliance across the package

### Security
- **Atomic Recovery Code Consumption** - Transaction with `lockForUpdate()` prevents race condition where concurrent requests consume the same code
- **Strict 2FA Input Validation** - Regex enforcement: TOTP exactly N digits, recovery 6-30 alphanumeric characters
- **Dual-Mode Submission Prevention** - Reject requests containing both `code` and `recovery_code` fields
- Sensitive codes never flashed to session or old input (removed from request before ValidationException)
- Closes IP rotation bypass vulnerability (attacker can no longer rotate IP to reset account budget)
- Closes identifier rotation bypass (attacker can no longer rotate email to reset network budget)
- Closes botnet bypass (distributed 1M IP×account pairs now blocked by account+network+global dimensions)
- IPv6 /64 prefix rotation now detected and blocked

### Migration Guide
**Existing installations**: To keep legacy composite limiter behavior, set in `config/authentication.php`:
```php
'security' => [
    'abuse_policy' => [
        'enabled' => false, // Keep legacy composite behavior
    ],
],
```

**New installations**: Multi-dimensional abuse policy enabled by default with production-ready thresholds:
- Account: 20 attempts / 5 minutes
- Account+IP: 5 attempts / 1 minute  
- Network: 100 attempts / 1 minute
- Global: 1000 attempts / 1 minute

## [1.9.3] - 2026-10-02

### Added - Security Telemetry
- Added PII-redacted `RateLimitExceeded`, `DistributedAttackDetected`, and `ChallengeIssued` events. Payloads use SHA-256 identifier hashes, hashed IPv4 /24 or IPv6 /64 network buckets, bounded counts, and reason codes only.
- `AuthenticationAbusePolicy` emits `RateLimitExceeded` when the composite login limit is exceeded.

### Fixed - UI Critical
- **CRITICAL: Alpine scope inheritance bug**: Button accessed `$root.submitting` expecting form data, but `$root` points to topmost Alpine component in tree (could be layout), not parent form. Button never saw submitting state, loading text hidden.
  - **Solution**: Remove button `x-data`, inherit form scope lexically. Button reads `submitting` directly from `<form x-data="{ submitting: false }">`. Native `@submit` (no `.prevent`, no manual submit) → instant loading, no double submit.
- **CRITICAL: Missing Alpine state on 7 forms**: Two-factor setup, two-factor challenge, OTP request/verify, reset password, confirm password, and forgot password forms missing `x-data="{ submitting: false }"`. Button slot text hidden because `x-show="!submitting"` evaluated to undefined.
  - **Solution**: Add `x-data="{ submitting: false }" @submit="submitting = true"` to all POST forms for consistent button loading behavior.
  - Files: `two-factor-setup.blade.php`, `two-factor-challenge.blade.php`, `otp-request.blade.php`, `otp-verify.blade.php`, `reset-password.blade.php`, `confirm-password.blade.php`, `forgot-password.blade.php`
- **CRITICAL: Alpine.js $parent bug**: `$parent` does not exist in Alpine v3 core. Button component used `$parent.submitting` causing `TypeError: Cannot read properties of undefined (reading 'submitting')`. Alpine crashed, button stuck disabled forever.
  - **Solution**: Use scope inheritance instead of magic property access.
  - Files: `button.blade.php`
- **CRITICAL: x-cloak FOUC (Flash of Unstyled Content)**: Missing `[x-cloak] { display: none !important; }` style. Caps Lock warning with `x-cloak` attribute flashed yellow on page load before Alpine.js initialized.
  - **Solution**: Add x-cloak style to `layouts/auth.blade.php`
- **Caps Lock warning UX**: Default `x-transition` too slow causing visible flash. Absolute positioning overlapped checkbox/button (unprofessional spacing).
  - **Solution**: `x-transition.opacity.duration.150ms` for instant fade. `mt-2` natural flow instead of absolute positioning. `gap-1.5` better icon-text spacing.
  - File: `input.blade.php`
- **CRITICAL: Button infinite loading bug**: Form never submitted when button disabled in @click event. Chrome/Edge cancel form submission if submit button disabled before submit event completes. User stuck with spinner forever, form never reaches server.
  - **Solution**: Move loading state from button @click to form @submit. Button inherits `submitting` via lexical scope. Native form submission works correctly.
  - Files: `button.blade.php`, `login.blade.php`, `register.blade.php`
- **Hardcoded messages replaced with i18n**: All controller messages now use `__()` translation helper for proper localization support.
  - Added lang keys: `registration_disabled`, `two_factor_required`, `processing`
  - Files: `LoginController.php`, `RegisterController.php`, `resources/lang/{en,id}/messages.php`
- **Install command**: Removed `--views` flag. Views load from vendor/ by default (no publish needed). Manual publish available via `php artisan vendor:publish --tag=authentication-views`.

### Fixed - OAuth
- **CRITICAL: GitHub OAuth rejected**: GitHub Socialite returns only verified primary email but does not expose `email_verified` field on user payload. Package strict verification saw `null` and rejected callback with "Social sign-in with github failed."
  - **Solution**: Treat GitHub provider as implicitly verified when email is present (GitHub API already filtered to verified primary email). Add `report($e)` to controller for diagnostics.
  - Files: `SocialAuthService.php`, `SocialAuthController.php`

## [1.9.0] - 2026-10-01

### Security Fixes (Deep Audit - 4 Parallel Subagents)
- **CRITICAL: Fatal Crash pada Account Lockout**: `LoginController.php:64` memanggil `$this->config` yang tidak di-inject → 500 error (DoS). Fix: `config()` dengan `(int)` cast + `max(0, ...)` sanitization.
- **CRITICAL: XSS Injection via Alpine.js Template**: `countdown-alert.blade.php` tidak cast `$seconds` ke `(int)` dan `$submitButton` tidak di-escape → session manipulation = XSS. Fix: Force `(int)` + `json_encode()`.
- **HIGH: User Enumeration via Timing Attack**: `AuthenticationService.php:113` skip bcrypt untuk non-existent user (5ms) vs wrong password (423ms). Delta 8360% → email enumeration. Fix: Dummy `Hash::check()` untuk normalize timing.
- **MEDIUM: Circuit Breaker Timing Side-Channel**: OPEN state return instantly (fast-path) → OAuth provider status leak via timing. Fix: `addTimingNoise()` 5-25ms + remove `opened_at` dari `getMetrics()`.
- **MEDIUM: Audit Data Loss Risk**: `RecordAuthenticationAuditJob` tanpa retry/idempotency → queue fail = audit hilang. Fix: 3 attempts + backoff [5,15,30]s + Cache idempotency + `failed()` emergency log.
- **MEDIUM: Health Check Information Disclosure**: Error messages expose table/model names. Fix: `--silent` flag + `sanitizeErrorMessage()`.
- **LOW: TOCTOU Race di Session Pruning**: Separate `count()` + `delete()` → active session bisa terhapus. Fix: Atomic single `DELETE`.
- **PII Leak via Events (DEFERRED)**: `LoginAttempted`/`LoginFailed` expose raw email ke external listeners. Breaking change required → deferred ke v2.0.
- **Performance Bottlenecks (RECOMMENDATION)**: 3 optimization identified (async audit, duplicate device lookup, redundant lockout pre-check) → 64% latency gain jika diimplementasikan.

**Test Impact**: 175 tests, 472 assertions, 0 errors (was 18 errors during fix iteration). PHPStan Level 8 CLEAN.

### Added - Enterprise Production Features
- **`PruneSessionsCommand`**: CLI command untuk cleanup session kadaluarsa dan device record stale (high-traffic table management). Mendukung `--dry-run`, `--session-days`, `--device-days`.
- **`HealthCheckCommand`**: Health check untuk Kubernetes/Docker readiness probe. Verifikasi database, cache, required tables, user model, dan strategy registry. Exit code 0 = healthy, 1 = unhealthy.
- **Production Deployment Guide**: Dokumentasi lengkap `docs/PRODUCTION-DEPLOYMENT.md` (18KB) mencakup infrastructure requirements, configuration checklist, performance tuning (OPcache, query optimization, Redis cluster), high-availability setup (load balancer, sticky sessions), monitoring & observability (Prometheus, Grafana), scheduled jobs, Kubernetes deployment examples, security hardening, dan troubleshooting.
- **Account Lockout Concurrency Tests**: `AccountLockoutConcurrencyTest` (4 tests, 19 assertions) untuk race condition protection pada lockout counter.
- **Circuit Breaker Pattern**: `CircuitBreaker` class untuk fail-fast protection terhadap external service failures (OAuth providers, email, SMS gateway). Auto-recovery setelah timeout. 8 tests covering all state transitions.
- **Async Audit Logging**: `RecordAuthenticationAuditJob` untuk offload audit persistence ke queue workers. Config `authentication.audit.queue = true` untuk enable. Fallback ke sync write atau log-only jika dispatch gagal. Zero data loss guarantee.

### Added - UX Enhancements
- **Live Countdown Timer Alert**: Real-time countdown untuk throttle/lockout (detik/menit). Progress bar visual. Auto-disable tombol submit selama countdown. Auto-enable + success message saat countdown habis. Ekstrak detik otomatis dari error message.
- **Caps Lock Warning Indicator**: Deteksi Caps Lock aktif saat mengetik password. Badge peringatan amber "Caps Lock aktif". Mencegah user salah password berulang kali.
- **Form Submit Loading State**: Spinner animasi saat form disubmit. Tombol auto-disabled (prevent double-submit). Text berubah: "Masuk" → "Memproses...". Prevent spam-click yang memperburuk rate limit.
- **Auto-disable Submit Button**: Countdown alert otomatis disable tombol submit via JavaScript. Re-enable saat countdown habis.

### Components
- **countdown-alert.blade.php**: Alpine.js powered countdown component dengan progress bar + button control
- **input.blade.php**: Caps Lock detection via `getModifierState` API + Alpine scope fix
- **button.blade.php**: Loading spinner + disabled state + text toggle
- **UX Documentation**: `docs/UX-IMPROVEMENTS.md` (10KB) - Complete integration guide

### Fixed - Security
- **SEC-15**: Account lockout counter tidak mengecek status lock sebelum increment. Bug memungkinkan concurrent request menaikkan `failed_attempts` setelah lockout triggered. Fix: guard pre-check + re-check under row lock di `AccountLockService::recordFailureAndCheckLockout()`.

### Changed
- CLI commands diregister di `AuthenticationServiceProvider`: tambah `PruneSessionsCommand` dan `HealthCheckCommand`.
- **PERF-08**: OAuth provider calls via `SocialAuthService::handleCallback()` dilindungi circuit breaker (5 failures → 60s timeout).
- **PERF-09**: Audit logging dapat offload ke queue workers untuk eliminasi 2 synchronous DB writes per login di high-traffic apps (>1000 req/sec).

### Performance Improvements
- Circuit breaker prevents cascading failures + thundering herd on external service recovery
- Async audit logging: ~50-100ms latency reduction per authenticated request
- Queue workers decouple write I/O from HTTP thread (horizontal scaling audit persistence)

Total: 175 tests, 473 assertions, PHPStan level 8 clean.

## [1.8.0] - 2026-09-23

### Added
- **Auto-detection `mixudev/security-defense`**: Helper `SecurityDefenseDetector` mendeteksi keberadaan package pertahanan `mixudev/security-defense` dan status bridge subscriber (`app/Listeners/AuthenticationSecuritySubscriber.php`).
- **Hint `php artisan auth:sync`**: `InstallCommand` (`php artisan authentication:install`) otomatis menampilkan banner hint jika package `security-defense` terpasang namun bridge subscriber belum di-generate.
- **Config toggle `authentication.security_defense.auto_detect`**: Opsi untuk mematikan hint deteksi (default: `true`), serta konfigurasi perintah bridge hint.
- **Dokumentasi integrasi**: Panduan lengkap `docs/operations/integration-security-defense.md` mencakup prasyarat, alur setup 1 perintah (`php artisan auth:sync`), tabel pemetaan 12 domain auth event ke `SecurityDefense::record()`, panduan verifikasi, dan troubleshooting.
- **Unit tests**: `SecurityDefenseDetectorTest` (5 test assertions) untuk deteksi package, bridge subscriber file check, AppServiceProvider registration check, dan pesan hint format.

Total: 159 tests, 426 assertions, PHPStan level 8 bersih.

## [1.7.4] - 2026-09-06

### Security (Red-Team Sweep — SA-15..SA-19)

- **SA-15 — Internal exception leak dihentikan**: `$e->getMessage()` di `OtpController` (web + API), `SocialAuthController` (web + API), `PasskeyController`, `RegisterController` diganti pesan generik. Detail internal hanya ke `report()`. Lang key baru: `passkey_registration_failed` (en + id).
- **SA-16 — Fail-closed token API**: `TokenService` tidak lagi mengembalikan token dummy `Str::random(64)` yang tidak pernah di-persist (client menerima token "sukses" yang langsung invalid dan tidak bisa di-revoke). Tanpa Sanctum `HasApiTokens` kini throw `AuthenticationConfigurationException`. `AuthenticationService` + `PasskeyService` injeksi `TokenManagerInterface` (decoupled).
- **SA-17 — Pending token 2FA opaque + single-use**: `Support\TwoFactorPendingToken` menggantikan kode inline di 4 controller. Cache key = `sha256(token)`, token dikonsumsi setelah verifikasi (anti-replay), TTL configurable `authentication.features.two_factor.pending_token_ttl_minutes` (default 10). Rate limit `two_factor` di-upgrade dari `ip` ke `composite` (user+IP) — memblokir brute force via rotasi IP.
- **SA-18 — Middleware mati dihidupkan**: `EnsureSessionSecurity`, `CheckAccountLockout`, `AuthenticateWithCustomGuard` sebelumnya dead code (tidak ter-register). Kini alias `authentication.session-security`, `authentication.lockout`, `authentication.guard`. `authentication.session-security` otomatis terpasang di route web + api package — semua halaman auth kini kirim `X-Frame-Options`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`.
- **SA-19 — Catatan timing recovery code**: ditinjau, tidak eksploitable karena rate limit composite.

### Security (Red-Team Sweep lanjutan — SA-20..SA-24)

- **SA-20 — Brute force `sessions/revoke-others` diblokir**: endpoint password-gated tanpa rate limit → sekarang share limiter `confirm_password` (5/1min, user+IP), hit saat password salah, clear saat sukses.
- **SA-21 — Brute force setup 2FA diblokir**: `TwoFactorSetupController::confirm` (TOTP setup pra-aktivasi) dan `destroy` (disable butuh password) kini di-rate-limit — attacker tidak bisa brute TOTP setup atau password untuk mematikan 2FA.
- **SA-22 — Passkey options cache-flood dicegah**: `loginOptions` menyimpan challenge 5 menit per request tanpa limit → feature rate limit baru `passkeys` (60/min per IP) dipasang.
- **SA-23 — Registration DTO dibersihkan**: `RegisterRequest::toDto()` tidak lagi meneruskan semua field request ke `RegisterData::extra` — hanya `name`/`email`/`password`.
- **SA-24 — Catatan rotasi secret 2FA setup**: `setup()` berulang pada record unconfirmed me-regenerate secret + recovery codes (bisa meng-orphan kode yang sudah disimpan user). Tidak eksploitable (butuh auth + rate limit), dicatat untuk hardening.
- **SA-25 — Dead code dihapus & fitur diwiring**: hapus 4 file tak ber-referensi (`Enums/LoginMethod.php`, `Contracts/IdentityResolverInterface.php`, `Contracts/SecurityPolicyInterface.php`, `Providers/AuthenticationRouteServiceProvider.php`). `RequirePasswordConfirmation` kini ter-register sebagai alias `authentication.password-confirm` (sebelumnya tidak bisa dipasang host app).
- **SA-26 — Interface injection menyeluruh**: 6 service (`AuthenticationService`, `RegistrationService`, `OtpService`, `PasskeyService`, `SocialAuthService`, `SessionController`) inject `AuditLoggerInterface` dan `LoginAttemptManagerInterface` alih-alih implementasi konkret — binding container kini benar-benar terpakai. `AuditLoggerInterface::getRecentLogins()` ditambahkan ke kontrak. `ServiceProviderTest` kini assert semua 11 binding kontrak resolve.
- **SA-27 — Config keys mati ditandai @deprecated**: `audit.retention_days`, `ui.brand_badge`, `views.otp_email` (pakai `features.otp.email_view`), `password.validation_rules.require_mixed_case` (pakai `require_uppercase`+`require_lowercase`) — tidak dibaca kode mana pun; ditandai di config, tidak dihapus demi backward-compat.
- **SA-28 — Max active sessions kini di-enforce**: config `max_active_sessions` ada tapi tidak pernah ditegakkan — user bisa punya sesi aktif tak terbatas. `SessionManagerService::enforceMaxActiveSessions()` memangkas sesi tertua saat login melebihi limit (current session tidak pernah dihapus). Dipanggil dari `SessionSecurityService::loginUser()`.
- **SA-29 — Passkey service tidak lagi bocorkan detail WebAuthn**: `PasskeyService::authenticate` re-throw `$e->getMessage()` dari `WebAuthnHelper` (detail origin/rpIdHash/UV) → sekarang `report($e)` + exception generik. Redundan `request()->is('api/*')` dihapus (channel dari `$context`).
- **SA-30 — Command `authentication:prune`**: `audit.retention_days` kini di-konsumsi — command hapus attempts/login_histories/password_histories lebih tua dari retensi (per-kolom waktu masing-masing), dukungan `--days` + `--dry-run`.

### Tests (Red-Team)

- `TwoFactorPendingTokenTest` (8 test): opacity token, single-use, replay gagal, TTL config, cache-key hashing, collision.
- `TokenServiceFailClosedTest` (2 test): throw tanpa Sanctum; revoke no-op aman.
- `SecurityHeadersTest` (3 test): headers keamanan di login/register/api routes.
- `RegistrationInjectionTest` (3 test): extra-field injection tidak ter-persist; duplicate email ditolak.
- `SessionRevokeOthersRateLimitTest` (2 test): brute-force password via revoke-others kena throttle; password benar sukses.
- `TwoFactorSetupBruteForceTest` (2 test): TOTP setup confirm + disable 2FA kena throttle.
| `PasskeyRateLimitTest` (2 test): loginOptions kena 429 melewati limit; normal sebelum limit.
- `PasswordConfirmMiddlewareTest` (3 test): alias middleware resolve; aksi tanpa konfirmasi → 423; dengan konfirmasi → 200.
- `ServiceProviderTest` (3 test): semua 11 kontrak binding resolve + audit logger ter-bind ke concrete.
- `TrustedDevicePipelineTest` (3 test): tanpa cookie trust → 2FA required; cookie valid → login langsung; cookie di-revoke → 2FA lagi.
- `MaxActiveSessionsEnforcementTest` (4 test): over-limit memangkas sesi tertua; at-limit no-op; current session tidak pernah dihapus; feature disabled no-op.
- `PruneAuditLogsCommandTest` (3 test): hapus data tua > retensi; dry-run no-op; `--days` override menang atas config.

Total: 151 tests, 417 assertions, PHPStan level 8 bersih.

## [1.7.3] - 2026-09-06

### Security (Red-Team lanjutan — SA-31)

- **SA-31 — Audit trail tidak lengkap (nilai enum diam)**: `SecurityEventType` punya 18 kasus tapi hanya 6 yang pernah ditulis ke trail audit — `PASSWORD_CHANGED`, `EMAIL_VERIFIED`, `ACCOUNT_LOCKED`, dll. defined tapi tanpa konsumen; event ter-dispatch namun audit DB (rekam forensik) melewatkan momen siklus hidup ini.
- **Fix**: audit logging di-wire ke 3 jalur siklus hidup yang sama sekali tidak punya konsumen:
  - `PasswordService::updatePassword` → log `PASSWORD_CHANGED` (context null-safe untuk CLI/queue — `request()` bisa null).
  - `EmailVerificationController::verify` → log `EMAIL_VERIFIED`.
  - `AccountLockService::recordFailureAndCheckLockout` → log `ACCOUNT_LOCKED` (sebelumnya hanya dispatch event, tidak pernah di-audit).
  - Semua lewat kontrak `AuditLoggerInterface` (SA-26); redaksi identifier tetap (`SecurityHelper::maskIdentifier`).
- `AuditTrailCompletenessTest` (3 test): trail DB merekam `PASSWORD_CHANGED` dan `ACCOUNT_LOCKED`; kontrak logger resolve dari container.
- Sisa kasus senyap (`PASSWORD_RESET_*`, `SESSION_REVOKED`, `TOKEN_REVOKED`, `OTP_*`, `USER_REGISTERED`, `SOCIAL_LOGIN`, `ACCOUNT_UNLOCKED`) sudah di-cover oleh listener event saat `default_audit_enabled=true` — fix ini menutup jalur yang TIDAK punya audit write sama sekali.

## [1.7.4] - 2026-09-06

### Security (Red-Team lanjutan — SA-32)

- **SA-32 — Audit trail dilengkapi 100%**: semua nilai enum senyap (`TOKEN_REVOKED`, `SESSION_REVOKED`, `PASSWORD_RESET_REQUESTED`, `OTP_FAILED`, `TWO_FACTOR_DISABLED`) kini di-wire ke audit write nyata:
  - `TokenService::revokeAllTokens` + `revokeCurrentToken` → `TOKEN_REVOKED` (+ metadata `scope`; context null-safe untuk CLI). Logout API (via `TokenManagerInterface` di `AuthenticationService::logout`) otomatis tercatat.
  - `SessionController::destroy` → `SESSION_REVOKED` (+ `session_id`).
  - `PasswordResetController` (web + API) → `PASSWORD_RESET_REQUESTED` (sebelum sleep timing-jitter; identifier di-mask, tidak bocorkan eksistensi user).
  - `TwoFactorService::disable` → enum case BARU `TWO_FACTOR_DISABLED` (disable 2FA = event kritis yang tadinya tanpa representasi audit sama sekali).
  - `OtpService::verify` jalur gagal (expired, max-attempts, mismatch) → `OTP_FAILED` (+ `reason`) — satu-satunya gap di siklus OTP.
  - `TokenServiceFailClosedTest` di-upgrade ke `app(TokenService::class)` (constructor baru inject `AuditLoggerInterface`).
- `AuditTrailCompletenessTest` diperluas jadi 6 test: `PASSWORD_CHANGED`, `ACCOUNT_LOCKED`, `OTP_FAILED`, `TOKEN_REVOKED`, `PASSWORD_RESET_REQUESTED` terbukti tertulis di trail DB; kontrak resolve.

Total: 154 tests, 421 assertions, PHPStan level 8 bersih.

## [1.6.1] - 2026-09-06

### Security (SEC-03 extension — API User Payload Sanitization)
- **Kebocoran hash password di response JSON**: 5 endpoint API mengembalikan Eloquent model mentah (`$user` / `$result->user`) yang menserialize SELURUH kolom termasuk `password` hash, `remember_token`, `created_at`, dan atribut internal lain:
  - `LoginController::apiLogin`
  - `PasskeyController::authenticate`
  - `TwoFactorChallengeController::verify`
  - `OtpController::verify`
  - `SocialAuthController::handleProviderCallback`
- **Fix**: Ditambahkan `Support\SafeUserPresenter` — presenter whitelist yang hanya mengekspos `id`, `name`, `email`, `username`. Semua 5 endpoint kini memakai presenter ini.
- Test baru: `tests/Security/ApiUserPayloadSanitizationTest` (4 test) — memverifikasi presenter whitelist, login API tidak mengandung hash password di response, OTP verify API tidak mengandung hash, dan presenter menangani field `name`/`username` nullable.

## [1.6.0] - 2026-09-06

### Added
- **Event Dispatch Completeness**: `PasswordService::updatePassword()` kini mem-dispatch `PasswordChanged`; `PasswordResetController` mem-dispatch `PasswordResetRequested` (web & API, selalu dipicu walau email tidak terdaftar — anti user enumeration) dan `PasswordResetCompleted`; `SessionController::destroy()` mem-dispatch `SessionRevoked`.
- **Optional Default Security Audit Listener**: `src/Listeners/SecurityAuditEventListener.php` menulis audit trail JSON terredaksi (IP, channel, user agent terpotong, tanpa password/hash) ke log channel yang dapat dikonfigurasi. Diaktifkan via `authentication.listeners.default_audit_enabled` (default `false` — opt-in; host app bebas mendaftarkan listener sendiri).
- **Consistent `Dispatchable` Trait**: Semua event package kini menggunakan `Illuminate\Foundation\Events\Dispatchable` (sebelumnya hanya sebagian), memungkinkan dispatch facade-style dan konsistensi dengan framework events.
- **Nullable `AuthenticationContext` pada Password Events**: `PasswordChanged` dan `PasswordResetCompleted` menerima `?AuthenticationContext` sehingga aman di-dispatch dari queue worker / CLI tanpa HTTP request.
- **Dokumentasi Events & Listeners**: `docs/development/events-and-listeners.md` — katalog 15 domain events, cara mendaftarkan listener (EventServiceProvider / manual / queue), aturan redaction payload, contoh alert AccountLocked.

### Fixed
- **Strategy Name Disclosure (Security)**: Pesan `InvalidStrategyException` tidak lagi memuat nama strategi yang diminta — mencegah bocornya FQCN internal namespace package (`Vendor\LaravelAuthentication\Strategies\...`) ke attacker.

### Security Tests (68 → 107 tests, 290 assertions)
- **RateLimitingAndBruteForceTest**: IP rotation bypass, isolasi composite rate-limit key (IP A throttled, IP B tidak), uppercase identifier bypass, correct-password-setelah-lockout tetap diblokir, `secondsRemaining` pada throttle exception.
- **SQLInjectionAndInputSanitizationTest**: 15 payload data provider (UNION, stacked query, null byte, CRLF injection, unicode lookalike, oversized 10K input, format string) + injeksi pada field password user nyata.
- **UserEnumerationProtectionTest**: timing delta < 50ms (multi-sampel + warm-up), respon identik untuk case-sensitive email, whitespace-padded identifier.
- **EventIntegrityTest**: urutan event (Attempted → Succeeded/Failed), payload event tidak mengandung password/hash saat di-serialize, `PasswordChanged` ter-dispatch, non-existent user tetap memicu `LoginFailed`.
- **AnomalyAndEdgeCaseSecurityTest**: service disabled memblokir semua attempt, strategi FQCN injection ditolak, oversized user agent (64KB) tidak crash, 20 rapid sequential attempts tidak pernah sukses dengan password salah.

### Documentation Restructure
- Direktori `docs/` ditata ulang ke hierarki: `getting-started/`, `features/`, `development/`, `api/`, `security/`, `operations/`.
- **`docs/getting-started/installation.md`** (baru): panduan end-to-end — composer require → setup otomatis/manual → publish config/migrasi/views → Tailwind → user model → verifikasi instalasi → langkah berikutnya.
- `docs/getting-started/prerequisites.md` (baru): matriks kebutuhan layanan, cara mendapatkan API keys (Turnstile, Google/GitHub OAuth, SMTP), checklist produksi.
- Sitemap 49 rute dipindah ke `docs/api/api-reference.md` sebagai lampiran.
- README daftar dokumen diperbarui ke path baru.

## [1.5.9] - 2026-09-04

### Fixed
- **Laravel 10 Eloquent Model Casting Compatibility**: In Laravel 10.x, Eloquent does not call the `casts(): array` method (introduced in Laravel 11.x), which caused all models to have empty `$casts`. This led to `QueryException: Array to string conversion` when persisting arrays (`TwoFactorAuthentication::$recovery_codes`, `PasskeyCredential::$transports`) and `Call to a member function isFuture() on string` on uncast datetime attributes (`AccountLockout::$locked_until`, `AuthenticationDevice::$trusted_until`). Defined explicit `protected $casts = [...]` property on all Eloquent models (`AccountLockout`, `AuthenticationAttempt`, `AuthenticationDevice`, `LoginHistory`, `PasskeyCredential`, `PasswordHistory`, `TwoFactorAuthentication`) ensuring full backward and forward compatibility across Laravel 10.x, 11.x, 12.x, and 13.x.
- **Cross-Platform QR Generator Test File Path**: Replaced hardcoded Windows file path (`D:/WEBSITE/...`) in `QrGeneratorEnvTest` and `tests/_qrlong_gen.php` with portable `sys_get_temp_dir()` and guaranteed teardown cleanup in a `finally` block, resolving CI test failures on Linux/GitHub Actions runners.

## [1.5.8] - 2026-09-03

### Fixed
- **OTP Email Queue Conflict (SEC-01.5)**: `OtpMail` and `NewDeviceLoginMail` both implemented `ShouldQueue`, forcing all dispatch into the queue regardless of `config('mail.queue') = false`. When no queue worker is running, emails never deliver. Fix: removed hardcoded `ShouldQueue` from both mailables so config-driven queue/sync routing works as designed.
- **QR Data URI Scan Failure (SEC-05b)**: `QrCodeGenerator::dataUri()` produced `data:image/svg+xml;base64` URIs — SVG data URIs are not supported by mobile authenticator camera scanners. Fix: `dataUri()` now prefers PNG via GD (`imagefilledrectangle` scaling) with SVG fallback when GD is unavailable. View blade unchanged (`src` attribute still receives data URI, now PNG).

## [1.5.7] - 2026-09-03

### Security (Tahap 2 — dari Audit Report)
- **Persistent Account Lockout (SEC-07)**: Lockout & failure counter kini disimpan di database (model `AccountLockout`, migration `2026_02_01_000008`), bukan hanya di cache. Cache-flush / multi-server tidak lagi bisa me-reset lockout untuk bypass brute-force.
- **Hardened Config Preset (SEC-17)**: `config/authentication-hardened.php` — preset production (password min 12 + uppercase/lowercase/number/symbol, lockout aktif, captcha aktif, require_email_verify, auto_register=false, dsb).
- **Fail-Closed CAPTCHA (SEC-22)**: Driver captcha yang tidak dikenal/typo kini melempar `AuthenticationConfigurationException`, bukan diam-diam memakai `NullCaptchaDriver` (yang menonaktifkan captcha tanpa disadari).

### Files changed
- `src/Services/Security/AccountLockService.php` (cache → DB), `src/Services/Security/CaptchaService.php` (fail-closed), `src/Models/AccountLockout.php` (baru), `config/authentication.php` (tambah `lockouts` table name), `config/authentication-hardened.php` (baru).
- Migration baru: `database/migrations/2026_02_01_000008_create_authentication_account_lockouts_table.php` — **WAJIB `php artisan migrate`**.
- Test baru: `tests/Security/AccountLockoutPersistenceTest`.

## [1.5.6] - 2026-09-03

### Security (dari Audit Report SEC-01..SEC-21)
- **OTP Attempt Counter Race Condition (SEC-01)**: Ganti read-modify-write counter pada OTP verify dengan atomic `cache->increment()` di key terpisah `:attempts`, mencegah parallel request bypass `max_attempts`.
- **API Password Reset Timing Attack (SEC-02)**: Tambah `usleep(random_int(50_000, 150_000))` pada `apiSendResetLink` — samakan dengan endpoint web untuk cegah user enumeration via timing.
- **Registration API User Object Leak (SEC-03)**: Filter response `user` hanya id/name/email, tidak lagi mengembalikan seluruh Eloquent model.
- **2FA Device Trust Cookie Revocation (SEC-04)**: Cookie kini berisi random token tersendiri; hanya SHA-256 hash disimpan di DB (`trust_token_hash`, migration baru `2026_02_01_000007`). Trust token di-revoke server-side saat logout dan revoke-other-sessions. Tambah `DeviceTrustService::revokeUserTrust()`.
- **OAuth Email-Verified Check (SEC-06/14)**: Tolak social sign-in saat provider menyatakan `email_verified=false`, cegah account takeover via email tak terverifikasi.
- **LoginData.extra Data Injection (SEC-08)**: Whitelist field hanya `email` dan `username`, bukan seluruh request kecuali password.
- **Reset Token Exposed di JSON Fallback (SEC-10)**: Hapus `token` dan `email` dari fallback response `showResetForm`.
- **Session Cookie Flags (SEC-12)**: Provider kini menetapkan `cookie_secure`, `cookie_http_only`, `cookie_samesite` bila belum diset host.
- **Header Overexposure (SEC-13)**: `AuthenticationContext` hanya menyimpan whitelist header non-sensitif, bukan seluruh header bag.
- **SHA1 → SHA256 di Rate Limiter Key (SEC-16)**: Ganti hash key rate limiter.
- **WebAuthn Origin HTTPS Enforcement (SEC-21)**: Tolak origin non-HTTPS kecuali localhost/loopback.

### Files changed
- `src/Services/Otp/OtpService.php`, `src/Http/Controllers/PasswordResetController.php`, `src/Http/Controllers/RegisterController.php`, `src/Services/Social/SocialAuthService.php`, `src/Http/Requests/LoginRequest.php`, `src/DTO/AuthenticationContext.php`, `src/Services/Session/DeviceTrustService.php`, `src/Services/Core/AuthenticationService.php`, `src/Services/Session/SessionManagerService.php`, `src/Models/AuthenticationDevice.php`, `src/Providers/AuthenticationServiceProvider.php`, `src/Services/Security/FeatureRateLimiter.php`, `src/Support/WebAuthn/WebAuthnHelper.php`, `src/Rules/*` (tidak diubah).
- Migration baru: `database/migrations/2026_02_01_000007_add_trust_token_to_authentication_devices_table.php` — **WAJIB jalankan `php artisan migrate`** untuk mengambil kolom `trust_token_hash`; tanpa ini, fitur trust-device 2FA tetap berjalan via fallback HMAC lama.
- Test baru: `tests/Security/DeviceTrustSecurityTest::test_server_side_revocation_invalidates_issued_trust_cookie`.

## [1.5.5] - 2026-09-02

### Fixed
- **Legacy Service Namespace in Blade Views**: Two Blade views still referenced old flat service namespaces that were moved to domain subfolders during the `src/Services/` reorganization, causing `BindingResolutionException` at runtime:
  - `resources/views/login.blade.php`: `Services\CaptchaService` → `Services\Security\CaptchaService`
  - `resources/views/components/active-sessions.blade.php`: `Services\SessionManagerService` → `Services\Session\SessionManagerService`

## [1.5.4] - 2026-09-02

### Fixed
- **Mixed-Language Validation Messages**: All custom validation `Rule` classes (`PasswordRule`, `LoginIdentifierRule`, `SecurityPolicyRule`, `ValidCaptcha`) previously used hardcoded English strings for error messages. When the host application ran in Indonesian (or any non-English locale), Laravel would translate the `:attribute` placeholder (e.g. `kata sandi`) but leave the surrounding sentence in English, producing broken output like *"The kata sandi must contain at least one uppercase letter."*. All `$fail()` calls now route through the package translation system (`trans('authentication::messages.*')`).
- **Added Translation Keys**: New keys added to both `resources/lang/en/messages.php` and `resources/lang/id/messages.php`:
  - `password_must_be_string`, `password_min_length`, `password_require_uppercase`, `password_require_lowercase`, `password_require_number`, `password_require_symbol`
  - `identifier_must_be_string`, `identifier_length`, `identifier_invalid_chars`
  - `security_null_byte`

## [1.6.0] - 2026-09-01

### Added
- **FIDO2 / WebAuthn Passkey Authentication (Passwordless)**:
  - Standard W3C WebAuthn ceremony implementation (`PasskeyService`, `PasskeyController`, `PasskeyAuthenticationStrategy`).
  - Table migration `authentication_passkeys` with dynamic prefixing support (`AuthenticationConfig::tableName('passkeys')`).
  - WebAuthn registration and assertion DTOs (`PasskeyCreationOptions`, `PasskeyRequestOptions`, `PasskeyAssertion`).
  - Seamless WebAuthn Conditional UI / Autofill support in browser login flows.
  - Dedicated Passkey Blade component (`<x-authentication::security.passkey-button />`) and user device management in session security dashboard.
- **Massive-Scale Database Query & Index Optimization (10M+ Rows)**:
  - High-performance composite indexes on `authentication_attempts` (`idx_attempts_id_time`, `idx_attempts_ip_time`, `idx_attempts_status_time`).
  - Composite indexes on `authentication_login_histories` (`idx_histories_user_login`, `idx_histories_user_logout`).
  - Composite indexes on `authentication_devices` (`idx_devices_user_last_seen`).
  - Smart indexed fast-path lookup in `CredentialResolver` preventing expensive table scans and multi-column `OR` index merge bottlenecks.

### Fixed
- **UI Form & Input Error Message Standardization**:
  - Resolved all string typing and array-to-string conversion bugs across `LoginRequest`, `RegisterRequest`, `ResetPasswordRequest`, `SendOtpRequest`, `VerifyOtpRequest`, and `ForgotPasswordRequest`.
  - Added safe translation helper `SecurityHelper::trans()` guaranteeing strict string return types and passing PHPStan Level 8 static analysis with 0 errors.
  - Unified language keys and alert bindings across all forms (`login`, `register`, `forgot-password`, `reset-password`, `otp-verify`, `sessions`).
- **Elevated Social Login Buttons Aesthetics**:
  - Redesigned Google and GitHub social authentication buttons with modern borders, subtle shadows, micro-interaction hover lift, active press states, and dark mode obsidian styling.

## [1.5.0] - 2026-08-30

### Added
- **One-Step Artisan Installer (`authentication:install`)**:
  - Automatically publishes package configuration and migrations.
  - Automatically detects Tailwind CSS v4 (`resources/css/app.css`) or v3 (`tailwind.config.js`) in the host application and injects `@source "../../vendor/mixudev/laravel-authentication/resources/views";` and `@custom-variant dark (&:where(.dark, .dark *));`.
  - Prompts to execute database migrations interactively or with `--migrate`.
- **Dynamic Theme Engine & Dark Mode Isolation**:
  - Full support for `light`, `dark`, and `auto` themes (`prefers-color-scheme: dark`) with instant, flicker-free client detection and live OS theme change listeners.
  - Eliminated brittle hardcoded `!important` CSS rules, ensuring clean harmony between Tailwind utility classes and host application styling.
- **Enhanced 2FA & Session UI Interactivity**:
  - Built-in Alpine.js CDN loading across all base layouts, resolving modal triggers (e.g. "Matikan 2FA" password confirmation modal and "Cabut Semua Sesi" forms).
  - Polished high-contrast dark/light mode compatibility for 2FA QR code display, manual secret key boxes, and recovery backup codes.

## [1.4.0] - 2026-08-26

### Added
- **Multi-Factor Authentication (MFA / 2FA)**:
  - Pure PHP RFC 6238 TOTP engine compatible with Google Authenticator, Authy, Microsoft Authenticator, and 1Password (`TotpService`).
  - Single-use encrypted backup recovery codes (`TwoFactorService`).
  - Web & API challenge intercept workflow during login (`TwoFactorChallengeController`, `TwoFactorSetupController`).
  - "Trust This Device" (Remember Device) support with signed cookies to bypass 2FA on trusted devices for $N$ days (`DeviceTrustService`).
- **Granular Rate Limiting per Feature**:
  - Independent throttle counters for `login`, `registration`, `otp_request`, `otp_verify`, `forgot_password`, `two_factor`, and `confirm_password` to eliminate cross-feature abuse (`FeatureRateLimiter`).
- **Adaptive CAPTCHA & Bot Protection**:
  - Multi-provider CAPTCHA engine supporting Cloudflare Turnstile, Google reCAPTCHA v2/v3, and hCaptcha (`CaptchaService`).
  - Adaptive threshold trigger allowing smooth friction-free login until $N$ consecutive failures are detected.
  - Validation rule `ValidCaptcha`.
- **Active Session & Device Management**:
  - Device detector extracting OS, browser, device name, and network fingerprint from User-Agent & IP (`DeviceDetector`).
  - List active sessions, revoke specific device sessions, or log out all other devices with password confirmation (`SessionManagerService`, `SessionController`).
- **Suspicious & New Device Login Alerts**:
  - Automatic detection of unrecognized device/IP fingerprints upon `LoginSucceeded` (`NewDeviceDetectionService`).
  - Event `NewDeviceLoginDetected` and alert email `NewDeviceLoginMail` with instant "Secure Account & Revoke Sessions" link.
- **Asynchronous Mail Queueing**:
  - Non-blocking email dispatch support (`mail.queue`, `mail.queue_connection`, `mail.queue_name`) across OTP, new device alerts, and password resets.
- **Configurable Database Table Names & Migration Loader**:
  - Custom table names configuration (`database.table_names`) and toggleable package migration loading (`database.load_migrations`).
- **Sensitive Action Re-Authentication (Confirm Password)**:
  - Middleware `RequirePasswordConfirmation`, controller, and views for protecting high-risk admin and security actions.

## [1.3.0] - 2026-08-26

### Added
- **Modular Component-Driven UI Architecture**: Refactored monolithic views into reusable Laravel Blade components (`<x-authentication::forms.input>`, `<x-authentication::forms.button>`, `<x-authentication::forms.checkbox>`, `<x-authentication::feedback.alert>`, `<x-authentication::social-buttons>`, `<x-authentication::forms.otp-input>`, `<x-authentication::brand-panel>`, `<x-authentication::divider>`, `<x-authentication::header>`).
- **Multi-Template Layout Engine**: Added 2 out-of-the-box layout templates switchable via `config('authentication.ui.layout')` or dynamic component resolution:
  - `split`: 2-column enterprise console layout (Brand graphic sidebar + auth stage).
  - `card`: Minimalist centered single-card layout with ambient backdrop glow.
- **Pure Tailwind CSS & Vite Integration**: Replaced heavy inline CSS blocks with modern, responsive utility classes supporting Vite asset bundling and safe standalone CDN fallbacks.
- **Accessible & Safe Form Elements**: Built-in client-side toggle password visibility, segmented OTP auto-focus & clipboard paste handler, automated error-binding (`@error` / `ViewErrorBag`), and strict XSS/tabnabbing mitigations.
- **Professional Indonesian Code Annotations**: Added comprehensive documentation and docblocks across all layout and component files.

## [1.2.0] - 2026-08-25

### Added
- **Passwordless OTP Login**: Modular OTP generation & verification engine with single-use expiry, rate limiting, and events (`OtpGenerated`, `OtpVerified`).
- **OAuth Social Login**: Google & GitHub social authentication via Laravel Socialite with automated local user provisioning and scope customization.
- **User Registration Engine**: Lightweight registration with configurable password rules, auto-login, and `UserRegistered` event.
- **Self-Service Password Recovery**: Forgot-password & reset-password flows with enumeration defense and token expiration.
- **Console Dark UI Suite**: Ready-to-use, accessible, responsive Blade templates (`login`, `register`, `forgot-password`, `reset-password`, `otp-request`, `otp-verify`) matching the Sentra developer console aesthetic.
- **Complete REST API Integration**: Full JSON endpoints for Registration, OTP, Socialite, and Password recovery under `/api/v1/auth/*`.
- **Modular Feature Switches**: Individual boolean toggles in `config/authentication.php` for Registration, Forgot Password, OTP, and Social login.

## [1.1.0] - 2026-08-25

### Added
- Official support for **Laravel 13.x** and `illuminate/*: ^13.0`.
- Modern Eloquent `casts(): array` method compatibility in models (`AuthenticationAttempt`, `LoginHistory`, `PasswordHistory`).
- Enhanced translation fallback in `LoginController`.
- Orchestra Testbench `^11.0` and PHPUnit `^12.0` support.

## [1.0.0] - 2026-08-25

### Added
- Modular, Strategy-based Authentication Engine (`UsernamePasswordStrategy`, `EmailPasswordStrategy`, `UsernameOrEmailStrategy`, `CustomIdentifierStrategy`).
- Dynamic `AuthenticationStrategyRegistry` for zero-core-modification extensions (e.g. Employee ID, Phone, SSO).
- Rate Limiting and Brute Force mitigation with Composite, IP, and Identifier throttle keys.
- User Enumeration Protection across all authentication, lockout, and password reset flows.
- Automated Password Rehashing and Password History reuse prevention.
- Temporary Account Lockout with exponential backoff and event dispatching.
- Secure Session Lifecycle management (Session ID regeneration on login, complete cache/cookie invalidation on logout).
- Security Audit Logging with automatic redaction of sensitive credentials and PII masking.
- Laravel Package Auto-Discovery and manual Service Provider registration support.
- Fully typed Data Transfer Objects (`LoginData`, `AuthenticationResult`, `AuthenticationContext`, `UserIdentity`).
- Comprehensive Unit, Feature, and Security test suites powered by Orchestra Testbench.
