<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\DTO;

use SensitiveParameter;

/**
 * Immutable Data Transfer Object encapsulating registration input data.
 */
final class RegisterData
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        #[SensitiveParameter]
        public readonly string $password,
        public readonly array $extra = []
    ) {}

    /**
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            name: (string) ($attributes['name'] ?? ''),
            email: (string) ($attributes['email'] ?? ''),
            password: (string) ($attributes['password'] ?? ''),
            extra: $attributes
        );
    }
}
