<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature\Security;

use Illuminate\Support\Facades\Cache;
use Vendor\LaravelAuthentication\Contracts\AuthenticationAbusePolicyInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Tests\TestCase;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;

/**
 * Test: Network prefix rate limiting prevents IPv6 rotation within /64 prefix.
 * 
 * Attack: Attacker rotates IPv6 privacy addresses within same /64 subnet to bypass per-IP limits.
 * Defense: Normalize IPv6 to /64 prefix, IPv4 to /24, share budget across prefix.
 */
final class NetworkPrefixLimitTest extends TestCase
{
    private AuthenticationAbusePolicyInterface $policy;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        
        $this->policy = app(AuthenticationAbusePolicyInterface::class);
        
        config([
            'authentication.security.abuse_policy.enabled' => true,
            'authentication.security.abuse_policy.dimensions.network.max_attempts' => 10,
            'authentication.security.abuse_policy.dimensions.network.decay_minutes' => 1,
        ]);
    }

    public function test_ipv6_rotation_within_64_prefix_shares_network_budget(): void
    {
        User::create([
            'email' => 'victim@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $prefix = '2001:db8:abcd:1234';
        $rotatedIPs = [
            "{$prefix}::1",
            "{$prefix}::2",
            "{$prefix}::abcd",
            "{$prefix}::ff:1234",
            "{$prefix}::dead:beef",
            "{$prefix}::cafe:babe",
            "{$prefix}::1111:2222",
            "{$prefix}::9999:aaaa",
            "{$prefix}::face:b00c",
            "{$prefix}::1337:cafe",
            "{$prefix}::ffff:1",
        ];

        $throttled = false;
        $attemptCount = 0;

        foreach ($rotatedIPs as $ip) {
            $loginData = new LoginData(
                identifier: "attacker{$attemptCount}@example.com",
                password: 'wrong-password',
                remember: false,
                strategy: 'email'
            );

            $context = new AuthenticationContext(
                ipAddress: $ip,
                userAgent: 'Test/1.0',
                channel: AuthenticationChannel::WEB
            );

            $decision = $this->policy->evaluate($loginData, $context);
            
            if (!$decision->allowed) {
                $throttled = true;
                break;
            }

            $this->policy->recordFailure($loginData, $context);
            $attemptCount++;
        }

        $this->assertTrue($throttled, 'IPv6 rotation within /64 bypassed network prefix limiter');
        $this->assertLessThanOrEqual(11, $attemptCount, 'Network limiter threshold incorrect');
    }

    public function test_ipv4_rotation_within_24_prefix_shares_network_budget(): void
    {
        User::create([
            'email' => 'victim@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $prefix = '192.168.1';
        $rotatedIPs = [
            "{$prefix}.1",
            "{$prefix}.2",
            "{$prefix}.50",
            "{$prefix}.100",
            "{$prefix}.150",
            "{$prefix}.200",
            "{$prefix}.210",
            "{$prefix}.220",
            "{$prefix}.230",
            "{$prefix}.240",
            "{$prefix}.250",
        ];

        $throttled = false;
        $attemptCount = 0;

        foreach ($rotatedIPs as $ip) {
            $loginData = new LoginData(
                identifier: "attacker{$attemptCount}@example.com",
                password: 'wrong-password',
                remember: false,
                strategy: 'email'
            );

            $context = new AuthenticationContext(
                ipAddress: $ip,
                userAgent: 'Test/1.0',
                channel: AuthenticationChannel::WEB
            );

            $decision = $this->policy->evaluate($loginData, $context);
            
            if (!$decision->allowed) {
                $throttled = true;
                break;
            }

            $this->policy->recordFailure($loginData, $context);
            $attemptCount++;
        }

        $this->assertTrue($throttled, 'IPv4 rotation within /24 bypassed network prefix limiter');
        $this->assertLessThanOrEqual(11, $attemptCount, 'Network limiter threshold incorrect');
    }

    public function test_different_ipv6_64_prefixes_have_independent_budgets(): void
    {
        User::create([
            'email' => 'victim@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $maxAttempts = 10;

        // Exhaust budget for first /64 prefix
        for ($i = 0; $i < $maxAttempts; $i++) {
            $loginData = new LoginData(
                identifier: "attacker{$i}@example.com",
                password: 'wrong-password',
                remember: false,
                strategy: 'email'
            );

            $context = new AuthenticationContext(
                ipAddress: "2001:db8:aaaa:1111::{$i}",
                userAgent: 'Test/1.0',
                channel: AuthenticationChannel::WEB
            );

            $this->policy->recordFailure($loginData, $context);
        }

        // Different /64 prefix should still work
        $loginData = new LoginData(
            identifier: 'attacker@example.com',
            password: 'wrong-password',
            remember: false,
            strategy: 'email'
        );

        $context = new AuthenticationContext(
            ipAddress: '2001:db8:bbbb:2222::1',
            userAgent: 'Test/1.0',
            channel: AuthenticationChannel::WEB
        );

        $decision = $this->policy->evaluate($loginData, $context);

        $this->assertTrue($decision->allowed, 'Different /64 prefix blocked incorrectly');
    }

    public function test_network_key_is_hashed_and_namespaced(): void
    {
        $cacheStore = Cache::getStore();
        
        User::create([
            'email' => 'test@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $loginData = new LoginData(
            identifier: 'test@example.com',
            password: 'wrong-password',
            remember: false,
            strategy: 'email'
        );

        $context = new AuthenticationContext(
            ipAddress: '2001:db8:abcd:1234::5678',
            userAgent: 'Test/1.0',
            channel: AuthenticationChannel::WEB
        );

        $this->policy->recordFailure($loginData, $context);

        // Collect all cache keys
        $allKeys = [];
        if (method_exists($cacheStore, 'getKeys')) {
            $allKeys = $cacheStore->getKeys();
        } elseif (method_exists($cacheStore, 'many')) {
            // Fallback: collect via array driver
            $reflection = new \ReflectionClass($cacheStore);
            if ($reflection->hasProperty('storage')) {
                $prop = $reflection->getProperty('storage');
                $prop->setAccessible(true);
                $allKeys = array_keys($prop->getValue($cacheStore));
            }
        }

        // Network keys must not contain raw IP or identifier
        foreach ($allKeys as $key) {
            $this->assertStringNotContainsString('2001:db8:abcd:1234', (string) $key, 'Raw IPv6 leaked into cache key');
            $this->assertStringNotContainsString('test@example.com', (string) $key, 'Raw identifier leaked into cache key');
        }
    }
}
