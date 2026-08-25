<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;

/**
 * Manages API bearer tokens with Laravel Sanctum or fallback token generation.
 */
class TokenService implements TokenManagerInterface
{
    public function createToken(Authenticatable $user, string $tokenName = 'auth_token', array $abilities = ['*']): string
    {
        // Support Laravel Sanctum if available on user model
        if (method_exists($user, 'createToken')) {
            /** @var object $tokenResult */
            $tokenResult = $user->createToken($tokenName, $abilities);
            return $tokenResult->plainTextToken ?? (string) $tokenResult;
        }

        // Fallback cryptographically secure random token
        return Str::random(64);
    }

    public function revokeAllTokens(Authenticatable $user): void
    {
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    }

    public function revokeCurrentToken(Authenticatable $user): void
    {
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken() !== null) {
            $user->currentAccessToken()->delete();
        }
    }
}
