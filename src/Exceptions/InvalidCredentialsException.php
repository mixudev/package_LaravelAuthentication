<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Exceptions;

/**
 * Thrown when credentials fail verification.
 */
class InvalidCredentialsException extends AuthenticationException
{
    protected string $errorCode = 'INVALID_CREDENTIALS';

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : (string) trans('authentication::messages.invalid_credentials'), $code, $previous);
    }
}
