<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Purpose:
 * Contract for managing API bearer tokens (Sanctum/Passport adapters).
 */
interface TokenManagerInterface
{
    /**
     * Create an API access token for the given user.
     *
     * @param array<int, string> $abilities
     */
    public function createToken(Authenticatable $user, string $tokenName = 'auth_token', array $abilities = ['*']): string;

    /**
     * Revoke all active tokens for the given user.
     */
    public function revokeAllTokens(Authenticatable $user): void;

    /**
     * Revoke the currently used token.
     */
    public function revokeCurrentToken(Authenticatable $user): void;
}
