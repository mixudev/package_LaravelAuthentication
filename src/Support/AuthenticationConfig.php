<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException;

/**
 * Type-safe configuration wrapper providing fail-closed lookups and validation.
 */
final class AuthenticationConfig
{
    public function __construct(
        private readonly ConfigRepository $config
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('authentication.enabled', true);
    }

    public function getGuard(): string
    {
        return (string) $this->config->get('authentication.guard', 'web');
    }

    public function getUserModel(): string
    {
        $model = (string) $this->config->get('authentication.user_model', 'App\\Models\\User');

        if (!class_exists($model)) {
            // In unit tests or minimal setups, fallback gracefully if configured
            if (!app()->runningUnitTests()) {
                throw new AuthenticationConfigurationException("Configured user model [{$model}] does not exist.");
            }
        }

        return $model;
    }

    public function getDefaultStrategy(): string
    {
        return (string) $this->config->get('authentication.login.default_strategy', 'username_or_email');
    }

    public function getIdentifierColumn(string $type): string
    {
        $column = (string) $this->config->get("authentication.login.identifiers.{$type}_column");

        if (empty($column)) {
            return match ($type) {
                'email'    => 'email',
                'username' => 'username',
                'custom'   => 'employee_id',
                'password' => 'password',
                default    => throw new AuthenticationConfigurationException("Identifier column for [{$type}] is not defined."),
            };
        }

        return $column;
    }

    public function isRateLimitEnabled(): bool
    {
        return (bool) $this->config->get('authentication.security.rate_limit.enabled', true);
    }

    public function getRateLimitMaxAttempts(): int
    {
        return (int) $this->config->get('authentication.security.rate_limit.max_attempts', 5);
    }

    public function getRateLimitDecayMinutes(): int
    {
        return (int) $this->config->get('authentication.security.rate_limit.decay_minutes', 1);
    }

    public function getRateLimitStrategy(): string
    {
        return (string) $this->config->get('authentication.security.rate_limit.strategy', 'composite');
    }

    public function isLockoutEnabled(): bool
    {
        return (bool) $this->config->get('authentication.security.account_lockout.enabled', false);
    }

    public function getLockoutMaxAttempts(): int
    {
        return (int) $this->config->get('authentication.security.account_lockout.max_failed_attempts', 5);
    }

    public function getLockoutDurationMinutes(): int
    {
        return (int) $this->config->get('authentication.security.account_lockout.lockout_duration_mins', 15);
    }

    public function shouldNormalizeIdentifiers(): bool
    {
        return (bool) $this->config->get('authentication.login.normalize_identifiers', true);
    }

    public function isPasswordRehashEnabled(): bool
    {
        return (bool) $this->config->get('authentication.password.rehash', true);
    }

    public function isPasswordHistoryEnabled(): bool
    {
        return (bool) $this->config->get('authentication.password.history.enabled', false);
    }

    public function getPasswordHistoryCount(): int
    {
        return (int) $this->config->get('authentication.password.history.remember', 5);
    }

    public function isAuditEnabled(): bool
    {
        return (bool) $this->config->get('authentication.audit.enabled', true);
    }

    public function getAuditDriver(): string
    {
        return (string) $this->config->get('authentication.audit.driver', 'database');
    }

    public function isRegistrationEnabled(): bool
    {
        return (bool) $this->config->get('authentication.features.registration.enabled', true);
    }

    public function shouldAutoLoginOnRegister(): bool
    {
        return (bool) $this->config->get('authentication.features.registration.auto_login_on_register', true);
    }

    public function isForgotPasswordEnabled(): bool
    {
        return (bool) $this->config->get('authentication.features.forgot_password.enabled', true);
    }

    public function isOtpEnabled(): bool
    {
        return (bool) $this->config->get('authentication.features.otp.enabled', true);
    }

    public function getOtpLength(): int
    {
        return (int) $this->config->get('authentication.features.otp.length', 6);
    }

    public function getOtpExpiryMinutes(): int
    {
        return (int) $this->config->get('authentication.features.otp.expiry_minutes', 10);
    }

    public function getOtpMaxAttempts(): int
    {
        return (int) $this->config->get('authentication.features.otp.max_attempts', 3);
    }

    public function getOtpThrottleSeconds(): int
    {
        return (int) $this->config->get('authentication.features.otp.throttle_seconds', 60);
    }

    public function getOtpType(): string
    {
        return (string) $this->config->get('authentication.features.otp.type', 'numeric');
    }

    public function isSocialEnabled(): bool
    {
        return (bool) $this->config->get('authentication.features.social.enabled', true);
    }

    public function isSocialAutoRegisterEnabled(): bool
    {
        return (bool) $this->config->get('authentication.features.social.auto_register', true);
    }

    public function isSocialProviderEnabled(string $provider): bool
    {
        return (bool) $this->config->get("authentication.features.social.providers.{$provider}.enabled", false);
    }

    /**
     * @return array<int, string>
     */
    public function getSocialProviderScopes(string $provider): array
    {
        return (array) $this->config->get("authentication.features.social.providers.{$provider}.scopes", []);
    }

    public function getRedirect(string $key, string $default = '/dashboard'): string
    {
        return (string) $this->config->get("authentication.redirects.{$key}", $default);
    }
}
