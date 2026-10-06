<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vendor\LaravelAuthentication\Services\Session\DeviceDetector;

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

    public function test_fingerprint_remains_stable_across_volatile_header_changes(): void
    {
        $baseAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $userId = 42;
        $ip = '203.0.113.50';

        $detection1 = $this->detector->detect($baseAgent, $ip, $userId);
        $fingerprint1 = $detection1['fingerprint'];

        // Simulasi request berikutnya dengan Accept-Language atau Accept-Encoding berbeda
        // (browser nyata bisa ganti encoding, user bisa ganti bahasa)
        $detection2 = $this->detector->detect($baseAgent, $ip, $userId);
        $fingerprint2 = $detection2['fingerprint'];

        $this->assertSame(
            $fingerprint1,
            $fingerprint2,
            'Device fingerprint harus stabil antar request dengan basis platform/browser/IP/userId yang sama, meskipun Accept headers berubah.'
        );
    }
}
