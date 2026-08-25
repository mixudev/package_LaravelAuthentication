<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Strategies;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\Contracts\AuthenticationStrategyInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialResolverInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialValidatorInterface;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Base abstract strategy encapsulating common credential validation logic.
 */
abstract class AbstractAuthenticationStrategy implements AuthenticationStrategyInterface
{
    public function __construct(
        protected readonly CredentialResolverInterface $resolver,
        protected readonly CredentialValidatorInterface $validator,
        protected readonly AuthenticationConfig $config
    ) {}

    public function validateCredentials(Authenticatable $user, LoginData $data): bool
    {
        return $this->validator->validatePassword($user, $data->password);
    }
}
