# Changelog

All notable changes to `vendor/laravel-authentication` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
  - Dedicated Passkey Blade component (`<x-authentication::passkey-button />`) and user device management in session security dashboard.
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
- **Modular Component-Driven UI Architecture**: Refactored monolithic views into reusable Laravel Blade components (`<x-authentication::input>`, `<x-authentication::button>`, `<x-authentication::checkbox>`, `<x-authentication::alert>`, `<x-authentication::social-buttons>`, `<x-authentication::otp-input>`, `<x-authentication::brand-panel>`, `<x-authentication::divider>`, `<x-authentication::header>`).
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
