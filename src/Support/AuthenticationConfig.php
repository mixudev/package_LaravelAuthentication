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
}
