<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class PasskeyRegisterRateLimitTest extends TestCase
{
    public function test_passkey_register_options_is_rate_limited(): void
    {
        $user = User::create([
            'name'     => 'Pk User',
            'username' => 'pkuser',
            'email'    => 'pkuser@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('SecretPass123!'),
        ]);

        $this->actingAs($user);

        $throttled = false;
        for ($i = 0; $i < 30; $i++) {
            $response = $this->postJson('/api/v1/auth/passkey/register-options');

            if ($response->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled, 'Passkey registerOptions not rate limited.');
    }
}
