<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vendor\LaravelAuthentication\Services\TotpService;

class TotpServiceTest extends TestCase
{
    private TotpService $totp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp = new TotpService();
    }

    public function test_it_generates_valid_base32_secret(): void
    {
        $secret = $this->totp->generateSecret(16);
        $this->assertSame(16, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_it_calculates_and_verifies_totp_code_accurately(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP'; // Base32 for "Hello!\xDE\xAD\xBE\xEF"
        $timestamp = 1600000000;

        $code = $this->totp->calculateCode($secret, $timestamp);
        $this->assertSame(6, strlen($code));
        $this->assertTrue(ctype_digit($code));

        $this->assertTrue($this->totp->verify($secret, $code, window: 0, digits: 6, period: 30));
    }

    public function test_it_supports_clock_drift_window(): void
    {
        $secret = $this->totp->generateSecret();
        $pastTimestamp = time() - 30; // 1 step behind
        $pastCode = $this->totp->calculateCode($secret, $pastTimestamp);

        // Window = 1 allows +-1 step (30s drift)
        $this->assertTrue($this->totp->verify($secret, $pastCode, window: 1));

        // Window = 0 rejects past step
        $this->assertFalse($this->totp->verify($secret, $pastCode, window: 0));
    }

    public function test_it_formats_otpauth_url_properly(): void
    {
        $secret = 'MYSUPERSECRETKEY';
        $url = $this->totp->getOtpAuthUrl('MyApp', 'user@example.com', $secret);

        $this->assertStringStartsWith('otpauth://totp/MyApp:user%40example.com?secret=MYSUPERSECRETKEY', $url);
        $this->assertStringContainsString('algorithm=SHA1', $url);
        $this->assertStringContainsString('digits=6', $url);
    }
}
