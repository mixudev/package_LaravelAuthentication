<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched when an account has been locked due to consecutive authentication failures.
 */
class AccountLocked
{
    use SerializesModels;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly AuthenticationContext $context,
        public readonly int $lockoutDurationMinutes
    ) {}
}
