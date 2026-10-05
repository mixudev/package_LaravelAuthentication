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
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : (string) trans('authentication::messages.auth_throttled_later'), $code, $previous);
    }
}
