<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Contracts\CredentialResolverInterface;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Resolves authenticatable Eloquent user models via configured columns.
 */
class CredentialResolver implements CredentialResolverInterface
{
    public function __construct(
        private readonly AuthenticationConfig $config
    ) {}

    public function resolveByColumn(string $column, string $identifier): ?Authenticatable
    {
        $userModel = $this->config->getUserModel();

        /** @var Model $instance */
        $instance = new $userModel();

        /** @var Authenticatable|null $result */
        $result = $instance->newQuery()
            ->where($column, $identifier)
            ->first();

        return $result;
    }

    public function resolveByColumns(array $columns, string $identifier): ?Authenticatable
    {
        $userModel = $this->config->getUserModel();

        /** @var Model $instance */
        $instance = new $userModel();

        // FAST-PATH: If identifier is a valid email format and 'email' is one of the target columns,
        // use a direct indexed single-column lookup first to avoid expensive OR / Index Merge on massive tables.
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $emailColumn = $this->config->getIdentifierColumn('email');
        $usernameColumn = $this->config->getIdentifierColumn('username');

        if ($isEmail && in_array($emailColumn, $columns, true)) {
            /** @var Authenticatable|null $emailResult */
            $emailResult = $instance->newQuery()->where($emailColumn, $identifier)->first();
            if ($emailResult !== null) {
                return $emailResult;
            }
        } elseif (!$isEmail && in_array($usernameColumn, $columns, true)) {
            /** @var Authenticatable|null $usernameResult */
            $usernameResult = $instance->newQuery()->where($usernameColumn, $identifier)->first();
            if ($usernameResult !== null) {
                return $usernameResult;
            }
        }

        // Fallback: Multi-column search for custom or edge-case identifiers
        $query = $instance->newQuery();

        $query->where(function ($q) use ($columns, $identifier) {
            foreach ($columns as $column) {
                $q->orWhere($column, $identifier);
            }
        });

        /** @var Authenticatable|null $result */
        $result = $query->first();

        return $result;
    }
}
