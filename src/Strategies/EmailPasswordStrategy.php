<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Strategies;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Support\Normalizers\EmailNormalizer;

/**
 * Strategy authenticating strictly using an email address and password.
 */
class EmailPasswordStrategy extends AbstractAuthenticationStrategy
{
    public function name(): string
    {
        return 'email_password';
    }

    public function supports(LoginData $data): bool
    {
        return $data->strategy === 'email_password' || ($data->strategy === null && filter_var(trim($data->identifier), FILTER_VALIDATE_EMAIL) !== false);
    }

    public function resolveUser(LoginData $data, AuthenticationContext $context): ?Authenticatable
    {
        $email = $this->config->shouldNormalizeIdentifiers()
            ? EmailNormalizer::normalize($data->identifier)
            : trim($data->identifier);

        $column = $this->config->getIdentifierColumn('email');

        return $this->resolver->resolveByColumn($column, $email);
    }
}
