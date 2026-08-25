<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Strategies;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Strategy authenticating using arbitrary custom identifier columns (e.g. employee_id, badge_number, phone).
 */
class CustomIdentifierStrategy extends AbstractAuthenticationStrategy
{
    public function name(): string
    {
        return 'custom_identifier';
    }

    public function supports(LoginData $data): bool
    {
        return $data->strategy === 'custom_identifier';
    }

    public function resolveUser(LoginData $data, AuthenticationContext $context): ?Authenticatable
    {
        $identifier = trim($data->identifier);
        $column = $this->config->getIdentifierColumn('custom');

        return $this->resolver->resolveByColumn($column, $identifier);
    }
}
