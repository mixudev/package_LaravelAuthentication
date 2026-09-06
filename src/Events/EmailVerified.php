<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched when a user verifies their email address.
 */
class EmailVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly AuthenticationContext $context
    ) {}
}
