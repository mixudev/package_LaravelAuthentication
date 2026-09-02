<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Services\Session\DeviceTrustService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class DeviceTrustSecurityTest extends TestCase
{
    private User $user;
    private DeviceTrustService $deviceTrustService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Edward Trust',
            'username' => 'edward',
            'email'    => 'edward@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $this->deviceTrustService = app(DeviceTrustService::class);
        config(['authentication.features.two_factor.trust_device.enabled' => true]);
    }

    public function test_device_trust_cookie_has_strict_security_attributes(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'REMOTE_ADDR'     => '192.168.1.100',
        ]);

        $cookie = $this->deviceTrustService->createTrustCookie($this->user, $request);

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEquals('strict', $cookie->getSameSite());
        $this->assertNotEmpty($cookie->getValue());
    }

    public function test_tampered_or_unregistered_cookie_token_fails_trust_verification(): void
    {
        $request = Request::create('/test', 'GET', [], [
            'auth_trusted_device' => 'forged_tampered_token_value',
        ], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'REMOTE_ADDR'     => '192.168.1.100',
        ]);

        $isTrusted = $this->deviceTrustService->isTrusted($this->user, $request);
        $this->assertFalse($isTrusted);
    }
}
