<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Exceptions;

/**
 * Thrown when an account has been temporarily or permanently locked due to security policy.
 */
class AccountLockedException extends AuthenticationException
{
    protected string $errorCode = 'ACCOUNT_LOCKED';

    public function __construct(
        public readonly int $lockoutMinutes = 15,
        string $message = 'Your account has been temporarily locked for security reasons. Please try again later.',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
