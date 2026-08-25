<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Strategies;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Support\Normalizers\UsernameNormalizer;

/**
 * Strategy authenticating strictly using a username and password.
 */
class UsernamePasswordStrategy extends AbstractAuthenticationStrategy
{
    public function name(): string
    {
        return 'username_password';
    }

    public function supports(LoginData $data): bool
    {
        return $data->strategy === 'username_password' || $data->strategy === null;
    }

    public function resolveUser(LoginData $data, AuthenticationContext $context): ?Authenticatable
    {
        $username = $this->config->shouldNormalizeIdentifiers()
            ? UsernameNormalizer::normalize($data->identifier)
            : trim($data->identifier);

        $column = $this->config->getIdentifierColumn('username');

        return $this->resolver->resolveByColumn($column, $username);
    }
}
