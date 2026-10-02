<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Vendor\LaravelAuthentication\Services\Security\RateLimitKeyFactory;

final class RateLimitKeyFactoryTest extends TestCase
{
    private RateLimitKeyFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new RateLimitKeyFactory();
    }

    public function test_ip_dimension_hashes_ipv4(): void
    {
        $key = $this->factory->make('login', '192.168.1.100', null, 'ip');

        $this->assertStringStartsWith('auth_rl:login:default:ip:', $key);
        $this->assertStringNotContainsString('192.168', $key);
        $this->assertMatchesRegularExpression('/^auth_rl:login:default:ip:[a-f0-9]{64}$/', $key);
    }

    public function test_ip_dimension_normalizes_ipv6(): void
    {
        $key1 = $this->factory->make('login', '2001:0db8:0000:0000:0000:ff00:0042:8329', null, 'ip');
        $key2 = $this->factory->make('login', '2001:db8::ff00:42:8329', null, 'ip');

        $this->assertSame($key1, $key2, 'IPv6 normalization failed: compressed and expanded forms must hash identically');
        $this->assertStringNotContainsString('2001', $key1);
    }

    public function test_identifier_dimension_hashes_account(): void
    {
        $key = $this->factory->make('login', '127.0.0.1', 'alice@example.com', 'identifier');

        $this->assertStringStartsWith('auth_rl:login:default:identifier:', $key);
        $this->assertStringNotContainsString('alice', $key);
        $this->assertStringNotContainsString('example.com', $key);
        $this->assertMatchesRegularExpression('/^auth_rl:login:default:identifier:[a-f0-9]{64}$/', $key);
    }

    public function test_composite_dimension_hashes_both(): void
    {
        $key = $this->factory->make('login', '10.0.0.1', 'bob@test.com', 'composite');

        $this->assertStringStartsWith('auth_rl:login:default:composite:', $key);
        $this->assertStringNotContainsString('10.0.0.1', $key);
        $this->assertStringNotContainsString('bob', $key);
        $this->assertMatchesRegularExpression('/^auth_rl:login:default:composite:[a-f0-9]{64}$/', $key);
    }

    public function test_composite_changes_when_either_input_changes(): void
    {
        $key1 = $this->factory->make('login', '10.0.0.1', 'user@test.com', 'composite');
        $key2 = $this->factory->make('login', '10.0.0.2', 'user@test.com', 'composite');
        $key3 = $this->factory->make('login', '10.0.0.1', 'other@test.com', 'composite');

        $this->assertNotSame($key1, $key2, 'Composite key must change when IP changes');
        $this->assertNotSame($key1, $key3, 'Composite key must change when identifier changes');
    }

    public function test_client_scope_affects_key(): void
    {
        $globalKey = $this->factory->make('login', '10.0.0.1', null, 'ip');
        $clientKey = $this->factory->make('login', '10.0.0.1', null, 'ip', 'mobile-app');

        $this->assertStringContainsString(':default:', $globalKey);
        $this->assertStringContainsString(':mobile-app:', $clientKey);
        $this->assertNotSame($globalKey, $clientKey);
    }

    public function test_feature_namespace_isolates_keys(): void
    {
        $loginKey = $this->factory->make('login', '10.0.0.1', 'user@test.com', 'composite');
        $otpKey = $this->factory->make('otp_request', '10.0.0.1', 'user@test.com', 'composite');

        $this->assertStringStartsWith('auth_rl:login:', $loginKey);
        $this->assertStringStartsWith('auth_rl:otp_request:', $otpKey);
        $this->assertNotSame($loginKey, $otpKey);
    }

    public function test_null_identifier_is_handled_safely(): void
    {
        $key = $this->factory->make('login', '10.0.0.1', null, 'identifier');

        $this->assertStringStartsWith('auth_rl:login:default:identifier:', $key);
        $this->assertMatchesRegularExpression('/^auth_rl:login:default:identifier:[a-f0-9]{64}$/', $key);
    }

    public function test_empty_identifier_is_handled_safely(): void
    {
        $key = $this->factory->make('login', '10.0.0.1', '', 'identifier');

        $this->assertStringStartsWith('auth_rl:login:default:identifier:', $key);
        $this->assertMatchesRegularExpression('/^auth_rl:login:default:identifier:[a-f0-9]{64}$/', $key);
    }

    public function test_identifier_normalization(): void
    {
        $key1 = $this->factory->make('login', '10.0.0.1', 'Alice@Example.COM', 'identifier');
        $key2 = $this->factory->make('login', '10.0.0.1', 'alice@example.com', 'identifier');

        $this->assertSame($key1, $key2, 'Email normalization failed: case-insensitive emails must hash identically');
    }

    public function test_hash_output_length_is_consistent(): void
    {
        $key = $this->factory->make('login', '192.168.1.1', 'test@example.com', 'composite');
        $hash = substr($key, strrpos($key, ':') + 1);

        $this->assertSame(64, strlen($hash), 'SHA-256 hash must be 64 hex characters');
    }

    public function test_key_format_structure(): void
    {
        $key = $this->factory->make('otp_verify', '10.0.0.1', 'user@test.com', 'identifier', 'web-client');

        $parts = explode(':', $key);
        $this->assertCount(5, $parts);
        $this->assertSame('auth_rl', $parts[0]);
        $this->assertSame('otp_verify', $parts[1]);
        $this->assertSame('web-client', $parts[2]);
        $this->assertSame('identifier', $parts[3]);
        $this->assertSame(64, strlen($parts[4]));
    }

    public function test_ipv6_loopback_normalization(): void
    {
        $key1 = $this->factory->make('login', '::1', null, 'ip');
        $key2 = $this->factory->make('login', '0000:0000:0000:0000:0000:0000:0000:0001', null, 'ip');

        $this->assertSame($key1, $key2, 'IPv6 loopback normalization failed');
    }

    public function test_identical_inputs_produce_identical_keys(): void
    {
        $key1 = $this->factory->make('login', '10.0.0.1', 'user@test.com', 'composite', 'app');
        $key2 = $this->factory->make('login', '10.0.0.1', 'user@test.com', 'composite', 'app');

        $this->assertSame($key1, $key2, 'Identical inputs must produce identical keys (idempotency)');
    }
}
