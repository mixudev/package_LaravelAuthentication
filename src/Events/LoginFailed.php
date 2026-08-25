<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched on failed login.
 * Carries redacted metadata and never exposes plaintext passwords.
 */
class LoginFailed
{
    use SerializesModels;

    public function __construct(
        public readonly string $identifier,
        public readonly AuthenticationContext $context,
        public readonly string $reason,
        public readonly ?Authenticatable $user = null
    ) {}
}
