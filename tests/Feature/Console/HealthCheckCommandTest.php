<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature\Console;

use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * authentication:health must fail closed when CAPTCHA is enabled without
 * provider credentials: an enabled CAPTCHA with empty keys would otherwise
 * produce a "healthy" probe while every challenged login silently fails.
 */
class HealthCheckCommandTest extends TestCase
{
    public function test_health_passes_when_captcha_disabled(): void
    {
        config([
            'authentication.security.captcha.enabled'   => false,
            'authentication.security.captcha.site_key'  => '',
            'authentication.security.captcha.secret_key' => '',
        ]);

        $this->artisan('authentication:health', ['--detailed' => true])
            ->assertExitCode(0);
    }

    public function test_health_fails_when_captcha_enabled_without_credentials(): void
    {
        config([
            'authentication.security.captcha.enabled'   => true,
            'authentication.security.captcha.driver'    => 'turnstile',
            'authentication.security.captcha.site_key'  => '',
            'authentication.security.captcha.secret_key' => '',
        ]);

        $this->artisan('authentication:health', ['--detailed' => true])
            ->expectsOutputToContain('CAPTCHA')
            ->assertExitCode(1);
    }

    public function test_health_passes_when_captcha_enabled_with_credentials(): void
    {
        config([
            'authentication.security.captcha.enabled'   => true,
            'authentication.security.captcha.driver'    => 'turnstile',
            'authentication.security.captcha.site_key'  => 'test-site-key',
            'authentication.security.captcha.secret_key' => 'test-secret-key',
        ]);

        $this->artisan('authentication:health', ['--detailed' => true])
            ->assertExitCode(0);
    }

    public function test_health_failure_message_does_not_leak_provider_credentials(): void
    {
        config([
            'authentication.security.captcha.enabled'   => true,
            'authentication.security.captcha.driver'    => 'turnstile',
            'authentication.security.captcha.site_key'  => 'site-1x0000000000000000000000000000000',
            'authentication.security.captcha.secret_key' => '',
        ]);

        $this->artisan('authentication:health')
            ->doesntExpectOutputToContain('site-1x0000000000000000000000000000000')
            ->assertExitCode(1);
    }
}
