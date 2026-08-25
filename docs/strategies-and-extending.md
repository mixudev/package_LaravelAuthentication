# Strategies & Extending the Package

## 1. Built-in Strategies

The package provides several out-of-the-box authentication strategies:

| Strategy Key | Class | Behavior |
| :--- | :--- | :--- |
| `username_or_email` | `UsernameOrEmailStrategy` | Automatically detects email or username and authenticates. |
| `email_password` | `EmailPasswordStrategy` | Strictly expects email format. |
| `username_password` | `UsernamePasswordStrategy` | Matches strictly against the username column. |
| `custom_identifier` | `CustomIdentifierStrategy` | Matches against configured custom column (e.g., `employee_id`). |

---

## 2. Writing a Custom Strategy

You can add custom strategies (such as Employee ID, Phone Number, SSO, etc.) without modifying package source files.

### Step 1: Create Strategy Class
```php
namespace App\Authentication\Strategies;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\Contracts\AuthenticationStrategyInterface;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EmployeeIdStrategy implements AuthenticationStrategyInterface
{
    public function name(): string
    {
        return 'employee_id';
    }

    public function supports(LoginData $data): bool
    {
        return str_starts_with($data->identifier, 'EMP-');
    }

    public function resolveUser(LoginData $data, AuthenticationContext $context): ?Authenticatable
    {
        return User::where('employee_id', $data->identifier)->first();
    }

    public function validateCredentials(Authenticatable $user, LoginData $data): bool
    {
        return Hash::check($data->password, $user->getAuthPassword());
    }
}
```

### Step 2: Register in `AppServiceProvider`
```php
use Vendor\LaravelAuthentication\Support\AuthenticationStrategyRegistry;
use App\Authentication\Strategies\EmployeeIdStrategy;

public function boot(AuthenticationStrategyRegistry $registry): void
{
    $registry->register('employee_id', EmployeeIdStrategy::class);
}
```

---

## 3. Listening to Authentication Events

The package dispatches clean lifecycle events that you can listen to:

| Event | Dispatched When | Payload Contains |
| :--- | :--- | :--- |
| `LoginAttempted` | Form request submitted | `identifier`, `context` |
| `LoginSucceeded` | User credentials validated & logged in | `user`, `context`, `strategy` |
| `LoginFailed` | Bad credentials / locked user attempt | `identifier`, `reason`, `context` |
| `UserRegistered` | New user created | `user`, `context` |
| `OtpGenerated` | OTP code created | `user`, `identifier`, `code`, `context`, `expiryMinutes` |
| `OtpVerified` | OTP code successfully matched | `user`, `identifier`, `context` |
| `AccountLocked` | Lockout threshold breached | `identifier`, `lockoutUntil`, `context` |
| `PasswordReset` | Password successfully updated | `user`, `context` |

### Example Listener for Sending OTP via WhatsApp / SMS:
```php
namespace App\Listeners;

use Vendor\LaravelAuthentication\Events\OtpGenerated;

class SendOtpNotification
{
    public function handle(OtpGenerated $event): void
    {
        // $event->identifier, $event->code, $event->expiryMinutes
        // Send SMS/WhatsApp or custom notification
    }
}
```
