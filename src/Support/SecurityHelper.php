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
     * Automatically resolves validation fallback if raw key is returned.
     *
     * @param array<string, mixed> $replace
     */
    public static function trans(string $key, array $replace = []): string
    {
        // If it's a validation key like 'validation.required'
        if (str_starts_with($key, 'validation.')) {
            $rule = substr($key, 11);
            $attribute = (string) ($replace['attribute'] ?? 'kolom');
            return self::transValidation($rule, $attribute, $replace);
        }

        $res = trans($key, $replace);
        if (is_array($res)) {
            return implode(' ', \Illuminate\Support\Arr::flatten($res));
        }

        $str = (string) $res;

        // If untranslated raw key is returned
        if ($str === $key) {
            $lastSegment = substr($key, strrpos($key, '.') !== false ? strrpos($key, '.') + 1 : 0);
            return ucwords(str_replace('_', ' ', $lastSegment));
        }

        return $str;
    }

    /**
     * Safely translate validation rules with package localization priority and fail-safe fallbacks.
     *
     * @param array<string, mixed> $replace
     */
    public static function transValidation(string $rule, string $attribute, array $replace = []): string
    {
        $replace['attribute'] = $attribute;
        $baseRule = explode('.', $rule)[0];
        
        // 1. Try package translation namespace first
        $packageKey = 'authentication::messages.validation_' . $baseRule;
        $packageTrans = trans($packageKey, $replace);
        if (is_string($packageTrans) && $packageTrans !== $packageKey) {
            return $packageTrans;
        }

        // 2. Try host application validation key
        $laravelKey = 'validation.' . $rule;
        $laravelTrans = trans($laravelKey, $replace);
        if (is_string($laravelTrans) && $laravelTrans !== $laravelKey && !str_starts_with($laravelTrans, 'validation.')) {
            return $laravelTrans;
        }

        $laravelBaseKey = 'validation.' . $baseRule;
        $laravelBaseTrans = trans($laravelBaseKey, $replace);
        if (is_string($laravelBaseTrans) && $laravelBaseTrans !== $laravelBaseKey && !str_starts_with($laravelBaseTrans, 'validation.')) {
            return $laravelBaseTrans;
        }

        // 3. Robust human-readable localized fallback
        $attrName = ucfirst(str_replace('_', ' ', $attribute));
        return match ($baseRule) {
            'required', 'required_without_all', 'required_with' => "{$attrName} wajib diisi.",
            'email' => "{$attrName} harus berupa alamat email yang valid.",
            'min' => "{$attrName} minimal harus " . ($replace['min'] ?? '8') . " karakter.",
            'max' => "{$attrName} maksimal " . ($replace['max'] ?? '255') . " karakter.",
            'confirmed' => "Konfirmasi {$attrName} tidak cocok.",
            'string' => "{$attrName} harus berupa teks.",
            'unique' => "{$attrName} sudah digunakan.",
            'numeric', 'digits' => "{$attrName} harus berupa angka.",
            default => "{$attrName} tidak valid.",
        };
    }
}

