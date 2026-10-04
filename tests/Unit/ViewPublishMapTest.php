<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use Vendor\LaravelAuthentication\Providers\AuthenticationServiceProvider;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * `php artisan vendor:publish --tag=authentication-views` copies the whole
 * `resources/views` tree into `resources/views/vendor/authentication/`.
 *
 * The tree was reorganised into `pages/<domain>/` and
 * `components/{feedback,forms,security}/`. If the publish map were built from a
 * hardcoded file list, or stopped recursing, the reorganized files would be
 * missing from a host's published copy while the tests (which load views
 * straight from the package) still passed. That is the exact shape of a bug
 * that only appears after `vendor:publish` on a real host.
 */
class ViewPublishMapTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AuthenticationServiceProvider::class];
    }

    public function test_publish_map_contains_every_reorganized_view(): void
    {
        $provider = new AuthenticationServiceProvider($this->app);

        $map = $this->publishMap($provider);

        $expected = [
            'pages/auth/login.blade.php',
            'pages/auth/register.blade.php',
            'pages/auth/confirm-password.blade.php',
            'pages/password/forgot-password.blade.php',
            'pages/password/reset-password.blade.php',
            'pages/otp/request.blade.php',
            'pages/otp/verify.blade.php',
            'pages/sessions/index.blade.php',
            'pages/two-factor/challenge.blade.php',
            'pages/two-factor/setup.blade.php',
            'pages/system/setup-warning.blade.php',
            'components/layouts/auth.blade.php',
            'components/layouts/card.blade.php',
            'components/layouts/split.blade.php',
            'components/feedback/alert.blade.php',
            'components/feedback/countdown-alert.blade.php',
            'components/forms/input.blade.php',
            'components/forms/button.blade.php',
            'components/forms/checkbox.blade.php',
            'components/forms/otp-input.blade.php',
            'components/forms/segmented-code-input.blade.php',
            'components/security/active-sessions.blade.php',
            'components/security/passkey-button.blade.php',
            'emails/otp.blade.php',
            'emails/new-device.blade.php',
        ];

        $missing = [];

        foreach ($expected as $relative) {
            if (! $this->hasDestination($map, $relative)) {
                $missing[] = $relative;
            }
        }

        $this->assertSame([], $missing, 'Reorganized views are missing from the publish map.');
    }

    /**
     * Every file that exists in `resources/views` must be publishable. Catches
     * a view added later that the map silently skips.
     */
    public function test_publish_map_covers_every_file_on_disk(): void
    {
        $map = $this->publishMap(new AuthenticationServiceProvider($this->app));

        $root = realpath(dirname(__DIR__, 2) . '/resources/views');

        $this->assertIsString($root);

        $missing = [];

        foreach ($this->files($root) as $relative) {
            if (! $this->hasDestination($map, $relative)) {
                $missing[] = $relative;
            }
        }

        $this->assertSame([], $missing, 'Some view files are not publishable.');
    }

    public function test_every_publish_entry_targets_the_vendor_authentication_directory(): void
    {
        $map = $this->publishMap(new AuthenticationServiceProvider($this->app));

        foreach ($map as $destination) {
            $this->assertStringContainsString(
                'views/vendor/authentication/',
                str_replace('\\', '/', $destination),
                "Publish target [{$destination}] escaped the vendor authentication directory."
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function publishMap(AuthenticationServiceProvider $provider): array
    {
        $method = new \ReflectionMethod($provider, 'buildPublishMap');
        $method->setAccessible(true);

        /** @var array<string, string> $map */
        $map = $method->invoke(
            $provider,
            dirname(__DIR__, 2) . '/resources/views',
            resource_path('views/vendor/authentication')
        );

        return $map;
    }

    /**
     * @param array<string, string> $map
     */
    private function hasDestination(array $map, string $relative): bool
    {
        $needle = str_replace('\\', '/', $relative);

        foreach ($map as $destination) {
            $normalized = str_replace('\\', '/', $destination);

            if (str_ends_with($normalized, '/views/vendor/authentication/' . $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function files(string $root): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $files[] = ltrim(
                str_replace('\\', '/', substr($file->getPathname(), strlen($root))),
                '/'
            );
        }

        sort($files);

        return $files;
    }
}