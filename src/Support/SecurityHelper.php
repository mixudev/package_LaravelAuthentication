<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

/**
 * Common security utilities: timing-safe comparisons, redactions, and safe hashing.
 */
final class SecurityHelper
{
    /**
     * Timing-attack-safe string comparison.
     */
    public static function hashEquals(string $knownString, string $userInput): bool
    {
        return hash_equals($knownString, $userInput);
    }

    /**
     * Redact sensitive values from associative data arrays.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function redactSensitive(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_token',
            'remember_token',
            'totp_secret',
            'authorization',
            'cookie',
        ];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }

            if (is_array($value)) {
                $data[$key] = self::redactSensitive($value);
            }
        }

        return $data;
    }

    /**
     * Mask an email or identifier for safe logging/display (e.g. j***e@example.com).
     */
    public static function maskIdentifier(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $identifier, 2);
            $name = $parts[0];
            $domain = $parts[1] ?? '';
            $len = strlen($name);

            if ($len <= 2) {
                $maskedName = substr($name, 0, 1) . '*';
            } else {
                $maskedName = substr($name, 0, 1) . str_repeat('*', $len - 2) . substr($name, -1);
            }

            return $maskedName . '@' . $domain;
        }

        $len = strlen($identifier);
        if ($len <= 3) {
            return str_repeat('*', $len);
        }

        return substr($identifier, 0, 1) . str_repeat('*', $len - 2) . substr($identifier, -1);
    }

    /**
     * Safely translate a key guaranteeing a strict string return type.
     *
     * @param array<string, mixed> $replace
     */
    public static function trans(string $key, array $replace = []): string
    {
        $res = trans($key, $replace);
        if (is_array($res)) {
            return implode(' ', \Illuminate\Support\Arr::flatten($res));
        }

        return (string) $res;
    }
}

