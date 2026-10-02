<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\RateLimitDecision;

/**
 * Contract for evaluating authentication abuse policy across multiple dimensions.
 * 
 * Purpose:
 * Central orchestration point for rate limiting, challenge escalation, and distributed attack detection.
 * Phase 1: Delegates to existing FeatureRateLimiter for backward compatibility.
 * Phase 2+: Multi-dimensional evaluation (account, network, client, global budgets).
 */
interface AuthenticationAbusePolicyInterface
{
    /**
     * Evaluate whether an authentication request is allowed, challenged, throttled, or denied.
     */
    public function evaluate(LoginData $data, AuthenticationContext $context): RateLimitDecision;

    /**
     * Record a failed authentication attempt.
     */
    public function recordFailure(LoginData $data, AuthenticationContext $context): void;

    /**
     * Clear failure counters after successful authentication.
     */
    public function clearFailures(LoginData $data, AuthenticationContext $context): void;

    /**
     * Generate a challenge token for adaptive escalation.
     * Single-use, time-bound, context-bound token.
     * Does NOT reveal account existence.
     */
    public function generateChallengeToken(LoginData $data, AuthenticationContext $context): string;

    /**
     * Verify a challenge token (single-use, atomic consumption).
     * Returns true on first valid verification, false on replay/expiry/invalid.
     */
    public function verifyChallengeToken(string $token, LoginData $data, AuthenticationContext $context): bool;
}
