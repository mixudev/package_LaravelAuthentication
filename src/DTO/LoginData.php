<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\DTO;

use SensitiveParameter;

/**
 * Purpose:
 * Immutable Data Transfer Object encapsulating raw login credentials.
 *
 * Security considerations:
 * Password property is tagged with #[SensitiveParameter] to prevent exposure in stack traces.
 */
final class LoginData
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $identifier,
        #[SensitiveParameter]
        public readonly string $password,
        public readonly bool $remember = false,
        public readonly ?string $strategy = null,
        public readonly array $extra = []
    ) {}

    /**
     * Named factory from validated array.
     *
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            identifier: (string) ($attributes['identifier'] ?? $attributes['email'] ?? $attributes['username'] ?? ''),
            password: (string) ($attributes['password'] ?? ''),
            remember: (bool) ($attributes['remember'] ?? false),
            strategy: isset($attributes['strategy']) ? (string) $attributes['strategy'] : null,
            extra: $attributes
        );
    }
}
