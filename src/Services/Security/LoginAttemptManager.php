<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security;

use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\Contracts\LoginAttemptManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;

/**
 * Manages rate-limiting counters across IP, identifier, or composite keys for login attempts.
 */
class LoginAttemptManager implements LoginAttemptManagerInterface
{
    public function __construct(
        private readonly FeatureRateLimiterInterface $limiter
    ) {}

    public function isThrottled(LoginData $data, AuthenticationContext $context): bool
    {
        return $this->limiter->tooManyAttempts('login', $data->identifier, $context->ipAddress);
    }

    public function recordFailedAttempt(LoginData $data, AuthenticationContext $context): void
    {
        $this->limiter->hit('login', $data->identifier, $context->ipAddress);
    }

    public function clearAttempts(LoginData $data, AuthenticationContext $context): void
    {
        $this->limiter->clear('login', $data->identifier, $context->ipAddress);
    }

    public function availableIn(LoginData $data, AuthenticationContext $context): int
    {
        return $this->limiter->availableIn('login', $data->identifier, $context->ipAddress);
    }
}
