<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Strategies;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Support\Normalizers\IdentifierNormalizer;

/**
 * Strategy dynamically resolving credentials that may be either a username or an email address.
 */
class UsernameOrEmailStrategy extends AbstractAuthenticationStrategy
{
    public function name(): string
    {
        return 'username_or_email';
    }

    public function supports(LoginData $data): bool
    {
        return $data->strategy === 'username_or_email' || $data->strategy === null;
    }

    public function resolveUser(LoginData $data, AuthenticationContext $context): ?Authenticatable
    {
        $identity = IdentifierNormalizer::resolve($data->identifier);

        if ($identity->isEmail()) {
            $emailCol = $this->config->getIdentifierColumn('email');
            $user = $this->resolver->resolveByColumn($emailCol, $identity->normalized);
            if ($user !== null) {
                return $user;
            }
        }

        $usernameCol = $this->config->getIdentifierColumn('username');
        return $this->resolver->resolveByColumn($usernameCol, $identity->normalized);
    }
}
