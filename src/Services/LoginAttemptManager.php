<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Cache\RateLimiter;
use Vendor\LaravelAuthentication\Contracts\LoginAttemptManagerInterface;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\Normalizers\EmailNormalizer;

/**
 * Manages rate-limiting counters across IP, identifier, or composite keys.
 */
class LoginAttemptManager implements LoginAttemptManagerInterface
{
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly AuthenticationConfig $config
    ) {}

    public function isThrottled(LoginData $data, AuthenticationContext $context): bool
    {
        if (!$this->config->isRateLimitEnabled()) {
            return false;
        }

        $key = $this->resolveThrottleKey($data, $context);
        $maxAttempts = $this->config->getRateLimitMaxAttempts();

        return $this->rateLimiter->tooManyAttempts($key, $maxAttempts);
    }

    public function recordFailedAttempt(LoginData $data, AuthenticationContext $context): void
    {
        if (!$this->config->isRateLimitEnabled()) {
            return;
        }

        $key = $this->resolveThrottleKey($data, $context);
        $decaySeconds = $this->config->getRateLimitDecayMinutes() * 60;

        $this->rateLimiter->hit($key, $decaySeconds);
    }

    public function clearAttempts(LoginData $data, AuthenticationContext $context): void
    {
        if (!$this->config->isRateLimitEnabled()) {
            return;
        }

        $key = $this->resolveThrottleKey($data, $context);
        $this->rateLimiter->clear($key);
    }

    public function availableIn(LoginData $data, AuthenticationContext $context): int
    {
        $key = $this->resolveThrottleKey($data, $context);
        return $this->rateLimiter->availableIn($key);
    }

    protected function resolveThrottleKey(LoginData $data, AuthenticationContext $context): string
    {
        $normalizedIdentifier = EmailNormalizer::normalize($data->identifier);
        $strategy = $this->config->getRateLimitStrategy();

        return match ($strategy) {
            'ip'         => 'auth_throttle|ip|' . $context->ipAddress,
            'identifier' => 'auth_throttle|id|' . sha1($normalizedIdentifier),
            default      => 'auth_throttle|comp|' . sha1($normalizedIdentifier . '|' . $context->ipAddress),
        };
    }
}
