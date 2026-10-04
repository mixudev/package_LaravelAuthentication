<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Support\SecurityHelper;

/**
 * Dispatched when a password reset link is generated and dispatched.
 *
 * The email is masked on construction: any host listener, log sink, or queued
 * subscriber can never persist or broadcast the raw submitted address.
 */
class PasswordResetRequested
{
    use Dispatchable, SerializesModels;

    public readonly string $email;

    public function __construct(
        string $email,
        public readonly AuthenticationContext $context,
        public readonly ?Authenticatable $user = null
    ) {
        $this->email = SecurityHelper::maskIdentifier($email);
    }
}
