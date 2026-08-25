<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support\Normalizers;

/**
 * Normalizes username inputs while respecting case sensitivity preferences and removing surrounding spaces.
 */
final class UsernameNormalizer
{
    public static function normalize(string $username, bool $lowercase = true): string
    {
        $username = trim($username);

        if ($lowercase) {
            return function_exists('mb_strtolower') ? mb_strtolower($username, 'UTF-8') : strtolower($username);
        }

        return $username;
    }
}
