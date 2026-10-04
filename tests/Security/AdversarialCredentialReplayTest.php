<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Mail\OtpMail;
use Vendor\LaravelAuthentication\Services\TwoFactor\TotpService;
use Vendor\LaravelAuthentication\Services\TwoFactor\TwoFactorService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Attack-intent tests: replay credentials that an attacker has captured.
 */
final class AdversarialCredentialReplayTest extends TestCase
{
    public function test_captured_totp_code_cannot_be_redeemed_twice(): void
    {
        $user = User::create([
            'name' => 'Replay Target',
            'email' => 'replay@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        /** @var TwoFactorService $twoFactor */
        $twoFactor = app(TwoFactorService::class);
        $setup = $twoFactor->setup($user);
        $this->assertTrue($twoFactor->confirm($user, app(TotpService::class)->calculateCode($setup['secret'])));

        $capturedCode = app(TotpService::class)->calculateCode($setup['secret']);

        $this->assertTrue($twoFactor->verifyChallenge($user, $capturedCode));
        $this->assertFalse($twoFactor->verifyChallenge($user, $capturedCode));
    }

    public function test_otp_mailable_is_encrypted_when_queued(): void
    {
        $mailable = new OtpMail(
            code: '123456',
            expiryMinutes: 10,
            identifier: 'target@example.com'
        );

        $this->assertInstanceOf(ShouldBeEncrypted::class, $mailable);
    }
}
