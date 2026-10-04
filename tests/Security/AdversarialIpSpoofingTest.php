<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Http\Request;
use Vendor\LaravelAuthentication\Support\ClientIpResolver;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Attack-intent test: an untrusted peer must not be able to choose the security IP.
 *
 * X-Forwarded-For / X-Real-IP / Client-IP are attacker-controlled headers. When the
 * TCP peer (REMOTE_ADDR) is not a configured trusted proxy, every rate-limit,
 * lockout, and device-detection key must ignore them.
 */
final class AdversarialIpSpoofingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // A hostile client claiming to be a trusted proxy.
        $app['config']->set('trustedproxy.proxies', ['198.51.100.77']);
        $app['config']->set('authentication.security.global_throttle.max_attempts', 3);
        $app['config']->set('authentication.security.global_throttle.decay_minutes', 1);
    }

    public function test_global_throttle_ignores_forwarded_headers_from_untrusted_peer(): void
    {
        // First requests are served under the spoofed address...
        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders([
                'X-Forwarded-For' => '1.1.1.1',
                'X-Real-IP'      => '1.1.1.1',
                'Client-IP'      => '1.1.1.1',
            ])->postJson('/api/v1/auth/login', [
                'identifier' => 'nobody@example.com',
                'password'   => 'WrongPassword1!',
            ]);
        }

        // ...and the throttle must still engage, bound to the real TCP peer.
        $response = $this->withHeaders([
            'X-Forwarded-For' => '1.1.1.1',
            'X-Real-IP'      => '1.1.1.1',
            'Client-IP'      => '1.1.1.1',
        ])->postJson('/api/v1/auth/login', [
            'identifier' => 'nobody@example.com',
            'password'   => 'WrongPassword1!',
        ]);

        $response->assertStatus(429);
    }

    public function test_security_context_ip_is_the_tcp_peer_not_forwarded_header(): void
    {
        $request = Request::create('/api/v1/auth/login', 'POST', server: [
            'REMOTE_ADDR'          => '198.51.100.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ]);

        $context = \Vendor\LaravelAuthentication\DTO\AuthenticationContext::fromRequest($request);

        $this->assertSame('198.51.100.10', $context->ipAddress);
    }

    public function test_forwarded_header_is_honored_only_for_a_configured_trusted_proxy(): void
    {
        $request = Request::create('/api/v1/auth/login', 'POST', server: [
            'REMOTE_ADDR'          => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ]);

        $this->assertSame(
            '203.0.113.99',
            ClientIpResolver::resolve($request, ['10.0.0.10']),
            'A configured proxy peer must be allowed to forward the real client IP.'
        );

        $this->assertSame(
            '10.0.0.10',
            ClientIpResolver::resolve($request, ['10.1.0.0/16', '192.168.1.1']),
            'An unlisted peer must never supply the forwarded address.'
        );

        $this->assertSame(
            '10.0.0.10',
            ClientIpResolver::resolve($request, ['0.0.0.0/0', '*']),
            'A wildcard trust entry must not enable attacker-controlled IPs.'
        );

        $this->assertSame(
            '10.0.0.10',
            ClientIpResolver::resolve($request, []),
            'An empty trust list must ignore forwarded headers.'
        );
    }

    public function test_ipv6_trust_entries_and_malformed_configurations_fail_closed(): void
    {
        $v6 = Request::create('/api/v1/auth/login', 'POST', server: [
            'REMOTE_ADDR'          => '2001:db8::1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.5',
        ]);

        $this->assertSame('203.0.113.5', ClientIpResolver::resolve($v6, ['2001:db8::/32']));
        $this->assertSame('2001:db8::1', ClientIpResolver::resolve($v6, ['2001:db9::/32']));
        $this->assertSame('2001:db8::1', ClientIpResolver::resolve($v6, ['10.0.0.0/8']));

        $garbage = Request::create('/api/v1/auth/login', 'POST', server: ['REMOTE_ADDR' => 'not-an-ip']);
        $this->assertSame('not-an-ip', ClientIpResolver::resolve($garbage, ['10.0.0.0/8']));
    }

    public function test_package_config_trusted_proxies_defaults_to_empty(): void
    {
        $this->assertSame([], config('authentication.security.trusted_proxies'));
    }
}