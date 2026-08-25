<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched when a session or device authorization is revoked.
 */
class SessionRevoked
{
    use SerializesModels;

    public function __construct(
        public readonly ?Authenticatable $user,
        public readonly AuthenticationContext $context,
        public readonly string $sessionId
    ) {}
}
