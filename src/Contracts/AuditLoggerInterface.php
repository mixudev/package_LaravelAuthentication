<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Vendor\LaravelAuthentication\DTO\AuthenticationResult;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;

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
}
