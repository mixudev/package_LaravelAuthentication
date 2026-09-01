<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SensitiveParameter;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched when an OTP code is generated for a user or identifier.
 */
class OtpGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ?Authenticatable $user,
        public readonly string $identifier,
        #[SensitiveParameter]
        public readonly string $code,
        public readonly AuthenticationContext $context,
        public readonly int $expiryMinutes
    ) {}
}
