<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Core;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException;

/**
 * Manages API bearer tokens with Laravel Sanctum or fallback token generation.
 */
class TokenService implements TokenManagerInterface
{
    public function __construct(
        private readonly AuditLoggerInterface $auditService
    ) {}

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

        $this->auditRevocation($user, 'all');
    }

    public function revokeCurrentToken(Authenticatable $user): void
    {
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken() !== null) {
            $user->currentAccessToken()->delete();
        }

        $this->auditRevocation($user, 'current');
    }

    /**
     * Write TOKEN_REVOKED to the audit trail. Null-safe for CLI/queue contexts.
     */
    protected function auditRevocation(Authenticatable $user, string $scope): void
    {
        $userIdentifier = (string) $user->getAuthIdentifier();

        $request = request();
        if ($request instanceof \Illuminate\Http\Request) {
            $context = AuthenticationContext::fromRequest($request);
        } else {
            $context = new AuthenticationContext('cli', 'cli', AuthenticationChannel::CLI, 'web');
        }

        $this->auditService->logEvent(
            SecurityEventType::TOKEN_REVOKED,
            $userIdentifier,
            $context,
            null,
            ['scope' => $scope]
        );
    }
}
