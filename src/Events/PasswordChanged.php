<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched when a user modifies their password.
 * Context is optional — password changes can originate from service layer without HTTP context.
 */
class PasswordChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly ?AuthenticationContext $context = null
    ) {}
}
