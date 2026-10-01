<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\Repositories\AuthenticationAttemptRepository;
use Vendor\LaravelAuthentication\Repositories\LoginHistoryRepository;

/**
 * Asynchronously persist security audit records to avoid blocking the HTTP thread.
 *
 * ENTERPRISE: High-traffic applications (>1000 req/sec) cannot afford 2-3 synchronous
 * database writes on every authentication request. Queuing audit persistence offloads
 * the I/O to background workers while ensuring zero audit data loss.
 */
class RecordAuthenticationAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $attemptData Payload for authentication_attempts table
     * @param array{user_id: int|string, payload: array<string, mixed>}|null $historyData Payload for login_histories table
     */
    public function __construct(
        public readonly array $attemptData,
        public readonly ?array $historyData = null
    ) {
        $queueName = (string) config('authentication.audit.queue_name', 'auth-audit');
        $this->onQueue($queueName);

        $connection = config('authentication.audit.queue_connection');
        if ($connection) {
            $this->onConnection((string) $connection);
        }
    }

    public function handle(
        AuthenticationAttemptRepository $attemptRepo,
        LoginHistoryRepository $historyRepo
    ): void {
        if (!empty($this->attemptData)) {
            $attemptRepo->record($this->attemptData);
        }

        if ($this->historyData !== null && !empty($this->historyData['user_id'])) {
            $historyRepo->recordLogin(
                $this->historyData['user_id'],
                $this->historyData['payload'] ?? []
            );
        }
    }
}
