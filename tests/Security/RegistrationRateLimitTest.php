<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\TestCase;

class RegistrationRateLimitTest extends TestCase
{
    public function test_repeated_registrations_from_one_ip_are_throttled(): void
    {
        $throttled = false;

        for ($i = 0; $i < 12; $i++) {
            $response = $this->postJson('/api/v1/auth/register', [
                'name'                  => 'Flooder',
                'email'                 => "flood-{$i}@example.com",
                'password'              => 'SecretPass123!',
                'password_confirmation' => 'SecretPass123!',
            ]);

            if ($response->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled, 'registration bucket is configured but never enforced — mass account creation possible.');
    }
}
