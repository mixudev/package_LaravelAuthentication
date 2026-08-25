<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Exceptions;

/**
 * Thrown when an unknown or unregistered authentication strategy is requested.
 */
class InvalidStrategyException extends AuthenticationException
{
    protected string $errorCode = 'INVALID_STRATEGY';
}
