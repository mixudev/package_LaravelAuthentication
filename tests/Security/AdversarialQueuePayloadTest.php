<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Mail\NewDeviceLoginMail;
use Vendor\LaravelAuthentication\Mail\OtpMail;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Attack-intent tests for queue serialization boundaries.
 */
final class AdversarialQueuePayloadTest extends TestCase
{
    public function test_otp_and_new_device_mailables_require_encrypted_queue_payloads(): void
    {
        $user = User::create([
            'name' => 'Queue Target',
            'email' => 'queue@example.com',
            'password' => Hash::make('unused'),
        ]);

        $otp = new OtpMail('123456', 10, 'queue@example.com');
        $device = new \Vendor\LaravelAuthentication\Models\AuthenticationDevice([
            'user_id' => $user->getAuthIdentifier(),
            'device_fingerprint' => hash('sha256', 'device'),
            'ip_address' => '198.51.100.10',
            'user_agent' => 'test',
        ]);
        $newDevice = new NewDeviceLoginMail($user, $device);

        $this->assertInstanceOf(ShouldBeEncrypted::class, $otp);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $newDevice);
    }
}
