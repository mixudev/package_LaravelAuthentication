# AI Agent Architecture & Development Guidelines (`AGENTS.md`)

This document serves as the canonical knowledge base and operating manual for AI coding agents maintaining, extending, or debugging the **`mixudev/laravel-authentication`** package (`Vendor\LaravelAuthentication\`).

---

## 1. Package Identity & Core Invariants

- **Composer Package Name**: `mixudev/laravel-authentication`
- **Root Namespace**: `Vendor\LaravelAuthentication\`
- **Target Environments**: PHP 8.1 - 8.5+ | Laravel 10.x, 11.x, 12.x, 13.x
- **Distribution Model**: Canonical Git repository powering Packagist, Private Git VCS, Local Path Repositories, and Tagged ZIPs from the exact same codebase.

### Strict Architectural Invariants (DO NOT VIOLATE):
1. **Zero Hardcoded Application Coupling**: Never import `App\Models\User`, `App\Http\*`, or any host application namespace. All host integrations must go through `config('authentication.user_model')`, interfaces, or dependency injection.
2. **Fail-Closed by Design**: Unhandled strategy lookups, corrupted configs, or unknown channels MUST throw explicit exceptions (e.g. `InvalidStrategyException`, `AuthenticationConfigurationException`) rather than falling back to an insecure default.
3. **Sensitive Data Redaction**:
   - Every raw password and secret argument MUST use PHP 8.2+ `#[\SensitiveParameter]`.
   - Never pass plaintext passwords, reset tokens, or API secrets into events, logs, or exception messages.
4. **User Enumeration Mitigations**:
   - Invalid credentials vs non-existent user lookups MUST return identical exception types (`InvalidCredentialsException`) and identical user-facing error messages with normalized timing.
5. **Strict Typing & Immutability**:
   - Every PHP file MUST start with `declare(strict_types=1);`.
   - DTOs (`LoginData`, `AuthenticationResult`, `AuthenticationContext`, `UserIdentity`) are `readonly` and immutable.

---

## 2. Request Lifecycle & Pipeline Flow

When an authentication request occurs, the flow is strictly structured as follows:

```
1. HTTP Request (Form / JSON)
        ↓
2. Form Request Validation (LoginRequest + Rules: LoginIdentifierRule, SecurityPolicyRule)
        ↓
3. Rate Limiter (LoginAttemptManager - Composite Key sha1(identifier + ip))
        ↓ (Throws AuthenticationThrottledException if limit exceeded)
4. Authentication Orchestrator (AuthenticationService::authenticate)
        ↓ (Dispatches LoginAttempted event)
5. Strategy Resolution (AuthenticationStrategyRegistry -> get(strategy_name))
        ↓
6. Identifier Normalization (IdentifierNormalizer: lowercase email / trimmed username)
        ↓
7. Identity Resolution (CredentialResolver -> lookup Eloquent user by column)
        ↓
8. Account Lockout Check (AccountLockService::isLocked)
        ↓ (Throws AccountLockedException if locked)
9. Credential Verification (CredentialValidator: Hasher check + auto-rehash if needed)
        ↓ (On failure: Record failure, check lockout, dispatch LoginFailed, throw InvalidCredentialsException)
10. Success Lifecycle:
        - Clear rate limit & lockout counters
        - Stateful Session Login + session()->regenerate() [Web] OR TokenService::createToken [API]
        - Dispatch LoginSucceeded event
        - Log audit trail (AuthenticationAuditService with masked PII)
        - Return AuthenticationResult DTO
```

---

## 3. Directory Layout & Layer Responsibilities

```
src/
├── Contracts/       # Public API Interfaces (Decouples services from implementations)
├── DTO/             # Immutable data carriers (LoginData, AuthenticationResult, etc.)
├── Enums/           # Strongly typed constants (LoginMethod, AuthenticationStatus, SecurityEventType)
├── Events/          # Framework events (Payloads contain NO passwords or raw tokens)
├── Exceptions/      # Typed exception hierarchy (Rooted at AuthenticationException)
├── Http/
│   ├── Controllers/ # Web & API Controller actions
│   ├── Middleware/  # EnsureSessionSecurity, CheckAccountLockout, AuthenticateWithCustomGuard
│   └── Requests/    # FormRequests with strict validation rules
├── Models/          # Eloquent models for attempts, login history, and password history
├── Providers/       # AuthenticationServiceProvider & RouteServiceProvider
├── Repositories/    # Database persistence abstraction layer
├── Rules/           # Invokable Laravel validation rules
├── Services/        # Core business engines (AuthenticationService, PasswordService, etc.)
├── Strategies/      # Strategy implementations (Username, Email, Composite, Custom)
└── Support/         # Helpers, Normalizers, and StrategyRegistry
```

---

## 4. How to Extend the Package (For Future AI Agents)

### A. Adding a New Authentication Strategy
1. Create a strategy class in `src/Strategies/` extending `AbstractAuthenticationStrategy` (or implementing `AuthenticationStrategyInterface`).
2. Register it in `src/Providers/AuthenticationServiceProvider.php` and `config/authentication.php`.
3. Add corresponding Unit and Feature tests in `tests/`.

### B. Adding a New Security Policy / Rule
1. Implement `Illuminate\Contracts\Validation\ValidationRule` in `src/Rules/`.
2. Attach the rule to `LoginRequest` or expose it for host applications in `config/authentication.php`.

---

## 5. Verification & Testing Standards

Before proposing or committing any code changes:
1. **PHP Syntax & Lints**: Run `php -l` on all modified files.
2. **Composer Integrity**: Run `composer validate --strict`.
3. **Automated Tests**: Execute PHPUnit tests (`vendor/bin/phpunit`).
4. **Static Analysis**: Maintain PHPStan Level 8 without errors (`vendor/bin/phpstan analyse`).

---

## 6. Git Commit, Versioning & Release Protocol

Whenever making changes to this codebase:
1. Follow **Conventional Commits**:
   - `feat: <description>` (Minor feature)
   - `fix: <description>` (Bugfix / patch)
   - `docs: <description>` (Documentation update)
   - `refactor: <description>` (Code refactoring)
   - `feat!: <description>` (Breaking change)
2. Update [`CHANGELOG.md`](file:///d:/WEBSITE/PACKAGE/LaravelAuthentication/CHANGELOG.md) under the appropriate version header.
3. If creating a new release:
   - Create a SemVer tag (e.g. `v1.0.1`, `v1.1.0`).
   - Push both branch and tags:
     ```bash
     git push origin main
     git push origin <tag_name>
     ```
