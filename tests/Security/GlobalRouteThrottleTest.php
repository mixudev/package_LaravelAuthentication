<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\TestCase;

class GlobalRouteThrottleTest extends TestCase
{
    public function test_api_routes_are_rate_limited_globally(): void
    {
        config()->set('authentication.security.global_throttle.max_attempts', 3);
        config()->set('authentication.security.global_throttle.decay_minutes', 1);

        $statuses = [];

        for ($i = 0; $i < 6; $i++) {
            $statuses[] = $this->postJson('/api/v1/auth/login', [
                'identifier' => 'nobody@example.com',
                'password'   => 'WrongPassword123!',
            ])->status();
        }

        $this->assertContains(429, $statuses, 'Package routes have no global rate limit ceiling.');
    }

    public function test_throttle_fails_open_when_disabled(): void
    {
        config()->set('authentication.security.global_throttle.max_attempts', 0);

        $middleware = app(\Vendor\LaravelAuthentication\Http\Middleware\ThrottleAuthenticationRoutes::class);
        $request = request()->create('/health', 'GET');
        $called = false;

        $response = $middleware->handle($request, function () use (&$called): \Symfony\Component\HttpFoundation\Response {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }
}
