<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Purpose:
 * Contract for resolving user entities from various database columns or external identity systems.
 */
interface CredentialResolverInterface
{
    /**
     * Resolve a user by a specified database column and raw identifier.
     */
    public function resolveByColumn(string $column, string $identifier): ?Authenticatable;

    /**
     * Resolve a user by an identifier across multiple fallback columns.
     *
     * @param array<int, string> $columns
     */
    public function resolveByColumns(array $columns, string $identifier): ?Authenticatable;
}
