<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Repositories;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Carbon;
use Vendor\LaravelAuthentication\Contracts\PasswordHistoryRepositoryInterface;
use Vendor\LaravelAuthentication\Models\PasswordHistory;

class PasswordHistoryRepository implements PasswordHistoryRepositoryInterface
{
    public function __construct(
        private readonly Hasher $hasher
    ) {}

    public function recordPassword(Authenticatable $user, string $passwordHash): void
    {
        PasswordHistory::create([
            'user_id'       => (string) $user->getAuthIdentifier(),
            'password_hash' => $passwordHash,
            'created_at'    => Carbon::now(),
        ]);
    }

    public function isPreviouslyUsed(Authenticatable $user, #[\SensitiveParameter] string $plainPassword, int $rememberCount = 5): bool
    {
        $histories = PasswordHistory::where('user_id', (string) $user->getAuthIdentifier())
            ->latest('created_at')
            ->limit($rememberCount)
            ->get();

        foreach ($histories as $history) {
            if ($this->hasher->check($plainPassword, $history->password_hash)) {
                return true;
            }
        }

        return false;
    }
}
