<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Same view contract as ViewRouteNameTest, but with the namespaced route-name
 * prefix a host can configure (`routes.web.route_name_prefix = 'authentication.'`).
 *
 * Both shapes must render: views resolve names through RouteConfig::name(),
 * never through a literal namespace.
 */
class ViewRouteNamePrefixedTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Route User Prefix',
            'username' => 'routeprefix',
            'email'    => 'routeprefix@example.com',
            'password' => Hash::make('PasswordA123!'),
        ]);
    }

    public function test_sessions_page_renders_with_configured_route_name_prefix(): void
    {
        $this->assertSame(
            'authentication.',
            config('authentication.routes.web.route_name_prefix')
        );

        $response = $this->actingAs($this->user)->get('/auth/sessions');

        $response->assertOk();
        $response->assertSee('/auth/passkey/register-options', false);
    }
}
