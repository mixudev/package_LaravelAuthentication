<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

interface FeatureRateLimiterInterface
{
    /**
     * Determine if too many attempts have occurred for a given feature and subject.
     */
    public function tooManyAttempts(string $feature, ?string $identifier, string $ipAddress, string $clientId = 'default'): bool;

    /**
     * Hit the throttle counter for a given feature and subject.
     */
    public function hit(string $feature, ?string $identifier, string $ipAddress, string $clientId = 'default'): int;

    /**
     * Clear the throttle counter for a given feature and subject.
     */
    public function clear(string $feature, ?string $identifier, string $ipAddress, string $clientId = 'default'): void;

    /**
     * Get the number of seconds until the key becomes available again.
     */
    public function availableIn(string $feature, ?string $identifier, string $ipAddress, string $clientId = 'default'): int;

    /**
     * Get the number of remaining attempts allowed.
     */
    public function remaining(string $feature, ?string $identifier, string $ipAddress, string $clientId = 'default'): int;

    /**
     * Get the total failed attempts count in current window.
     */
    public function attempts(string $feature, ?string $identifier, string $ipAddress, string $clientId = 'default'): int;
}
