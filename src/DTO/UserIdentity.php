<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\DTO;

/**
 * Purpose:
 * Represents a parsed and normalized login identifier.
 */
final class UserIdentity
{
    public function __construct(
        public readonly string $raw,
        public readonly string $normalized,
        public readonly string $type // 'email', 'username', 'custom'
    ) {}

    public function isEmail(): bool
    {
        return $this->type === 'email';
    }

    public function isUsername(): bool
    {
        return $this->type === 'username';
    }

    public function isCustom(): bool
    {
        return $this->type === 'custom';
    }
}
