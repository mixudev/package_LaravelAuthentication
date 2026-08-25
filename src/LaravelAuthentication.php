<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication;

use Illuminate\Support\Facades\Facade;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;

/**
 * Static Facade for interacting with the authentication package service.
 *
 * @method static \Vendor\LaravelAuthentication\DTO\AuthenticationResult authenticate(\Vendor\LaravelAuthentication\DTO\LoginData $data, \Vendor\LaravelAuthentication\DTO\AuthenticationContext $context)
 * @method static void logout(\Vendor\LaravelAuthentication\DTO\AuthenticationContext $context)
 * @method static bool isEnabled()
 *
 * @see \Vendor\LaravelAuthentication\Services\AuthenticationService
 */
class LaravelAuthentication extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return AuthenticationServiceInterface::class;
    }
}
