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
    public function test_package_routes_are_registered_under_a_configured_name_prefix(): void
    {
        $expected = 'authentication.';

        $this->assertSame($expected, RouteConfig::webRouteNamePrefix());

        $packageRoutes = collect(app('router')->getRoutes())->filter(
            fn ($route) => str_starts_with((string) $route->getName(), $expected)
        );

        $this->assertNotEmpty($packageRoutes, 'No prefixed web package routes registered.');

        foreach ($packageRoutes as $route) {
            $this->assertStringStartsWith($expected, (string) $route->getName());
        }
    }

    public function test_unprefixed_legacy_route_names_are_not_registered(): void
    {
        $legacyNames = [
            'login', 'logout', 'register',
            'password.confirm', 'password.request', 'password.reset',
            'verification.verify', 'two-factor.verify', 'passkey.login',
        ];

        foreach ($legacyNames as $name) {
            $this->assertFalse(
                Route::has($name),
                "Bare route name [{$name}] is still registered and can hijack host application redirects."
            );
        }
    }

    public function test_host_application_route_names_are_not_overwritten_by_package(): void
    {
        // Simulate a host app that already owns the global names.
        Route::get('/host/login', fn () => response('host login'))->name('login');
        Route::get('/host/password-confirm', fn () => response('host'))->name('password.confirm');

        require __DIR__ . '/../../routes/web.php';

        $this->assertStringEndsWith('/host/login', route('login'));
        $this->assertStringEndsWith('/host/password-confirm', route('password.confirm'));
        $this->assertNotSame(route('login'), route('authentication.login'));
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

        require __DIR__ . '/../../routes/web.php';

        $routes = collect(app('router')->getRoutes());

        $this->assertTrue(
            $routes->contains(fn ($route) => $route->uri() === 'account/login'
                && $route->getName() === 'authentication.login'),
            'Configured web URL prefix was not applied.'
        );

        $this->assertFalse(
            $routes->contains(fn ($route) => $route->uri() === 'account/login'
                && $route->getName() === 'login'),
            'Prefixed package URL must retain the package route namespace.'
        );
    }

    public function test_empty_route_name_prefix_fails_closed(): void
    {
        config()->set('authentication.routes.web.route_name_prefix', '');

        $this->expectException(\Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException::class);

        RouteConfig::name('login');
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
