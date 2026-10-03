<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\TestCase;

class SocialAuthRateLimitTest extends TestCase
{
    public function test_oauth_redirect_endpoint_is_rate_limited(): void
    {
        config()->set('authentication.features.social.providers', [
            'gitlab' => [
                'enabled'       => true,
                'client_id'     => 'x',
                'client_secret' => 'y',
                'redirect'      => 'http://localhost/cb',
            ],
        ]);

        $throttled = false;

        for ($i = 0; $i < 40; $i++) {
            $response = $this->get('/auth/gitlab/redirect');

            if ($response->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled, 'OAuth redirect endpoint has no rate limit — provider abuse / circuit-breaker exhaustion.');
    }
}
