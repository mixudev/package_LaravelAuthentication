<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit\Support;

use Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Tests\TestCase;

final class AuthenticationConfigRateLimitTest extends TestCase
{
    public function test_abuse_policy_has_secure_default_dimensions(): void
    {
        /** @var AuthenticationConfig $config */
        $config = app(AuthenticationConfig::class);

        $policy = $config->getAbusePolicyConfig();

        $this->assertTrue($policy['enabled']);
        $this->assertArrayHasKey('account', $policy['dimensions']);
        $this->assertArrayHasKey('network', $policy['dimensions']);
    }

    public function test_valid_dimensions_are_normalized(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => true,
            'dimensions' => [
                'account' => ['max_attempts' => 20, 'decay_minutes' => 5],
                'network' => ['max_attempts' => 100, 'decay_minutes' => 1],
            ],
        ]);

        /** @var AuthenticationConfig $config */
        $config = app(AuthenticationConfig::class);
        $policy = $config->getAbusePolicyConfig();

        $this->assertTrue($policy['enabled']);
        $this->assertSame(20, $policy['dimensions']['account']['max_attempts']);
        $this->assertSame(5, $policy['dimensions']['account']['decay_minutes']);
        $this->assertSame(100, $policy['dimensions']['network']['max_attempts']);
    }

    public function test_unknown_dimension_fails_closed(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => true,
            'dimensions' => [
                'browser_fingerprint' => ['max_attempts' => 10, 'decay_minutes' => 1],
            ],
        ]);

        $this->expectException(AuthenticationConfigurationException::class);
        app(AuthenticationConfig::class)->getAbusePolicyConfig();
    }

    public function test_non_positive_limit_fails_closed(): void
    {
        config()->set('authentication.security.abuse_policy', [
            'enabled' => true,
            'dimensions' => [
                'account' => ['max_attempts' => 0, 'decay_minutes' => 1],
            ],
        ]);

        $this->expectException(AuthenticationConfigurationException::class);
        app(AuthenticationConfig::class)->getAbusePolicyConfig();
    }
}
