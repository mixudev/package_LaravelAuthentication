<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature\Security;

use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Services\Security\AuthenticationAbusePolicy;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Feature tests for multi-dimensional abuse policy enforcement.
 * 
 * Attack patterns tested:
 * - IP rotation: one identifier from many IPs (credential stuffing)
 * - Identifier rotation: many identifiers from one IP (account enumeration)
 * - Network-wide budget exhaustion
 * - Global platform protection
 */
final class MultiDimensionalRateLimitTest extends TestCase
{
    /**
     * Test: Identifier rotation from same IP is blocked by account dimension.
     */
    public function test_blocks_identifier_rotation_from_same_ip(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => true,
            'dimensions' => [
                'account' => ['max_attempts' => 3, 'decay_minutes' => 1],
            ],
        ]);

        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);
        $ip = '203.0.113.100';

        // Attack: Try many identifiers from same IP
        $loginData1 = new LoginData('victim1@example.test', 'wrongpass');
        $context1 = new AuthenticationContext($ip, 'AttackerBot/1.0');

        // Exhaust account dimension for victim1
        for ($i = 0; $i < 3; $i++) {
            $policy->recordFailure($loginData1, $context1);
        }

        $decision1 = $policy->evaluate($loginData1, $context1);
        $this->assertFalse($decision1->allowed, 'Account dimension should throttle victim1');
        $this->assertEquals('throttle', $decision1->action);

        // Different identifier from same IP should still be allowed (account dimension is per-identifier)
        $loginData2 = new LoginData('victim2@example.test', 'wrongpass');
        $context2 = new AuthenticationContext($ip, 'AttackerBot/1.0');

        $decision2 = $policy->evaluate($loginData2, $context2);
        $this->assertTrue($decision2->allowed, 'Different account should not be throttled by account dimension');
    }

    /**
     * Test: IP rotation is blocked by account dimension (same identifier, many IPs).
     */
    public function test_blocks_ip_rotation_for_same_identifier(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => true,
            'dimensions' => [
                'account' => ['max_attempts' => 3, 'decay_minutes' => 1],
            ],
        ]);

        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);
        $identifier = 'victim@example.test';

        // Attack: Try same identifier from different IPs
        for ($i = 0; $i < 3; $i++) {
            $loginData = new LoginData($identifier, 'wrongpass');
            $context = new AuthenticationContext("203.0.113.{$i}", "Bot/1.0");
            $policy->recordFailure($loginData, $context);
        }

        // Fourth attempt from yet another IP should be blocked by account dimension
        $loginData = new LoginData($identifier, 'wrongpass');
        $context = new AuthenticationContext('203.0.113.99', 'Bot/1.0');
        $decision = $policy->evaluate($loginData, $context);

        $this->assertFalse($decision->allowed, 'Account dimension blocks IP rotation');
        $this->assertEquals('throttle', $decision->action);
    }

    /**
     * Test: Network dimension blocks all requests from same IP regardless of identifier.
     */
    public function test_blocks_all_requests_from_exhausted_network(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => true,
            'dimensions' => [
                'network' => ['max_attempts' => 5, 'decay_minutes' => 1],
            ],
        ]);

        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);
        $ip = '203.0.113.50';

        // Exhaust network budget with different identifiers
        for ($i = 0; $i < 5; $i++) {
            $loginData = new LoginData("user{$i}@example.test", 'wrongpass');
            $context = new AuthenticationContext($ip, 'Bot/1.0');
            $policy->recordFailure($loginData, $context);
        }

        // Any identifier from exhausted IP should be blocked
        $loginData = new LoginData('newuser@example.test', 'wrongpass');
        $context = new AuthenticationContext($ip, 'Bot/1.0');
        $decision = $policy->evaluate($loginData, $context);

        $this->assertFalse($decision->allowed, 'Network dimension blocks entire IP');
        $this->assertEquals('throttle', $decision->action);
    }

    /**
     * Test: Global dimension protects platform-wide capacity.
     */
    public function test_global_dimension_protects_platform_capacity(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => true,
            'dimensions' => [
                'global' => ['max_attempts' => 10, 'decay_minutes' => 1],
            ],
        ]);

        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);

        // Exhaust global budget with different IPs and identifiers
        for ($i = 0; $i < 10; $i++) {
            $loginData = new LoginData("user{$i}@example.test", 'wrongpass');
            $context = new AuthenticationContext("203.0.113.{$i}", 'Bot/1.0');
            $policy->recordFailure($loginData, $context);
        }

        // Any request from anywhere should be blocked
        $loginData = new LoginData('anyuser@example.test', 'wrongpass');
        $context = new AuthenticationContext('192.168.1.1', 'Bot/1.0');
        $decision = $policy->evaluate($loginData, $context);

        $this->assertFalse($decision->allowed, 'Global dimension protects platform');
        $this->assertEquals('throttle', $decision->action);
    }

    /**
     * Test: Most restrictive dimension wins (first deny/throttle decision returned).
     */
    public function test_returns_most_restrictive_decision(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => true,
            'dimensions' => [
                'account' => ['max_attempts' => 2, 'decay_minutes' => 1],
                'network' => ['max_attempts' => 10, 'decay_minutes' => 1],
            ],
        ]);

        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);

        $loginData = new LoginData('victim@example.test', 'wrongpass');
        $context = new AuthenticationContext('203.0.113.1', 'Bot/1.0');

        // Exhaust account dimension only
        for ($i = 0; $i < 2; $i++) {
            $policy->recordFailure($loginData, $context);
        }

        $decision = $policy->evaluate($loginData, $context);

        // Should be blocked by account dimension even though network has budget
        $this->assertFalse($decision->allowed);
        $this->assertEquals('throttle', $decision->action);
    }

    /**
     * Test: Successful login clears only relevant failure counters.
     */
    public function test_clear_failures_resets_relevant_dimensions(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => true,
            'dimensions' => [
                'account' => ['max_attempts' => 2, 'decay_minutes' => 1],
                'network' => ['max_attempts' => 5, 'decay_minutes' => 1],
            ],
        ]);

        /** @var AuthenticationAbusePolicy $policy */
        $policy = app(AuthenticationAbusePolicy::class);

        $loginData = new LoginData('user@example.test', 'wrongpass');
        $context = new AuthenticationContext('203.0.113.1', 'Client/1.0');

        // Record failures
        $policy->recordFailure($loginData, $context);
        $policy->recordFailure($loginData, $context);

        // Clear after successful login
        $policy->clearFailures($loginData, $context);

        // Should be allowed again
        $decision = $policy->evaluate($loginData, $context);
        $this->assertTrue($decision->allowed);
    }
}
