<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\OtpServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Events\OtpGenerated;
use Vendor\LaravelAuthentication\Events\OtpVerified;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    public function test_otp_can_be_generated_and_verified_successfully(): void
    {
        Event::fake([OtpGenerated::class, OtpVerified::class]);

        $user = User::create([
            'name'     => 'Alice Smith',
            'email'    => 'alice@example.com',
            'password' => Hash::make('secret123'),
        ]);

        /** @var OtpServiceInterface $otpService */
        $otpService = app(OtpServiceInterface::class);

        $context = new AuthenticationContext(
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            channel: AuthenticationChannel::WEB
        );

        // 1. Generate OTP
        $code = $otpService->generate('alice@example.com', $context);
        $this->assertEquals(6, strlen($code));
        Event::assertDispatched(OtpGenerated::class);

        // 2. Verify OTP
        $verifiedUser = $otpService->verify('alice@example.com', $code, $context);
        $this->assertNotNull($verifiedUser);
        $this->assertEquals($user->id, $verifiedUser->getAuthIdentifier());
        Event::assertDispatched(OtpVerified::class);
    }

    public function test_otp_fails_with_invalid_code(): void
    {
        /** @var OtpServiceInterface $otpService */
        $otpService = app(OtpServiceInterface::class);

        $context = new AuthenticationContext(
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            channel: AuthenticationChannel::WEB
        );

        $otpService->generate('alice@example.com', $context);

        $this->expectException(InvalidCredentialsException::class);
        $otpService->verify('alice@example.com', '999999', $context);
    }
}
