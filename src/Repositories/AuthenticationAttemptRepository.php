<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Repositories;

use Illuminate\Support\Carbon;
use Vendor\LaravelAuthentication\Models\AuthenticationAttempt;

class AuthenticationAttemptRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function record(array $data): AuthenticationAttempt
    {
        return AuthenticationAttempt::create([
            'identifier'     => $data['identifier'] ?? 'unknown',
            'ip_address'     => $data['ip_address'] ?? '127.0.0.1',
            'user_agent'     => $data['user_agent'] ?? null,
            'status'         => $data['status'] ?? 'UNKNOWN',
            'failure_reason' => $data['failure_reason'] ?? null,
            'strategy'       => $data['strategy'] ?? null,
            'channel'        => $data['channel'] ?? 'web',
            'attempted_at'   => $data['attempted_at'] ?? Carbon::now(),
        ]);
    }

    /**
     * Count recent failed attempts for a given identifier within a time window.
     */
    public function countRecentFailures(string $identifier, int $minutes): int
    {
        return AuthenticationAttempt::where('identifier', $identifier)
            ->where('status', 'FAILED')
            ->where('attempted_at', '>=', Carbon::now()->subMinutes($minutes))
            ->count();
    }
}
