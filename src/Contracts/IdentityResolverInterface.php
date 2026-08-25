<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Vendor\LaravelAuthentication\DTO\UserIdentity;

/**
 * Purpose:
 * Contract for parsing and identifying credential formats (email vs username vs phone).
 */
interface IdentityResolverInterface
{
    /**
     * Inspect raw identifier string and return normalized UserIdentity structure.
     */
    public function resolve(string $rawIdentifier): UserIdentity;
}
