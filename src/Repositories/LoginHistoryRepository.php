<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Repositories;

use Illuminate\Support\Carbon;
use Vendor\LaravelAuthentication\Models\LoginHistory;

class LoginHistoryRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function recordLogin(int|string $userId, array $data): LoginHistory
    {
        return LoginHistory::create([
            'user_id'      => (int) $userId,
            'ip_address'   => $data['ip_address'] ?? null,
            'user_agent'   => $data['user_agent'] ?? null,
            'login_method' => $data['login_method'] ?? 'standard',
            'channel'      => $data['channel'] ?? 'web',
            'login_at'     => Carbon::now(),
        ]);
    }

    public function recordLogout(int|string $userId): void
    {
        LoginHistory::where('user_id', (int) $userId)
            ->whereNull('logout_at')
            ->latest('login_at')
            ->limit(1)
            ->update(['logout_at' => Carbon::now()]);
    }
}
