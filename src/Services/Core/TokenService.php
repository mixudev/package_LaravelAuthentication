<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Core;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException;

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

        // Fail-closed: tanpa Sanctum, package TIDAK boleh mengembalikan token dummy
        // yang tidak pernah di-persist — client akan menerima "token sukses" yang
        // langsung invalid (broken contract) dan tidak bisa di-revoke.
        // Host app HARUS memasang laravel/sanctum untuk fitur API token.
        throw new AuthenticationConfigurationException(
            'API token generation requires laravel/sanctum. Install it and add the HasApiTokens trait to your user model, or disable API authentication.'
        );
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
