<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Exceptions;

/**
 * Thrown when credentials fail verification.
 */
class InvalidCredentialsException extends AuthenticationException
{
    protected string $errorCode = 'INVALID_CREDENTIALS';

    public function __construct(string $message = 'These credentials do not match our records.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
