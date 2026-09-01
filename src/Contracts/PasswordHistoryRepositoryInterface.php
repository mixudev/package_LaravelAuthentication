<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use SensitiveParameter;

/**
 * Purpose:
 * Contract for persisting and checking password history to prevent immediate reuse.
 */
interface PasswordHistoryRepositoryInterface
{
    /**
     * Record a newly assigned password hash for the user.
     */
    public function recordPassword(Authenticatable $user, string $passwordHash): void;

    /**
     * Check if a candidate plain password matches any of the user's last N recorded passwords.
     */
    public function isPreviouslyUsed(Authenticatable $user, #[SensitiveParameter] string $plainPassword, int $rememberCount = 5): bool;
}
