<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched when a login attempt is initiated.
 * Note: Never contains raw passwords or unhashed secrets.
 */
class LoginAttempted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $identifier,
        public readonly AuthenticationContext $context,
        public readonly ?string $strategy = null
    ) {}
}
