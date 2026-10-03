<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\TestCase;

class OtpFeatureRateLimitTest extends TestCase
{
    public function test_otp_requests_across_rotating_identifiers_are_throttled(): void
    {
        $throttled = false;

        for ($i = 0; $i < 15; $i++) {
            $response = $this->postJson('/api/v1/auth/otp/send', [
                'identifier' => "rotating-{$i}@example.com",
            ]);

            if ($response->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue(
            $throttled,
            'otp_request bucket never fires — per-identifier cooldown is defeated by rotating identifiers.'
        );
    }

    public function test_otp_verify_attempts_are_capped_by_feature_bucket(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['identifier' => 'victim@example.com']);

        $throttled = false;

        // otp_verify has max_attempts 5 per composite by config (as updated).
        // Loop beyond the bucket to force a 429 from the feature bucket when exceeded.
        for ($i = 0; $i < 60; $i++) {
            $response = $this->postJson('/api/v1/auth/otp/verify', [
                'identifier' => 'victim@example.com',
                'code'       => '000000',
            ]);

            if ($response->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled, 'otp_verify bucket never fires.');
    }
}
