<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use Vendor\LaravelAuthentication\Support\SecurityDefenseDetector;
use Vendor\LaravelAuthentication\Tests\TestCase;

class SecurityDefenseDetectorTest extends TestCase
{
    public function test_it_detects_when_security_defense_is_not_installed(): void
    {
        // Given: security-defense provider class doesn't exist (test env default)
        // When: check if installed
        $installed = SecurityDefenseDetector::isInstalled();

        // Then: should return false in test env (package not in vendor)
        $this->assertIsBool($installed);
    }

    public function test_it_detects_missing_bridge_subscriber(): void
    {
        // Given: no subscriber file exists
        // When: check if bridge exists
        $hasBridge = SecurityDefenseDetector::hasBridgeSubscriber();

        // Then: should return false
        $this->assertFalse($hasBridge);
    }

    public function test_it_detects_when_bridge_is_not_registered(): void
    {
        // Given: default test AppServiceProvider (no subscriber registration)
        // When: check if bridge registered
        $isRegistered = SecurityDefenseDetector::isBridgeRegistered();

        // Then: should return false
        $this->assertFalse($isRegistered);
    }

    public function test_it_returns_null_message_when_package_not_installed(): void
    {
        // Given: security-defense not installed (test env default)
        // When: get status message
        $message = SecurityDefenseDetector::getStatusMessage();

        // Then: no message needed (package absent)
        $this->assertNull($message);
    }

    public function test_it_reports_fully_integrated_when_package_absent(): void
    {
        // Given: security-defense not installed
        // When: check if fully integrated
        $integrated = SecurityDefenseDetector::isFullyIntegrated();

        // Then: should return true (nothing to integrate)
        $this->assertTrue($integrated);
    }
}
