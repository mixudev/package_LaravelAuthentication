<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Support\RouteConfig;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Locks the route-layer security invariants found during the route architecture audit.
 *
 * Each test here corresponds to one audit finding so a future change that reopens
 * it fails loudly instead of silently regressing.
 */
class RouteHardeningTest extends TestCase
{
    public function test_package_routes_use_bare_names_by_default(): void
    {
        config()->set('authentication.routes.web.route_name_prefix', '');

        $this->assertSame('', RouteConfig::webRouteNamePrefix());
        $this->assertSame('login', RouteConfig::name('login'));
        $this->assertSame('logout', RouteConfig::name('logout'));
        $this->assertSame('register', RouteConfig::name('register'));
        $this->assertSame('password.reset', RouteConfig::name('password.reset'));
    }

    public function test_package_routes_respect_configured_prefix(): void
    {
        // Test ini memerlukan isolasi penuh karena routes sudah di-load di setUp().
        // Untuk sekarang, kita verify prefix logic melalui RouteConfig helper.
        config()->set('authentication.routes.web.route_name_prefix', 'auth');

        $expected = 'auth.';
        $this->assertSame($expected, RouteConfig::webRouteNamePrefix());
        $this->assertSame('auth.login', RouteConfig::name('login'));
        $this->assertSame('auth.logout', RouteConfig::name('logout'));
    }

    public function test_empty_prefix_maps_logical_names_to_laravel_names(): void
    {
        config()->set('authentication.routes.web.route_name_prefix', '');

        foreach (['login', 'logout', 'register', 'password.confirm', 'password.request', 'password.reset', 'verification.verify'] as $name) {
            $this->assertSame($name, RouteConfig::name($name));
        }
    }

    public function test_host_application_route_names_can_coexist_with_prefixed_package(): void
    {
        // Host registers bare names first
        Route::get('/host/login', fn () => response('host login'))->name('login');
        Route::get('/host/password-confirm', fn () => response('host'))->name('password.confirm');

        // Package uses a prefix to avoid collision
        config()->set('authentication.routes.web.route_name_prefix', 'auth');
        require __DIR__ . '/../../routes/web.php';

        // Both coexist
        $this->assertStringEndsWith('/host/login', route('login'));
        $this->assertStringEndsWith('/host/password-confirm', route('password.confirm'));
        $this->assertTrue(Route::has('auth.login'));
        $this->assertNotSame(route('login'), route('auth.login'));
    }

    public function test_web_authn_challenge_endpoints_are_post_only(): void
    {
        $routes = $this->routesByUri('auth/passkey/login-options');
        $this->assertNotEmpty($routes, 'Passkey login-options route missing.');

        foreach ($routes as $route) {
            $this->assertSame(['POST'], $route->methods(), 'Challenge creation must not answer GET.');
        }

        $routes = $this->routesByUri('auth/passkey/register-options');
        $this->assertNotEmpty($routes, 'Passkey register-options route missing.');

        foreach ($routes as $route) {
            $this->assertSame(['POST'], $route->methods(), 'Challenge creation must not answer GET.');
        }
    }

    public function test_web_url_prefix_from_config_is_applied_to_registration(): void
    {
        config()->set('authentication.routes.web.prefix', 'account');
        config()->set('authentication.routes.web.route_name_prefix', '');

        require __DIR__ . '/../../routes/web.php';

        $routes = collect(app('router')->getRoutes());

        $this->assertTrue(
            $routes->contains(fn ($route) => $route->uri() === 'account/login'
                && $route->getName() === 'login'),
            'Configured web URL prefix was not applied.'
        );
    }

    public function test_empty_route_name_prefix_produces_bare_names(): void
    {
        config()->set('authentication.routes.web.route_name_prefix', '');

        $this->assertSame('', RouteConfig::webRouteNamePrefix());
        $this->assertSame('login', RouteConfig::name('login'));
        $this->assertSame('password.reset', RouteConfig::name('password.reset'));
    }

    public function test_api_auth_middleware_cannot_be_emptied_by_host_config(): void
    {
        config()->set('authentication.routes.api.auth_middleware', []);

        $this->expectException(\Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException::class);

        RouteConfig::apiAuthMiddleware();
    }

    public function test_api_auth_middleware_rejects_non_authenticating_middleware(): void
    {
        config()->set('authentication.routes.api.auth_middleware', ['throttle', 'bindings']);

        $this->expectException(\Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException::class);

        RouteConfig::apiAuthMiddleware();
    }

    public function test_api_authenticated_routes_carry_a_validated_auth_middleware(): void
    {
        $sensitive = [
            'api/v1/auth/logout',
            'api/v1/auth/sessions',
            'api/v1/auth/passkey/{id}',
            'api/v1/auth/confirm-password',
        ];

        $routes = collect(app('router')->getRoutes());

        foreach ($sensitive as $uri) {
            $matched = $routes->filter(fn ($route) => $route->uri() === $uri);

            $this->assertNotEmpty($matched, "Sensitive API route [{$uri}] is not registered.");

            foreach ($matched as $route) {
                $stack = $route->gatherMiddleware();

                $this->assertNotEmpty($stack, "Sensitive API route [{$uri}] has no middleware at all.");

                $authenticating = array_filter($stack, static fn ($middleware) => RouteConfig::isAuthMiddleware($middleware));

                $this->assertNotEmpty(
                    $authenticating,
                    "Sensitive API route [{$uri}] lost its auth middleware: " . implode(', ', $stack)
                );
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Illuminate\Routing\Route>
     */
    private function routesByUri(string $uri): \Illuminate\Support\Collection
    {
        return collect(app('router')->getRoutes())
            ->filter(fn ($route) => $route->uri() === $uri)
            ->values();
    }
}
