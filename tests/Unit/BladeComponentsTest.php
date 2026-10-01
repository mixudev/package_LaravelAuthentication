<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Vendor\LaravelAuthentication\Providers\AuthenticationServiceProvider;

class BladeComponentsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AuthenticationServiceProvider::class,
        ];
    }

    public function test_countdown_alert_renders_with_seconds(): void
    {
        $view = $this->blade(
            '<x-authentication::countdown-alert type="error" :retryAfter="45" submitButton="#submit-btn" />'
        );

        $view->assertSee('Terlalu banyak percobaan', false);
        $view->assertSee('seconds: 45', false);
        $view->assertSee('total: 45', false);
        $view->assertSee('auth-alert-error', false);
    }

    public function test_countdown_alert_auto_detects_seconds_from_message(): void
    {
        $view = $this->blade(
            '<x-authentication::countdown-alert type="error" message="Too many login attempts. Please try again in 60 seconds." />'
        );

        $view->assertSee('seconds: 60', false);
        $view->assertSee('total: 60', false);
    }

    public function test_countdown_alert_falls_back_to_normal_alert_without_seconds(): void
    {
        $view = $this->blade(
            '<x-authentication::countdown-alert type="error" message="Invalid credentials." />'
        );

        $view->assertSee('Invalid credentials.', false);
        $view->assertDontSee('x-data="{', false);
    }

    public function test_button_component_has_loading_state_support(): void
    {
        $view = $this->blade(
            '<x-authentication::button type="submit" variant="primary">Masuk</x-authentication::button>'
        );

        $view->assertSee('submitting', false);
        $view->assertSee('Masuk', false);
        $view->assertSee('animate-spin', false);
    }
}
