<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Purpose:
 * Contract for rate-limiting and tracking failed login attempts.
 */
interface LoginAttemptManagerInterface
{
    /**
     * Check if the attempt is throttled.
     */
    public function isThrottled(LoginData $data, AuthenticationContext $context): bool;

    /**
     * Increment the failed attempt counter.
     */
    public function recordFailedAttempt(LoginData $data, AuthenticationContext $context): void;

    /**
     * Clear failed attempts upon successful login.
     */
    public function clearAttempts(LoginData $data, AuthenticationContext $context): void;

    /**
     * Get remaining seconds until the throttle lock expires.
     */
    public function availableIn(LoginData $data, AuthenticationContext $context): int;
}
