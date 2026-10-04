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

    /**
     * Get dynamic table name from config with fallback.
     */
    public function getTableName(string $key, string $default = ''): string
    {
        $defaultMap = [
            'attempts'           => 'authentication_attempts',
            'login_histories'    => 'authentication_login_histories',
            'password_histories' => 'authentication_password_histories',
            'two_factor'         => 'authentication_two_factors',
            'devices'            => 'authentication_devices',
            'sessions'           => 'authentication_sessions',
            'passkeys'           => 'authentication_passkeys',
        ];

        $fallback = $default ?: ($defaultMap[$key] ?? "authentication_{$key}");

        return (string) $this->config->get("authentication.database.table_names.{$key}", $fallback);
    }

    public static function tableName(string $key, string $default = ''): string
    {
        /** @var ConfigRepository $config */
        $config = config();
        $defaultMap = [
            'attempts'           => 'authentication_attempts',
            'login_histories'    => 'authentication_login_histories',
            'password_histories' => 'authentication_password_histories',
            'two_factor'         => 'authentication_two_factors',
            'devices'            => 'authentication_devices',
            'sessions'           => 'authentication_sessions',
            'passkeys'           => 'authentication_passkeys',
        ];

        $fallback = $default ?: ($defaultMap[$key] ?? "authentication_{$key}");

        return (string) $config->get("authentication.database.table_names.{$key}", $fallback);
    }

    public function shouldLoadMigrations(): bool
    {
        return (bool) $this->config->get('authentication.database.load_migrations', true);
    }

    public function isMailQueueEnabled(): bool
    {
        return (bool) $this->config->get('authentication.mail.queue', false);
    }

    public function getMailQueueConnection(): ?string
    {
        return $this->config->get('authentication.mail.queue_connection');
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

    /**
     * Granular Rate Limits
     *
     * @return array{enabled: bool, max_attempts: int, decay_minutes: int, strategy: string, challenge_threshold: int, challenge_token_ttl: int}
     */
    public function getRateLimitConfig(string $feature = 'login'): array
    {
        // Check new granular rate_limits config, with backward compatibility fallback
        $featureConfig = $this->config->get("authentication.security.rate_limits.{$feature}");

        if (is_array($featureConfig)) {
            return [
                'enabled'       => (bool) ($featureConfig['enabled'] ?? true),
                'max_attempts'  => (int) ($featureConfig['max_attempts'] ?? 5),
                'decay_minutes' => (int) ($featureConfig['decay_minutes'] ?? 1),
                'strategy'             => (string) ($featureConfig['strategy'] ?? 'composite'),
                'challenge_threshold'  => (int) ($featureConfig['challenge_threshold'] ?? 0),
                'challenge_token_ttl'  => (int) ($featureConfig['challenge_token_ttl'] ?? 300),
            ];
        }

        return [
            'enabled'       => (bool) $this->config->get('authentication.security.rate_limit.enabled', true),
            'max_attempts'  => (int) $this->config->get('authentication.security.rate_limit.max_attempts', 5),
            'decay_minutes' => (int) $this->config->get('authentication.security.rate_limit.decay_minutes', 1),
            'strategy'      => (string) $this->config->get('authentication.security.rate_limit.strategy', 'composite'),
            'challenge_threshold' => (int) $this->config->get('authentication.security.rate_limit.challenge_threshold', 0),
            'challenge_token_ttl' => (int) $this->config->get('authentication.security.rate_limit.challenge_token_ttl', 300),
        ];
    }

    /**
     * Get validated enterprise abuse-policy dimensions.
     *
     * The policy is opt-in so existing installations retain legacy behavior.
     *
     * @return array{enabled: bool, dimensions: array<string, array{max_attempts: int, decay_minutes: int}>}
     */
    public function getAbusePolicyConfig(): array
    {
        $raw = $this->config->get('authentication.security.abuse_policy', [
            'enabled' => false,
            'dimensions' => [],
        ]);

        if (!is_array($raw)) {
            throw new AuthenticationConfigurationException('Authentication abuse policy must be an array.');
        }

        $dimensions = $raw['dimensions'] ?? [];
        if (!is_array($dimensions)) {
            throw new AuthenticationConfigurationException('Authentication abuse policy dimensions must be an array.');
        }

        $allowedDimensions = ['account', 'account_ip', 'client', 'network', 'global'];
        $normalized = [];

        foreach ($dimensions as $name => $settings) {
            if (!is_string($name) || !in_array($name, $allowedDimensions, true)) {
                throw new AuthenticationConfigurationException("Unknown authentication abuse dimension [{$name}].");
            }

            if (!is_array($settings)) {
                throw new AuthenticationConfigurationException("Authentication abuse dimension [{$name}] must be an array.");
            }

            $maxAttempts = (int) ($settings['max_attempts'] ?? 0);
            $decayMinutes = (int) ($settings['decay_minutes'] ?? 0);

            if ($maxAttempts < 1 || $decayMinutes < 1) {
                throw new AuthenticationConfigurationException(
                    "Authentication abuse dimension [{$name}] requires positive max_attempts and decay_minutes."
                );
            }

            $normalized[$name] = [
                'max_attempts' => $maxAttempts,
                'decay_minutes' => $decayMinutes,
            ];
        }

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'dimensions' => $normalized,
        ];
    }

    public function isRateLimitEnabled(string $feature = 'login'): bool
    {
        return $this->getRateLimitConfig($feature)['enabled'];
    }

    public function getRateLimitMaxAttempts(string $feature = 'login'): int
    {
        return $this->getRateLimitConfig($feature)['max_attempts'];
    }

    public function getRateLimitDecayMinutes(string $feature = 'login'): int
    {
        return $this->getRateLimitConfig($feature)['decay_minutes'];
    }

    public function getRateLimitStrategy(string $feature = 'login'): string
    {
        return $this->getRateLimitConfig($feature)['strategy'];
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

    public function isAuditQueued(): bool
    {
        return (bool) $this->config->get('authentication.audit.queue', false);
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

    // Two-Factor Authentication (2FA / TOTP)
    public function isTwoFactorEnabled(): bool
    {
        return (bool) $this->config->get('authentication.features.two_factor.enabled', false);
    }

    public function getTwoFactorDigits(): int
    {
        return (int) $this->config->get('authentication.features.two_factor.digits', 6);
    }

    public function getTwoFactorPeriod(): int
    {
        return (int) $this->config->get('authentication.features.two_factor.period', 30);
    }

    public function getTwoFactorWindow(): int
    {
        return (int) $this->config->get('authentication.features.two_factor.window', 1);
    }

    public function getTwoFactorBackupCodesCount(): int
    {
        return (int) $this->config->get('authentication.features.two_factor.backup_codes_count', 8);
    }

    public function getTwoFactorIssuer(): string
    {
        return (string) $this->config->get('authentication.features.two_factor.issuer', config('app.name', 'Laravel'));
    }

    public function isDeviceTrustEnabled(): bool
    {
        return (bool) $this->config->get('authentication.features.two_factor.trust_device.enabled', false);
    }

    public function getDeviceTrustDurationDays(): int
    {
        return (int) $this->config->get('authentication.features.two_factor.trust_device.duration_days', 30);
    }

    public function getDeviceTrustCookieName(): string
    {
        return (string) $this->config->get('authentication.features.two_factor.trust_device.cookie_name', 'auth_trusted_device');
    }

    // Audit retention
    public function getAuditRetentionDays(): int
    {
        return (int) $this->config->get('authentication.audit.retention_days', 90);
    }

    // Confirm Password
    public function isConfirmPasswordEnabled(): bool
    {
        return (bool) $this->config->get('authentication.features.confirm_password.enabled', true);
    }

    public function getConfirmPasswordTimeout(): int
    {
        return (int) $this->config->get('authentication.features.confirm_password.timeout_seconds', 900);
    }

    // Session Management
    public function isSessionManagementEnabled(): bool
    {
        return (bool) $this->config->get('authentication.features.session_management.enabled', true);
    }

    public function getMaxActiveSessions(): int
    {
        return (int) $this->config->get('authentication.features.session_management.max_active_sessions', 5);
    }

    // CAPTCHA
    public function isCaptchaEnabled(): bool
    {
        return (bool) $this->config->get('authentication.security.captcha.enabled', false);
    }

    public function getCaptchaDriver(): string
    {
        return (string) $this->config->get('authentication.security.captcha.driver', 'turnstile');
    }

    public function getCaptchaTriggerThreshold(): int
    {
        return (int) $this->config->get('authentication.security.captcha.trigger_after_failed_attempts', 3);
    }

    public function getCaptchaSiteKey(): string
    {
        return (string) $this->config->get('authentication.security.captcha.site_key', '');
    }

    public function getCaptchaSecretKey(): string
    {
        return (string) $this->config->get('authentication.security.captcha.secret_key', '');
    }

    // New Device Notification
    public function isNewDeviceNotificationEnabled(): bool
    {
        return (bool) $this->config->get('authentication.security.new_device_notification.enabled', true);
    }

    public function shouldIncludeLocationInNewDeviceMail(): bool
    {
        return (bool) $this->config->get('authentication.security.new_device_notification.include_location', true);
    }

    // Social Auth
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
     * @return array<string, mixed>
     */
    public function getSocialProviderConfig(string $provider): array
    {
        return (array) $this->config->get("authentication.features.social.providers.{$provider}", []);
    }

    public function getRedirect(string $key, string $default = '/dashboard'): string
    {
        return (string) $this->config->get("authentication.redirects.{$key}", $default);
    }

    /**
     * Resolve a configured package view, falling back to the canonical path when
     * the configured one no longer exists.
     *
     * A host that published `config/authentication.php` before the view tree was
     * grouped still names the removed flat aliases. Resolving that name directly
     * would raise `View [...] not found` on every page, so the canonical path
     * wins and only a genuinely missing view fails loudly.
     */
    public function getView(string $key, string $default): string
    {
        $configured = (string) $this->config->get("authentication.views.{$key}", $default);

        if ($configured !== '' && view()->exists($configured)) {
            return $configured;
        }

        if (view()->exists($default)) {
            return $default;
        }

        throw new AuthenticationConfigurationException(
            "Authentication view [{$default}] is unavailable. Run php artisan vendor:publish --tag=authentication-views --force or fix authentication.views.{$key}."
        );
    }
}
