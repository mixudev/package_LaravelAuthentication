# Laravel Authentication Package Documentation

Welcome to the comprehensive documentation for **`mixudev/laravel-authentication`** (`Vendor\LaravelAuthentication\`).

This package provides an enterprise-grade, modular, portable, and secure authentication architecture for Laravel 10.x, 11.x, 12.x, and 13.x applications.

---

## 📚 Table of Contents

- [1. Getting Started](getting-started.md)
  - Installation via Packagist, VCS, or Path Repository
  - Publishing configuration, migrations, and views
  - Database schema & migrations
- [2. Features & Modules](features.md)
  - Modular feature switches (`config/authentication.php`)
  - User Registration Engine
  - Passwordless OTP Authentication
  - OAuth Social Login (Google & GitHub via Socialite)
  - Self-Service Password Recovery & Reset
- [3. Views & UI Customization](views-customization.md)
  - Dark Console UI Theme & Styling Tokens
  - Live interactive validation & password strength meters
  - Overriding and customizing Blade templates
- [4. Strategies & Extending](strategies-and-extending.md)
  - Built-in strategies (`username_or_email`, `email_password`, `username_password`, `custom_identifier`)
  - Writing custom authentication strategies (e.g. Employee ID, Phone Number)
  - Event Dispatching & Custom Listeners
- [5. REST API Reference](api-reference.md)
  - Full API endpoint catalog (`/api/v1/auth/*`)
  - Request & response JSON schemas
  - Token management with Laravel Sanctum
- [6. Security & Best Practices](security-and-best-practices.md)
  - Composite rate limiting & brute-force defense
  - User enumeration mitigations
  - Password rehashing & historical reuse prevention
  - Session fixation defense & audit trail logging
