<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class ForgottenPasswordRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'name'     => 'Reset Target',
            'username' => 'resettarget',
            'email'    => 'reset-target@example.com',
            'password' => Hash::make('SecretPass123!'),
        ]);
    }

    public function test_repeated_reset_requests_are_throttled(): void
    {
        $throttled = false;

        for ($i = 0; $i < 12; $i++) {
            $response = $this->postJson('/api/v1/auth/forgot-password', [
                'email' => 'reset-target@example.com',
            ]);

            if ($response->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled, 'forgot-password bucket is configured but never enforced — email bombing possible.');
    }
}
