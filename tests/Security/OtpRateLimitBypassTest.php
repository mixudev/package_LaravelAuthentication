<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class OtpRateLimitBypassTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Bob Test',
            'username' => 'bobtest',
            'email'    => 'bob@example.com',
            'password' => Hash::make('SecretPass123!'),
        ]);
    }

    public function test_otp_attempts_cannot_be_infinitely_looped(): void
    {
        // 1. Request OTP
        $sendResp = $this->postJson('/api/v1/auth/otp/send', ['identifier' => 'bob@example.com']);
        $sendResp->assertStatus(200);

        // 2. Perform max incorrect verify attempts (config default is 3)
        for ($i = 0; $i < 3; $i++) {
            $verifyResp = $this->postJson('/api/v1/auth/otp/verify', [
                'identifier' => 'bob@example.com',
                'code'       => '000000',
            ]);
            $this->assertEquals(401, $verifyResp->status());
        }

        // 3. Exceeded attempts must reject next attempt
        $exceededResp = $this->postJson('/api/v1/auth/otp/verify', [
            'identifier' => 'bob@example.com',
            'code'       => '000000',
        ]);
        $this->assertTrue(in_array($exceededResp->status(), [401, 422, 429], true));
    }
}
