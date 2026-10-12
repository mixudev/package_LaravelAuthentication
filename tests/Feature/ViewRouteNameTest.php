<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Route-name contract for the package views.
 *
 * Routes are registered through RouteConfig::name(), so the actual name is
 * `login`, `passkey.register.options`, ... by default and only carries the
 * `authentication.` namespace when routes.web.route_name_prefix is configured.
 * Views that hardcode `route('authentication.*')` therefore throw
 * RouteNotFoundException under the shipped default config (GET /auth/sessions
 * returned 500: Route [authentication.passkey.register.options] not defined).
 */
class ViewRouteNameTest extends TestCase
{
    private User $user;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Shipped default: bare Laravel-style route names (config/authentication.php).
        $app['config']->set('authentication.routes.web.route_name_prefix', '');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Route User',
            'username' => 'routeuser',
            'email'    => 'routeuser@example.com',
            'password' => Hash::make('PasswordA123!'),
        ]);
    }

    public function test_sessions_page_renders_with_default_empty_route_name_prefix(): void
    {
        $this->assertSame('', config('authentication.routes.web.route_name_prefix'));

        $response = $this->actingAs($this->user)->get('/auth/sessions');

        $response->assertOk();
        $response->assertSee('/auth/passkey/register-options', false);
    }

    public function test_no_view_hardcodes_the_authentication_route_namespace(): void
    {
        $directory = dirname(__DIR__, 2) . '/resources/views';
        $files = $this->viewFiles($directory);

        $this->assertGreaterThan(0, count($files), 'No Blade views found to scan.');

        $offenders = [];
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/(route|Route::has)\(\s*[\'"]authentication\./', $source)) {
                $offenders[] = substr($file, strlen($directory) + 1);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Views must resolve route names through RouteConfig::name() instead of hardcoding '
            . 'the authentication namespace: ' . implode(', ', $offenders)
        );
    }

    /**
     * Recursive collection — a non-recursive glob would scan zero files and
     * report this guard green over nothing.
     *
     * @return array<int, string>
     */
    private function viewFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && str_ends_with($item->getFilename(), '.blade.php')) {
                $files[] = $item->getPathname();
            }
        }

        return $files;
    }
}
