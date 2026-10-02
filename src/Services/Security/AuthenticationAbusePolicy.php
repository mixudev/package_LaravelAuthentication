<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security;

use Vendor\LaravelAuthentication\Contracts\AuthenticationAbusePolicyInterface;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\RateLimitDecision;

/**
 * Authentication abuse policy orchestrator.
 * 
 * Phase 1: Behavioral equivalence with existing FeatureRateLimiter (no new dimensions yet).
 * Phase 2+: Add multi-dimensional limits, challenge escalation, distributed detection.
 * 
 * Security invariant:
 * This is a typed boundary for future expansion without changing public contracts.
 */
class AuthenticationAbusePolicy implements AuthenticationAbusePolicyInterface
{
    public function __construct(
        private readonly FeatureRateLimiterInterface $rateLimiter
    ) {}

    public function evaluate(LoginData $data, AuthenticationContext $context): RateLimitDecision
    {
        // Phase 1: Delegate to existing composite limiter (identifier+IP)
        $throttled = $this->rateLimiter->tooManyAttempts('login', $data->identifier, $context->ipAddress);

        if ($throttled) {
            $retryAfter = $this->rateLimiter->availableIn('login', $data->identifier, $context->ipAddress);
            return RateLimitDecision::throttle($retryAfter, 'composite_limit_exceeded');
        }

        return RateLimitDecision::allow('within_composite_budget');
    }

    public function recordFailure(LoginData $data, AuthenticationContext $context): void
    {
        // Phase 1: Hit existing composite counter
        $this->rateLimiter->hit('login', $data->identifier, $context->ipAddress);
    }

    public function clearFailures(LoginData $data, AuthenticationContext $context): void
    {
        // Phase 1: Clear existing composite counter
        $this->rateLimiter->clear('login', $data->identifier, $context->ipAddress);
    }
}
