<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use Illuminate\Http\Request;

/**
 * Resolves the security client address from the TCP peer.
 * Forwarded headers are accepted only when the immediate peer is explicitly
 * configured as a trusted proxy by the host application.
 */
final class ClientIpResolver
{
    /**
     * @param array<int, mixed>|null $trustedProxy Override; defaults to package config.
     */
    public static function resolve(Request $request, ?array $trustedProxy = null): string
    {
        $remote = $request->server('REMOTE_ADDR');
        $peer = is_string($remote) && $remote !== '' ? $remote : '0.0.0.0';
        $trusted = $trustedProxy ?? (array) config('authentication.security.trusted_proxies', []);

        // Validate and filter trusted proxies
        $trusted = array_values(array_filter($trusted, fn($cidr) => is_string($cidr) && $cidr !== '' && self::isValidCidr($cidr)));

        if ($trusted === [] || !self::matchesAny($peer, $trusted)) {
            return $peer;
        }

        $forwarded = $request->header('X-Forwarded-For') ?: $request->header('X-Real-IP') ?: $request->header('Client-IP');
        if (!is_string($forwarded) || $forwarded === '') {
            return $peer;
        }

        $candidate = trim(explode(',', $forwarded)[0]);

        return filter_var($candidate, FILTER_VALIDATE_IP) !== false ? $candidate : $peer;
    }

    /**
     * Validate CIDR format (IPv4 or IPv6).
     * A bare IP without a prefix is treated as /32 (IPv4) or /128 (IPv6).
     */
    private static function isValidCidr(string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        $network = $parts[0];

        if (filter_var($network, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (count($parts) === 1) {
            // Bare IP — treat as /32 or /128
            return true;
        }

        $prefix = filter_var($parts[1], FILTER_VALIDATE_INT);
        if ($prefix === false || $prefix <= 0) {
            return false;
        }

        $ipBinary = inet_pton($network);
        if ($ipBinary === false) {
            return false;
        }

        return $prefix <= (strlen($ipBinary) * 8);
    }

    /** @param array<int, mixed> $trusted */
    private static function matchesAny(string $ip, array $trusted): bool
    {
        foreach ($trusted as $range) {
            if (!is_string($range) || $range === '') {
                continue;
            }

            if ($range === $ip || self::matchesCidr($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesCidr(string $ip, string $cidr): bool
    {
        [$network, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        // Validate CIDR format
        if ($network === null || $bits === null) {
            return false;
        }

        // Parse and validate prefix length
        $prefix = filter_var($bits, FILTER_VALIDATE_INT);
        if ($prefix === false || $prefix <= 0) {
            return false;
        }

        $ipBinary = inet_pton($ip);
        $networkBinary = inet_pton((string) $network);

        if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }

        $maxBits = strlen($ipBinary) * 8;
        if ($prefix > $maxBits) {
            return false;
        }

        $bytes = intdiv($prefix, 8);
        $remaining = $prefix % 8;

        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($networkBinary, 0, $bytes)) {
            return false;
        }

        if ($remaining === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remaining)) & 0xFF);

        return ($ipBinary[$bytes] & $mask) === ($networkBinary[$bytes] & $mask);
    }
}
