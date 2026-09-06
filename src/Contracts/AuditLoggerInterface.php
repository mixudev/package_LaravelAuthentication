<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Vendor\LaravelAuthentication\DTO\AuthenticationResult;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Purpose:
 * Contract for recording security audit logs without leaking credentials.
 */
interface AuditLoggerInterface
{
    /**
     * Record an authentication or security event.
     *
     * @param array<string, mixed> $metadata
     */
    public function logEvent(
        SecurityEventType $eventType,
        ?string $identifier,
        AuthenticationContext $context,
        ?AuthenticationResult $result = null,
        array $metadata = []
    ): void;

    /**
     * Fetch the most recent login records for a user (for session history UI).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLogins(Authenticatable $user, int $limit = 10): array;
}
