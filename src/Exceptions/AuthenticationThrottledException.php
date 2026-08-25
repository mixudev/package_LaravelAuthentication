<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Exceptions;

/**
 * Thrown when rate limit maximum attempts have been exceeded.
 */
class AuthenticationThrottledException extends AuthenticationException
{
    protected string $errorCode = 'AUTH_THROTTLED';

    public function __construct(
        public readonly int $secondsRemaining,
        string $message = 'Too many login attempts. Please try again later.',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
