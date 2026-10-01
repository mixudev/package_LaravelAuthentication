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
use Vendor\LaravelAuthentication\Jobs\RecordAuthenticationAuditJob;
use Throwable;

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
            $attemptPayload = [
                'identifier'     => $safeIdentifier,
                'ip_address'     => $context->ipAddress,
                'user_agent'     => $context->userAgent,
                'status'         => $eventType->value,
                'failure_reason' => $result?->message,
                'strategy'       => $result->metadata['strategy'] ?? null,
                'channel'        => $context->channel->value,
            ];

            $historyPayload = null;
            if ($eventType === SecurityEventType::LOGIN_SUCCESS && $result?->user instanceof Authenticatable) {
                $historyPayload = [
                    'user_id' => $result->user->getAuthIdentifier(),
                    'payload' => [
                        'ip_address'   => $context->ipAddress,
                        'user_agent'   => $context->userAgent,
                        'login_method' => $result->metadata['strategy'] ?? 'standard',
                        'channel'      => $context->channel->value,
                    ],
                ];
            }

            // PERF-09: Async audit logging offloads write I/O to queue workers.
            // High-traffic apps (>1000 req/sec) eliminate 2 synchronous DB writes per login.
            if ($this->config->isAuditQueued()) {
                try {
                    RecordAuthenticationAuditJob::dispatch($attemptPayload, $historyPayload);
                } catch (Throwable $e) {
                    // Fallback: sync write or log-only
                    $fallback = (string) config('authentication.audit.queue_fallback', 'sync');
                    if ($fallback === 'sync') {
                        $this->persistAuditSync($attemptPayload, $historyPayload);
                    } else {
                        $this->logger->error('[AUTH_AUDIT] Queue dispatch failed', ['error' => $e->getMessage()]);
                    }
                }
            } else {
                $this->persistAuditSync($attemptPayload, $historyPayload);
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

    /**
     * Persist audit records synchronously (fallback or when queue disabled).
     *
     * @param array<string, mixed> $attemptPayload
     * @param array{user_id: int|string, payload: array<string, mixed>}|null $historyPayload
     */
    private function persistAuditSync(array $attemptPayload, ?array $historyPayload): void
    {
        $this->attemptRepo->record($attemptPayload);

        if ($historyPayload !== null) {
            $this->historyRepo->recordLogin(
                $historyPayload['user_id'],
                $historyPayload['payload']
            );
        }
    }
}
