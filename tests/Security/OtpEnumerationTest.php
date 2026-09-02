<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Events\OtpGenerated;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class OtpEnumerationTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Real User',
            'username' => 'realuser',
            'email'    => 'real@example.com',
            'password' => Hash::make('Password123!'),
        ]);
    }

    public function test_send_otp_returns_identical_generic_message_for_existing_and_nonexistent_user(): void
    {
        // 1. Registered user
        $responseExisting = $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'real@example.com',
        ]);
        $responseExisting->assertStatus(200);
        $responseExisting->assertJson([
            'status'  => 'success',
            'message' => 'If an account exists with that identifier, a verification code has been dispatched.',
        ]);

        // 2. Non-existent user
        $responseNonexistent = $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'nonexistent_user_999@example.com',
        ]);
        $responseNonexistent->assertStatus(200);
        $responseNonexistent->assertJson([
            'status'  => 'success',
            'message' => 'If an account exists with that identifier, a verification code has been dispatched.',
        ]);
    }

    public function test_does_not_dispatch_otp_event_or_email_for_nonexistent_account(): void
    {
        Event::fake([OtpGenerated::class]);

        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'attacker_target_nobody@example.com',
        ]);

        Event::assertNotDispatched(OtpGenerated::class);
    }

    public function test_verify_otp_returns_identical_generic_error_for_wrong_code_and_nonexistent_user(): void
    {
        $responseWrongCode = $this->postJson('/api/v1/auth/otp/verify', [
            'identifier' => 'real@example.com',
            'code'       => '999999',
        ]);
        $responseWrongCode->assertStatus(401);
        $responseWrongCode->assertJson([
            'status'  => 'error',
            'message' => 'The provided OTP code is incorrect or has expired.',
        ]);

        $responseNonexistent = $this->postJson('/api/v1/auth/otp/verify', [
            'identifier' => 'nonexistent@example.com',
            'code'       => '999999',
        ]);
        $responseNonexistent->assertStatus(401);
        $responseNonexistent->assertJson([
            'status'  => 'error',
            'message' => 'The provided OTP code is incorrect or has expired.',
        ]);
    }
}
