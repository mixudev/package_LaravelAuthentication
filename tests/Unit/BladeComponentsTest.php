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

    protected function defineEnvironment($app): void
    {
        // The countdown copy used to be hardcoded Indonesian inside the Alpine
        // component, so this test only passed by accident. Pin the locale to
        // match the copy it asserts.
        $app->setLocale('id');
        $app['translator']->setLocale('id');
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

    /**
     * Component implementations moved into `components/forms/`,
     * `components/feedback/`, and `components/security/`, with the old
     * `components/<name>.blade.php` paths kept as `@include` wrappers.
     *
     * An `@include` wrapper does NOT automatically forward an anonymous
     * component's `$attributes` bag or `$slot`. These assertions exist to catch
     * that regression: without them a wrapper silently drops every attribute,
     * which is invisible until a page renders unstyled or a form field loses
     * its `name`.
     */
    public function test_relocated_component_alias_forwards_attributes_and_slot(): void
    {
        // Button takes attributes, class merging, and a slot.
        $button = $this->blade(
            '<x-authentication::button type="submit" variant="primary" data-test="btn">Isi Tombol</x-authentication::button>'
        );

        $button->assertSee('data-test="btn"', false);
        $button->assertSee('Isi Tombol', false);
        $button->assertSee('auth-btn-primary', false);

        // Checkbox forwards `name` (a plain attribute, not a declared prop) and slot.
        $checkbox = $this->blade(
            '<x-authentication::checkbox name="remember">Ingat saya</x-authentication::checkbox>'
        );

        $checkbox->assertSee('name="remember"', false);
        $checkbox->assertSee('Ingat saya', false);

        // Input forwards declared props and attributes.
        $input = $this->blade(
            '<x-authentication::input name="email" type="email" label="Email" data-test="in" />'
        );

        $input->assertSee('name="email"', false);
        $input->assertSee('data-test="in"', false);
        $input->assertSee('Email', false);
    }

    /**
     * `alert` is declared-prop only, so the wrapper must still forward the
     * props the package pages pass it.
     */
    public function test_relocated_alert_alias_receives_declared_props(): void
    {
        $view = $this->blade(
            '<x-authentication::alert type="error" :message="$m" />',
            ['m' => 'Kredensial tidak valid']
        );

        $view->assertSee('auth-alert-error', false);
        $view->assertSee('Kredensial tidak valid', false);
    }
}
