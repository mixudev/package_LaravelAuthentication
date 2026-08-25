<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched when a password reset link is generated and dispatched.
 */
class PasswordResetRequested
{
    use SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly AuthenticationContext $context,
        public readonly ?Authenticatable $user = null
    ) {}
}
