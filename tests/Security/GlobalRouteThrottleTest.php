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

    public function test_web_routes_are_rate_limited_globally(): void
    {
        config()->set('authentication.security.global_throttle.max_attempts', 3);
        config()->set('authentication.security.global_throttle.decay_minutes', 1);

        $statuses = [];

        for ($i = 0; $i < 6; $i++) {
            $statuses[] = $this->get('/login')->status();
        }

        $this->assertContains(429, $statuses, 'Web package routes have no global rate limit ceiling.');
        $this->assertSame(200, $statuses[0], 'Throttle must not block the first request.');
    }

    public function test_throttle_middleware_is_attached_to_both_route_stacks(): void
    {
        $web = $this->get('/login')->status();
        $this->assertSame(200, $web, 'Web login route must resolve for the middleware assertions below.');

        $webStack = collect(app('router')->getRoutes())
            ->first(fn ($route) => $route->uri() === 'login')
            ?->gatherMiddleware() ?? [];

        $apiStack = collect(app('router')->getRoutes())
            ->first(fn ($route) => str_starts_with($route->uri(), 'api/v1/auth/'))
            ?->gatherMiddleware() ?? [];

        $this->assertContains('authentication.throttle', $webStack, 'Web routes lost the global throttle.');
        $this->assertContains('authentication.throttle', $apiStack, 'API routes lost the global throttle.');
    }

    public function test_throttle_survives_host_config_that_drops_the_middleware(): void
    {
        // Host publishes a config that forgot 'authentication.throttle'.
        config()->set('authentication.routes.web.middleware', ['web']);
        config()->set('authentication.routes.api.middleware', ['api']);

        // Re-run the package route registration the way the provider does.
        require __DIR__ . '/../../routes/web.php';
        require __DIR__ . '/../../routes/api.php';

        $routes = collect(app('router')->getRoutes());

        foreach (['login', 'api/v1/auth/login'] as $uri) {
            $matched = $routes->filter(fn ($route) => $route->uri() === $uri);

            $this->assertNotEmpty($matched, "Route [{$uri}] was not registered.");

            foreach ($matched as $route) {
                $this->assertContains(
                    'authentication.throttle',
                    $route->gatherMiddleware(),
                    "Route [{$uri}] lost the global throttle after a host config edit."
                );
            }
        }
    }

    public function test_negative_global_throttle_fails_closed(): void
    {
        config()->set('authentication.security.global_throttle.max_attempts', -1);

        $middleware = app(\Vendor\LaravelAuthentication\Http\Middleware\ThrottleAuthenticationRoutes::class);
        $called = false;

        try {
            $middleware->handle(request()->create('/login', 'GET'), function () use (&$called) {
                $called = true;

                return response('ok');
            });
        } catch (\Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException) {
            $this->assertFalse($called, 'A corrupted throttle limit must not reach the route.');

            return;
        }

        $this->fail('A negative global_throttle.max_attempts must raise a configuration exception.');
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
