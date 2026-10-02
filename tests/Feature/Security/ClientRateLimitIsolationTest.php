<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Services\Security\FeatureRateLimiter;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Verifies per-client rate limit isolation.
 * Client A exhausting its quota must not affect Client B's budget.
 */
class ClientRateLimitIsolationTest extends TestCase
{

    public function test_client_a_exhausting_quota_does_not_block_client_b(): void
    {
        config(['authentication.security.rate_limits.login.max_attempts' => 3]);
        config(['authentication.security.rate_limits.login.enabled' => true]);
        config(['authentication.security.rate_limits.login.strategy' => 'composite']);

        /** @var FeatureRateLimiter $limiter */
        $limiter = app(FeatureRateLimiter::class);

        $identifier = 'test@example.com';
        $ip = '192.168.1.100';

        // Client A context
        $contextA = new AuthenticationContext(
            ipAddress: $ip,
            userAgent: 'ClientA/1.0',
            clientId: 'client-a'
        );

        // Client B context (same IP, same identifier, different client)
        $contextB = new AuthenticationContext(
            ipAddress: $ip,
            userAgent: 'ClientB/1.0',
            clientId: 'client-b'
        );

        // Exhaust Client A's quota
        for ($i = 0; $i < 3; $i++) {
            $this->assertFalse(
                $limiter->tooManyAttempts('login', $identifier, $ip, $contextA->clientId),
                "Client A attempt {$i} should not be throttled yet"
            );
            $limiter->hit('login', $identifier, $ip, $contextA->clientId);
        }

        // Client A should now be throttled
        $this->assertTrue(
            $limiter->tooManyAttempts('login', $identifier, $ip, $contextA->clientId),
            'Client A should be throttled after 3 attempts'
        );

        // Client B should still have full quota (isolation proof)
        $this->assertFalse(
            $limiter->tooManyAttempts('login', $identifier, $ip, $contextB->clientId),
            'Client B must not be affected by Client A exhaustion'
        );

        // Verify Client B can make all 3 attempts independently
        for ($i = 0; $i < 3; $i++) {
            $this->assertFalse(
                $limiter->tooManyAttempts('login', $identifier, $ip, $contextB->clientId),
                "Client B attempt {$i} should not be throttled"
            );
            $limiter->hit('login', $identifier, $ip, $contextB->clientId);
        }

        // Now Client B should be throttled
        $this->assertTrue(
            $limiter->tooManyAttempts('login', $identifier, $ip, $contextB->clientId),
            'Client B should be throttled after its own 3 attempts'
        );

        // Client A still throttled (independent counters)
        $this->assertTrue(
            $limiter->tooManyAttempts('login', $identifier, $ip, $contextA->clientId),
            'Client A should remain throttled'
        );
    }

    public function test_default_client_id_when_not_provided(): void
    {
        /** @var FeatureRateLimiter $limiter */
        $limiter = app(FeatureRateLimiter::class);

        $identifier = 'user@example.com';
        $ip = '10.0.0.1';

        // Context without explicit clientId
        $context = new AuthenticationContext(
            ipAddress: $ip,
            userAgent: 'DefaultClient/1.0'
        );

        $this->assertSame('default', $context->clientId, 'clientId should default to "default"');

        // Should work with default client
        $this->assertFalse($limiter->tooManyAttempts('login', $identifier, $ip, $context->clientId));
        $limiter->hit('login', $identifier, $ip, $context->clientId);
        $this->assertSame(1, $limiter->attempts('login', $identifier, $ip, $context->clientId));
    }

    public function test_clear_attempts_only_affects_target_client(): void
    {
        config(['authentication.security.rate_limits.login.enabled' => true]);

        /** @var FeatureRateLimiter $limiter */
        $limiter = app(FeatureRateLimiter::class);

        $identifier = 'shared@example.com';
        $ip = '172.16.0.1';

        $contextA = new AuthenticationContext($ip, 'A', clientId: 'app-one');
        $contextB = new AuthenticationContext($ip, 'B', clientId: 'app-two');

        // Both clients record attempts
        $limiter->hit('login', $identifier, $ip, $contextA->clientId);
        $limiter->hit('login', $identifier, $ip, $contextA->clientId);
        $limiter->hit('login', $identifier, $ip, $contextB->clientId);

        $this->assertSame(2, $limiter->attempts('login', $identifier, $ip, $contextA->clientId));
        $this->assertSame(1, $limiter->attempts('login', $identifier, $ip, $contextB->clientId));

        // Clear only Client A
        $limiter->clear('login', $identifier, $ip, $contextA->clientId);

        // Client A cleared, Client B unchanged
        $this->assertSame(0, $limiter->attempts('login', $identifier, $ip, $contextA->clientId));
        $this->assertSame(1, $limiter->attempts('login', $identifier, $ip, $contextB->clientId));
    }
}
