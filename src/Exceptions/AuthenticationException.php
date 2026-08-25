<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Exceptions;

use Exception;

/**
 * Base exception for all package-level authentication faults.
 */
class AuthenticationException extends Exception
{
    protected string $errorCode = 'AUTH_ERROR';

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
