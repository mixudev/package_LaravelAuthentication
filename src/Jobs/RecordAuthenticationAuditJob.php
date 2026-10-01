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
     * SECURITY FIX: Retry configuration to prevent audit data loss.
     * - 3 attempts with exponential backoff (5s, 15s, 30s)
     * - Survives transient DB deadlocks, connection failures
     * - Compliance requirement: audit trail must be durable
     */
    public int $tries = 3;
    public int $maxExceptions = 3;
    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    /**
     * @param array<string, mixed> $attemptData Payload for authentication_attempts table
     * @param array{user_id: int|string, payload: array<string, mixed>}|null $historyData Payload for login_histories table
     * @param string|null $idempotencyKey Unique key to prevent duplicate writes on retry
     */
    public function __construct(
        public readonly array $attemptData,
        public readonly ?array $historyData = null,
        public readonly ?string $idempotencyKey = null
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
        // SECURITY FIX: Idempotency check to prevent duplicate audit records on retry
        if ($this->idempotencyKey !== null) {
            $cacheKey = "audit_job_processed:{$this->idempotencyKey}";
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                return; // Already processed successfully
            }
        }

        try {
            if (!empty($this->attemptData)) {
                $attemptRepo->record($this->attemptData);
            }

            if ($this->historyData !== null && !empty($this->historyData['user_id'])) {
                $historyRepo->recordLogin(
                    $this->historyData['user_id'],
                    $this->historyData['payload'] ?? []
                );
            }

            // Mark as processed (TTL: 1 hour, covers retry window)
            if ($this->idempotencyKey !== null) {
                \Illuminate\Support\Facades\Cache::put("audit_job_processed:{$this->idempotencyKey}", true, 3600);
            }
        } catch (\Throwable $e) {
            // Let queue retry handle it, but log for monitoring
            \Illuminate\Support\Facades\Log::error('[AUTH_AUDIT] Job attempt failed', [
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'idempotency_key' => $this->idempotencyKey,
            ]);
            throw $e;
        }
    }

    /**
     * SECURITY FIX: Emergency fallback when all retries exhausted.
     * Write to local file for manual recovery (compliance requirement).
     */
    public function failed(\Throwable $exception): void
    {
        $emergencyLog = storage_path('logs/audit_failures.log');
        
        $entry = json_encode([
            'timestamp' => now()->toIso8601String(),
            'idempotency_key' => $this->idempotencyKey,
            'attempt_data' => $this->attemptData,
            'history_data' => $this->historyData,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n---\n";

        file_put_contents($emergencyLog, $entry, FILE_APPEND | LOCK_EX);

        \Illuminate\Support\Facades\Log::critical('[AUTH_AUDIT] PERMANENT FAILURE - written to emergency log', [
            'file' => $emergencyLog,
            'idempotency_key' => $this->idempotencyKey,
            'error' => $exception->getMessage(),
        ]);
    }
}
