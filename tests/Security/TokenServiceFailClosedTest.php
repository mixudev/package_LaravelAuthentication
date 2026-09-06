<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException;
use Vendor\LaravelAuthentication\Services\Core\TokenService;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Red-team test: TokenService fail-closed behavior.
 *
 * Attack / misconfiguration surface:
 * - Token dummy (Str::random(64)) yang tidak di-persist: client menerima
 *   "token sukses" yang langsung invalid, tidak bisa dipakai, dan tidak bisa
 *   di-revoke → broken contract + false sense of security.
 * - Fallback ini JUGA tidak bisa di-audit: tidak ada record token di DB.
 * - Fail-closed (AGENTS.md invariant): tanpa Sanctum, package HARUS throw
 *   konfigurasi error, bukan mengembalikan token palsu.
 */
class TokenServiceFailClosedTest extends TestCase
{
    public function test_create_token_throws_when_user_model_has_no_sanctum(): void
    {
        $service = new TokenService();

        $user = new class implements Authenticatable {
            public function getAuthIdentifierName(): string { return 'id'; }
            public function getAuthIdentifier(): mixed { return 1; }
            public function getAuthPassword(): string { return ''; }
            public function getAuthPasswordName(): string { return 'password'; }
            public function getRememberToken(): ?string { return null; }
            public function setRememberToken($value): void {}
            public function getRememberTokenName(): string { return 'remember_token'; }
        };

        $this->expectException(AuthenticationConfigurationException::class);
        $this->expectExceptionMessageMatches('/laravel\/sanctum/');

        $service->createToken($user);
    }

    public function test_revoke_current_token_noops_without_sanctum_but_does_not_crash(): void
    {
        $service = new TokenService();

        $user = new class implements Authenticatable {
            public function getAuthIdentifierName(): string { return 'id'; }
            public function getAuthIdentifier(): mixed { return 1; }
            public function getAuthPassword(): string { return ''; }
            public function getAuthPasswordName(): string { return 'password'; }
            public function getRememberToken(): ?string { return null; }
            public function setRememberToken($value): void {}
            public function getRememberTokenName(): string { return 'remember_token'; }
        };

        // Tidak boleh throw — revoke best-effort.
        $service->revokeAllTokens($user);
        $service->revokeCurrentToken($user);

        $this->assertTrue(true);
    }
}