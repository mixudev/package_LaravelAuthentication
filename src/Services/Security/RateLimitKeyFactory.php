<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security;

use Vendor\LaravelAuthentication\Support\Normalizers\EmailNormalizer;

/**
 * Generate secure, hashed, namespaced rate limit keys.
 * 
 * Key format: auth_rl:{feature}:{client}:{dimension}:{hash}
 * 
 * Security invariants:
 * - No raw IP addresses or identifiers in keys (SHA-256 hashed)
 * - IPv6 addresses normalized before hashing (inet_pton canonical form)
 * - Email identifiers normalized (lowercase) before hashing
 * - Feature and client namespacing prevent dimension collisions
 * 
 * @internal Used by FeatureRateLimiter and AuthenticationAbusePolicy
 */
final class RateLimitKeyFactory
{
    /**
     * Generate a rate limit key.
     * 
     * @param string $feature Feature name (login, otp_request, etc.)
     * @param string $ipAddress Client IP address (IPv4 or IPv6)
     * @param string|null $identifier User identifier (email, username, etc.)
     * @param string $dimension Limiting dimension: 'ip', 'identifier', 'composite'
     * @param string $client Client scope (default: 'global')
     * @return string Hashed namespaced key
     */
    public function make(
        string $feature,
        string $ipAddress,
        ?string $identifier,
        string $dimension,
        string $client = 'default'
    ): string {
        $payload = match ($dimension) {
            'ip'         => $this->normalizeIp($ipAddress),
            'identifier' => $this->normalizeIdentifier($identifier),
            'composite'  => $this->normalizeIp($ipAddress) . '|' . $this->normalizeIdentifier($identifier),
            default      => throw new \InvalidArgumentException("Unknown dimension: {$dimension}"),
        };

        $hash = hash('sha256', $payload);

        return "auth_rl:{$feature}:{$client}:{$dimension}:{$hash}";
    }

    /**
     * Normalize IP address to canonical binary form for consistent hashing.
     * 
     * IPv6 addresses are expanded to canonical form (inet_pton handles compression).
     * inet_pton returns false for invalid IPs; fall back to raw string to avoid crashes.
     */
    private function normalizeIp(string $ipAddress): string
    {
        $binary = @inet_pton($ipAddress);

        return $binary !== false ? $binary : $ipAddress;
    }

    /**
     * Normalize identifier for consistent hashing.
     * 
     * Email addresses are lowercased. Null/empty identifiers hash to empty string.
     */
    private function normalizeIdentifier(?string $identifier): string
    {
        if ($identifier === null || $identifier === '') {
            return '';
        }

        return EmailNormalizer::normalize($identifier);
    }
}
