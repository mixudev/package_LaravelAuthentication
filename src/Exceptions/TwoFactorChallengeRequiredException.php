<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Exceptions;

use Illuminate\Contracts\Auth\Authenticatable;

class TwoFactorChallengeRequiredException extends AuthenticationException
{
    public function __construct(
        public readonly Authenticatable $user,
        string $message = 'Two-factor authentication challenge required.'
    ) {
        parent::__construct($message);
    }
}
