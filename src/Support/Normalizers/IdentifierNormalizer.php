<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support\Normalizers;

use Vendor\LaravelAuthentication\DTO\UserIdentity;

/**
 * High-level normalizer resolving whether an arbitrary input string
 * is an email, standard username, or custom identifier.
 */
final class IdentifierNormalizer
{
    public static function resolve(string $rawInput): UserIdentity
    {
        $trimmed = trim($rawInput);

        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL) !== false) {
            return new UserIdentity(
                raw: $rawInput,
                normalized: EmailNormalizer::normalize($trimmed),
                type: 'email'
            );
        }

        return new UserIdentity(
            raw: $rawInput,
            normalized: UsernameNormalizer::normalize($trimmed),
            type: 'username'
        );
    }
}
