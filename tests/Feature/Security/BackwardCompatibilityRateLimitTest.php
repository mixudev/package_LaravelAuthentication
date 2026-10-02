<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature\Security;

use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Services\Security\AuthenticationAbusePolicy;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Backward compatibility tests: abuse_policy disabled = legacy composite behavior.
 */
final class BackwardCompatibilityRateLimitTest extends TestCase
{
    /**
     * Test: When abuse_policy disabled, legacy FeatureRateLimiter composite strategy is used.
     */
    public function test_legacy_behavior_when_abuse_policy_disabled(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => false,
            'dimensions' => [],
        ]);

        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);

        $loginData = new LoginData('user@example.test', 'wrongpass');
        $context = new AuthenticationContext('192.168.1.1', 'Client/1.0');

        // Exhaust legacy composite limit (identifier + IP)
        $maxAttempts = (int) config('authentication.security.rate_limits.login.max_attempts', 5);
        for ($i = 0; $i < $maxAttempts; $i++) {
            $policy->recordFailure($loginData, $context);
        }

        $decision = $policy->evaluate($loginData, $context);

        // Should be throttled by legacy composite limiter
        $this->assertFalse($decision->allowed);
        $this->assertEquals('throttle', $decision->action);
        $this->assertGreaterThan(0, $decision->retryAfter);
    }

    /**
     * Test: Same identifier from different IP is allowed when abuse_policy disabled.
     */
    public function test_legacy_allows_ip_rotation(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => false,
        ]);

        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);

        $identifier = 'victim@example.test';

        // Exhaust composite limit for identifier+IP1
        $loginData1 = new LoginData($identifier, 'wrongpass');
        $context1 = new AuthenticationContext('192.168.1.1', 'Client/1.0');

        $maxAttempts = (int) config('authentication.security.rate_limits.login.max_attempts', 5);
        for ($i = 0; $i < $maxAttempts; $i++) {
            $policy->recordFailure($loginData1, $context1);
        }

        // Same identifier from different IP should be allowed (legacy composite = identifier+IP)
        $loginData2 = new LoginData($identifier, 'wrongpass');
        $context2 = new AuthenticationContext('192.168.1.2', 'Client/1.0');

        $decision = $policy->evaluate($loginData2, $context2);

        $this->assertTrue($decision->allowed, 'Legacy composite allows IP rotation');
    }

    /**
     * Test: clearFailures works in legacy mode.
     */
    public function test_legacy_clear_failures(): void
    {
        config()->set('authentication.security.abuse_policy.enabled', false);

        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);

        $loginData = new LoginData('user@example.test', 'wrongpass');
        $context = new AuthenticationContext('192.168.1.1', 'Client/1.0');

        // Record some failures
        $policy->recordFailure($loginData, $context);
        $policy->recordFailure($loginData, $context);

        // Clear
        $policy->clearFailures($loginData, $context);

        // Should be allowed
        $decision = $policy->evaluate($loginData, $context);
        $this->assertTrue($decision->allowed);
    }
}
