<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vendor\LaravelAuthentication\Services\DeviceDetector;

class DeviceDetectorTest extends TestCase
{
    private DeviceDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new DeviceDetector();
    }

    public function test_it_detects_windows_and_chrome(): void
    {
        $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $res = $this->detector->detect($agent, '192.168.1.50', 'user_123');

        $this->assertSame('Windows 10/11', $res['platform']);
        $this->assertSame('Google Chrome', $res['browser']);
        $this->assertSame('Google Chrome on Windows 10/11', $res['device_name']);
        $this->assertNotEmpty($res['fingerprint']);
    }

    public function test_it_detects_ios_and_safari(): void
    {
        $agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $res = $this->detector->detect($agent, '10.0.0.1', 'user_456');

        $this->assertSame('iOS', $res['platform']);
        $this->assertSame('Apple Safari', $res['browser']);
        $this->assertSame('Apple Safari on iOS', $res['device_name']);
    }
}
