<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security;

use Illuminate\Contracts\Auth\Authenticatable;
use Psr\Log\LoggerInterface;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\AuthenticationResult;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Repositories\AuthenticationAttemptRepository;
use Vendor\LaravelAuthentication\Repositories\LoginHistoryRepository;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\SecurityHelper;

/**
 * Handles security audit logging to database or log channels with strict redaction of sensitive credentials.
 */
class AuthenticationAuditService implements AuditLoggerInterface
{
    public function __construct(
        private readonly AuthenticationAttemptRepository $attemptRepo,
        private readonly LoginHistoryRepository $historyRepo,
        private readonly LoggerInterface $logger,
        private readonly AuthenticationConfig $config
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public function logEvent(
        SecurityEventType $eventType,
        ?string $identifier,
        AuthenticationContext $context,
        ?AuthenticationResult $result = null,
        array $metadata = []
    ): void {
        if (!$this->config->isAuditEnabled()) {
            return;
        }

        $safeIdentifier = $identifier ? SecurityHelper::maskIdentifier($identifier) : 'unknown';
        $safeMetadata = SecurityHelper::redactSensitive($metadata);

        $driver = $this->config->getAuditDriver();

        if ($driver === 'database' || $driver === 'all') {
            $this->attemptRepo->record([
                'identifier'     => $safeIdentifier,
                'ip_address'     => $context->ipAddress,
                'user_agent'     => $context->userAgent,
                'status'         => $eventType->value,
                'failure_reason' => $result?->message,
                'strategy'       => $result->metadata['strategy'] ?? null,
                'channel'        => $context->channel->value,
            ]);

            if ($eventType === SecurityEventType::LOGIN_SUCCESS && $result?->user instanceof Authenticatable) {
                $this->historyRepo->recordLogin($result->user->getAuthIdentifier(), [
                    'ip_address'   => $context->ipAddress,
                    'user_agent'   => $context->userAgent,
                    'login_method' => $result->metadata['strategy'] ?? 'standard',
                    'channel'      => $context->channel->value,
                ]);
            }
        }

        if ($driver === 'log' || $driver === 'all') {
            $this->logger->info("[AUTH_AUDIT] Event: {$eventType->value}", [
                'identifier' => $safeIdentifier,
                'ip'         => $context->ipAddress,
                'channel'    => $context->channel->value,
                'metadata'   => $safeMetadata,
            ]);
        }
    }

    /**
     * Retrieve recent login history records for a user.
     *
     * @return array<int, array{id: int|string, ip_address: ?string, user_agent: ?string, login_method: string, channel: string, login_at: \Illuminate\Support\Carbon|string, logout_at: \Illuminate\Support\Carbon|string|null}>
     */
    public function getRecentLogins(Authenticatable $user, int $limit = 10): array
    {
        $userId = $user->getAuthIdentifier();
        $records = $this->historyRepo->getRecentForUser($userId, $limit);
        $results = [];

        foreach ($records as $record) {
            $results[] = [
                'id'           => $record->id,
                'ip_address'   => $record->ip_address,
                'user_agent'   => $record->user_agent,
                'login_method' => $record->login_method,
                'channel'      => $record->channel,
                'login_at'     => $record->login_at,
                'logout_at'    => $record->logout_at,
            ];
        }

        return $results;
    }
}
