<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support\Normalizers;

/**
 * Normalizes email strings safely without destructive stripping.
 */
final class EmailNormalizer
{
    public static function normalize(string $email): string
    {
        $email = trim($email);

        if (function_exists('mb_strtolower')) {
            $email = mb_strtolower($email, 'UTF-8');
        } else {
            $email = strtolower($email);
        }

        return $email;
    }
}
