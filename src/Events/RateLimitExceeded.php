<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched when authentication rate limit is exceeded.
 * 
 * Security: No raw identifiers or IPs - only hashed values and reason codes.
 */
class RateLimitExceeded
{
    use Dispatchable, SerializesModels;

    public readonly string $identifierHash;
    public readonly string $networkBucket;

    public function __construct(
        string $identifier,
        AuthenticationContext $context,
        public readonly string $reasonCode,
        public readonly int $retryAfterSeconds
    ) {
        // SEC: Hash identifier, never store raw value
        $this->identifierHash = hash('sha256', $identifier);
        
        // SEC: Compute network bucket from /24 CIDR, never store raw IP
        $this->networkBucket = $this->computeNetworkBucket($context->ipAddress);
    }

    private function computeNetworkBucket(string $ip): string
    {
        // Extract /24 network for IPv4, /64 for IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $cidr = implode('.', array_slice($parts, 0, 3)) . '.0/24';
        } else {
            // IPv6: use first 4 hextets (/64)
            $parts = explode(':', $ip);
            $cidr = implode(':', array_slice($parts, 0, 4)) . '::/64';
        }
        
        return hash('sha256', $cidr);
    }
}
