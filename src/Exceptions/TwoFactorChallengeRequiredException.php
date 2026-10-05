<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Exceptions;

use Illuminate\Contracts\Auth\Authenticatable;

class TwoFactorChallengeRequiredException extends AuthenticationException
{
    public function __construct(
        public readonly Authenticatable $user,
        string $message = ''
    ) {
        parent::__construct($message !== '' ? $message : (string) trans('authentication::messages.two_factor_challenge_required'));
    }
}
