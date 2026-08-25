<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Purpose:
 * Contract for enforcing pre- and post-authentication security policies.
 */
interface SecurityPolicyInterface
{
    /**
     * Evaluate pre-authentication policies (rate limit, lockout status).
     */
    public function checkPreAuthentication(LoginData $data, AuthenticationContext $context): void;

    /**
     * Evaluate post-authentication policies (account active state, email verification requirements).
     */
    public function checkPostAuthentication(Authenticatable $user, AuthenticationContext $context): void;
}
