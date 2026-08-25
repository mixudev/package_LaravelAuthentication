<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Exceptions;

/**
 * Thrown when the package configuration is corrupted, missing, or violates fail-closed rules.
 */
class AuthenticationConfigurationException extends AuthenticationException
{
    protected string $errorCode = 'CONFIG_ERROR';
}
