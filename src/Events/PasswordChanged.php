<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched when a user modifies their password.
 */
class PasswordChanged
{
    use SerializesModels;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly AuthenticationContext $context
    ) {}
}
