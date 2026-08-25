# Changelog

All notable changes to `vendor/laravel-authentication` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
