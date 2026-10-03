<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\TestCase;

class TwoFactorSetupShowRateLimitTest extends TestCase
{
    public function test_two_factor_setup_show_is_rate_limited(): void
    {
        $user = \Vendor\LaravelAuthentication\Tests\Fixtures\User::create([
            'name'     => 'Tfa User',
            'username' => 'tfauser',
            'email'    => 'tfauser@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('SecretPass123!'),
        ]);

        $this->actingAs($user);

        $throttled = false;
        for ($i = 0; $i < 40; $i++) {
            $response = $this->get('/auth/two-factor/setup');

            if ($response->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled, 'TwoFactorSetupController::show has no rate limit — secret/QR flood.');
    }
}
