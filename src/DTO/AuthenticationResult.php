<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\DTO;

use Illuminate\Contracts\Auth\Authenticatable;
use Vendor\LaravelAuthentication\Enums\AuthenticationStatus;

/**
 * Purpose:
 * Immutable outcome of an authentication attempt.
 */
final class AuthenticationResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly AuthenticationStatus $status,
        public readonly ?Authenticatable $user = null,
        public readonly ?string $token = null,
        public readonly ?string $message = null,
        public readonly array $metadata = []
    ) {}

    public static function success(Authenticatable $user, ?string $token = null, array $metadata = []): self
    {
        return new self(
            status: AuthenticationStatus::SUCCESS,
            user: $user,
            token: $token,
            message: 'Authentication successful.',
            metadata: $metadata
        );
    }

    public static function failed(
        AuthenticationStatus $status = AuthenticationStatus::INVALID_CREDENTIALS,
        string $message = 'These credentials do not match our records.',
        array $metadata = []
    ): self {
        return new self(
            status: $status,
            user: null,
            token: null,
            message: $message,
            metadata: $metadata
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status === AuthenticationStatus::SUCCESS;
    }

    public function isSuccess(): bool
    {
        return $this->isSuccessful();
    }
}
