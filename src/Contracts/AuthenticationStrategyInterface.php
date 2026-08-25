<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Purpose:
 * Contract for extensible login authentication strategies.
 *
 * Responsibility:
 * Resolves user identity from credentials and determines strategy compatibility.
 */
interface AuthenticationStrategyInterface
{
    /**
     * Unique identifier key of the strategy (e.g. 'username_or_email', 'employee_id').
     */
    public function name(): string;

    /**
     * Determine if this strategy can handle the given login data.
     */
    public function supports(LoginData $data): bool;

    /**
     * Resolve the authenticatable user instance using provided credentials.
     */
    public function resolveUser(LoginData $data, AuthenticationContext $context): ?Authenticatable;

    /**
     * Validate the credential against the resolved user.
     */
    public function validateCredentials(Authenticatable $user, LoginData $data): bool;
}
