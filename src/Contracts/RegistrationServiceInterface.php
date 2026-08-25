<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\RegisterData;

interface RegistrationServiceInterface
{
    public function isEnabled(): bool;

    public function register(RegisterData $data, AuthenticationContext $context): Authenticatable;
}
