<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;

/**
 * Asynchronously dispatch password reset link via queue to ensure stable HTTP latency
 * and offload SMTP connection overhead to background queue workers.
 */
class SendPasswordResetLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly string $email
    ) {
        $connection = config('authentication.mail.queue_connection');
        if ($connection) {
            $this->onConnection((string) $connection);
        }

        $queueName = config('authentication.mail.queue_name');
        if ($queueName) {
            $this->onQueue((string) $queueName);
        }
    }

    public function handle(): void
    {
        Password::broker()->sendResetLink(['email' => $this->email]);
    }
}
