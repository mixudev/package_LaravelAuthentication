<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit\Security;

use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\RateLimitDecision;
use Vendor\LaravelAuthentication\Services\Security\AuthenticationAbusePolicy;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Unit tests for AuthenticationAbusePolicy orchestration boundary.
 * 
 * Phase 1: Behavioral equivalence with existing FeatureRateLimiter.
 * Phase 2+ (future): Multi-dimensional limits, challenge escalation, distributed detection.
 */
class AuthenticationAbusePolicyTest extends TestCase
{
    /**
     * Test: Policy allows request when under rate limit.
     */
    public function test_allows_request_when_under_limit(): void
    {
        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);

        $loginData = new LoginData('test@example.com', 'password123');
        $context = new AuthenticationContext('192.168.1.1', 'TestAgent/1.0');

        $decision = $policy->evaluate($loginData, $context);

        $this->assertTrue($decision->allowed);
        $this->assertEquals('allow', $decision->action);
        $this->assertEquals(0, $decision->retryAfter);
    }

    /**
     * Test: Policy throttles after max attempts exceeded.
     */
    public function test_throttles_after_max_attempts(): void
    {
        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);

        $loginData = new LoginData('throttle-test@example.com', 'wrongpassword');
        $context = new AuthenticationContext('203.0.113.5', 'AttackerAgent/1.0');

        // Exhaust rate limit
        $maxAttempts = (int) config('authentication.rate_limits.login.max_attempts', 5);
        for ($i = 0; $i < $maxAttempts; $i++) {
            $policy->recordFailure($loginData, $context);
        }

        $decision = $policy->evaluate($loginData, $context);

        $this->assertFalse($decision->allowed);
        $this->assertEquals('throttle', $decision->action);
        $this->assertGreaterThan(0, $decision->retryAfter);
    }

    /**
     * Test: RateLimitDecision DTO validates action bounds.
     */
    public function test_decision_rejects_invalid_action(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid action');

        new RateLimitDecision(true, 'invalid_action', 0);
    }

    /**
     * Test: RateLimitDecision DTO validates non-negative retryAfter.
     */
    public function test_decision_rejects_negative_retry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('retryAfter must be non-negative');

        new RateLimitDecision(false, 'throttle', -5);
    }

    /**
     * Test: Decision factory methods produce correct shape.
     */
    public function test_decision_factories(): void
    {
        $allow = RateLimitDecision::allow();
        $this->assertTrue($allow->allowed);
        $this->assertEquals('allow', $allow->action);

        $challenge = RateLimitDecision::challenge(30);
        $this->assertFalse($challenge->allowed);
        $this->assertEquals('challenge', $challenge->action);
        $this->assertEquals(30, $challenge->retryAfter);

        $throttle = RateLimitDecision::throttle(60);
        $this->assertFalse($throttle->allowed);
        $this->assertEquals('throttle', $throttle->action);
        $this->assertEquals(60, $throttle->retryAfter);

        $deny = RateLimitDecision::deny();
        $this->assertFalse($deny->allowed);
        $this->assertEquals('deny', $deny->action);
    }
}
