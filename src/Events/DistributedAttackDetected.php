<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Dispatched when distributed attack pattern is detected across multiple identifiers.
 * 
 * Security: No raw identifiers or IPs - only aggregated counts, network buckets, and reason codes.
 */
class DistributedAttackDetected
{
    use Dispatchable, SerializesModels;

    public readonly int $affectedCount;
    public readonly string $networkBucket;

    /**
     * @param array<int, string> $affectedIdentifiers
     */
    public function __construct(
        array $affectedIdentifiers,
        AuthenticationContext $context,
        public readonly string $reasonCode,
        public readonly int $thresholdExceeded
    ) {
        // SEC: Store count only, never store raw identifiers
        $this->affectedCount = count($affectedIdentifiers);
        
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
