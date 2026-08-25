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

        return $instance->newQuery()
            ->where($column, $identifier)
            ->first();
    }

    public function resolveByColumns(array $columns, string $identifier): ?Authenticatable
    {
        $userModel = $this->config->getUserModel();

        /** @var Model $instance */
        $instance = new $userModel();

        $query = $instance->newQuery();

        $query->where(function ($q) use ($columns, $identifier) {
            foreach ($columns as $column) {
                $q->orWhere($column, $identifier);
            }
        });

        return $query->first();
    }
}
