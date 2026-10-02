<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;

final readonly class DistributedAttackRisk
{
    public function __construct(
        public float $score,
        public int $distinctIdentifiers,
        public int $distinctIps,
        public bool $hardDeny = false,
    ) {}
}

/**
 * Scores distributed credential attacks without blocking requests.
 * Distinct markers and aggregate counters expire together, bounding memory and time.
 */
final class DistributedAttackDetector
{
    public function __construct(
        private RateLimiter $rateLimiter,
        private Repository $cache,
        private int $decaySeconds = 300,
        private int $identifierThreshold = 100,
        private int $ipThreshold = 100,
    ) {}

    public function assess(string $ipAddress, string $identifier): DistributedAttackRisk
    {
        $ipHash = hash('sha256', $ipAddress);
        $identifierHash = hash('sha256', mb_strtolower(trim($identifier)));

        $idMarker = "auth_rl:distributed:id:{$ipHash}:{$identifierHash}";
        $ipMarker = "auth_rl:distributed:ip:{$identifierHash}:{$ipHash}";
        $idAggregate = "auth_rl:distributed:identifiers:{$ipHash}";
        $ipAggregate = "auth_rl:distributed:ips:{$identifierHash}";

        if ($this->cache->add($idMarker, 1, $this->decaySeconds)) {
            $this->rateLimiter->hit($idAggregate, $this->decaySeconds);
        }
        if ($this->cache->add($ipMarker, 1, $this->decaySeconds)) {
            $this->rateLimiter->hit($ipAggregate, $this->decaySeconds);
        }

        $distinctIdentifiers = $this->rateLimiter->attempts($idAggregate);
        $distinctIps = $this->rateLimiter->attempts($ipAggregate);
        $score = min(1.0, max(
            $distinctIdentifiers / $this->identifierThreshold,
            $distinctIps / $this->ipThreshold,
        ));

        return new DistributedAttackRisk($score, $distinctIdentifiers, $distinctIps);
    }
}
