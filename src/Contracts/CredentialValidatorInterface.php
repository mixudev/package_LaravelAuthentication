<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use SensitiveParameter;

/**
 * Purpose:
 * Contract for verifying passwords securely and triggering rehashing if needed.
 */
interface CredentialValidatorInterface
{
    /**
     * Verify whether the plain password matches the user's stored password hash.
     */
    public function validatePassword(Authenticatable $user, #[SensitiveParameter] string $plainPassword): bool;

    /**
     * Check if the user password hash requires rehashing to a newer work factor or algorithm.
     */
    public function needsRehash(Authenticatable $user): bool;

    /**
     * Rehash the user's password and persist to the storage model.
     */
    public function rehashPassword(Authenticatable $user, #[SensitiveParameter] string $plainPassword): void;
}
