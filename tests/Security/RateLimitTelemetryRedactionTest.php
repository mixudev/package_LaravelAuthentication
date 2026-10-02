<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use PHPUnit\Framework\Attributes\Test;
use Vendor\LaravelAuthentication\Tests\TestCase;
use Vendor\LaravelAuthentication\Events\RateLimitExceeded;
use Vendor\LaravelAuthentication\Events\DistributedAttackDetected;
use Vendor\LaravelAuthentication\Events\ChallengeIssued;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;

class RateLimitTelemetryRedactionTest extends TestCase
{
    #[Test]
    public function rate_limit_exceeded_event_never_contains_raw_identifier(): void
    {
        $context = new AuthenticationContext(
            ipAddress: '192.168.1.100',
            userAgent: 'Test Browser',
            channel: AuthenticationChannel::WEB
        );

        $event = new RateLimitExceeded(
            identifier: 'user@example.com',
            context: $context,
            reasonCode: 'composite_limit_exceeded',
            retryAfterSeconds: 60
        );

        $serialized = serialize($event);
        
        $this->assertStringNotContainsString('user@example.com', $serialized);
        $this->assertStringNotContainsString('192.168.1.100', $serialized);
    }

    #[Test]
    public function rate_limit_exceeded_contains_hashed_identifier(): void
    {
        $context = new AuthenticationContext(
            ipAddress: '10.0.0.50',
            userAgent: 'Test',
            channel: AuthenticationChannel::API
        );

        $event = new RateLimitExceeded(
            identifier: 'admin@test.com',
            context: $context,
            reasonCode: 'per_user_limit',
            retryAfterSeconds: 120
        );

        $this->assertNotEmpty($event->identifierHash);
        $this->assertSame(64, strlen($event->identifierHash)); // SHA-256 hex
        $this->assertSame('per_user_limit', $event->reasonCode);
    }

    #[Test]
    public function distributed_attack_event_uses_network_buckets(): void
    {
        $context = new AuthenticationContext(
            ipAddress: '203.45.67.89',
            userAgent: 'Attack Bot',
            channel: AuthenticationChannel::WEB
        );

        $event = new DistributedAttackDetected(
            affectedIdentifiers: ['user1@test.com', 'user2@test.com', 'user3@test.com'],
            context: $context,
            reasonCode: 'velocity_spike',
            thresholdExceeded: 50
        );

        $serialized = serialize($event);

        $this->assertStringNotContainsString('203.45.67.89', $serialized);
        $this->assertStringNotContainsString('user1@test.com', $serialized);
        $this->assertStringNotContainsString('user2@test.com', $serialized);
        
        $this->assertNotEmpty($event->networkBucket);
        $this->assertSame(3, $event->affectedCount);
        $this->assertSame('velocity_spike', $event->reasonCode);
    }

    #[Test]
    public function challenge_issued_event_contains_no_secrets(): void
    {
        $context = new AuthenticationContext(
            ipAddress: '172.16.0.10',
            userAgent: 'Suspicious',
            channel: AuthenticationChannel::WEB
        );

        $event = new ChallengeIssued(
            identifier: 'suspect@example.com',
            context: $context,
            challengeType: 'captcha',
            reasonCode: 'rate_limit_warning'
        );

        $serialized = serialize($event);

        $this->assertStringNotContainsString('suspect@example.com', $serialized);
        $this->assertStringNotContainsString('172.16.0.10', $serialized);
        
        $this->assertNotEmpty($event->identifierHash);
        $this->assertSame('captcha', $event->challengeType);
        $this->assertSame('rate_limit_warning', $event->reasonCode);
    }

    #[Test]
    public function events_contain_only_reason_codes_not_sensitive_details(): void
    {
        $context = new AuthenticationContext(
            ipAddress: '192.168.1.1',
            userAgent: 'Test',
            channel: AuthenticationChannel::WEB
        );

        $rateLimitEvent = new RateLimitExceeded(
            identifier: 'test@example.com',
            context: $context,
            reasonCode: 'composite_limit_exceeded',
            retryAfterSeconds: 30
        );

        $this->assertObjectHasProperty('reasonCode', $rateLimitEvent);
        $this->assertObjectNotHasProperty('rawIdentifier', $rateLimitEvent);
        $this->assertObjectNotHasProperty('rawIpAddress', $rateLimitEvent);
    }

    #[Test]
    public function network_bucket_computed_from_cidr_block(): void
    {
        $context1 = new AuthenticationContext(
            ipAddress: '192.168.1.50',
            userAgent: 'Bot1',
            channel: AuthenticationChannel::WEB
        );

        $context2 = new AuthenticationContext(
            ipAddress: '192.168.1.100',
            userAgent: 'Bot2',
            channel: AuthenticationChannel::WEB
        );

        $event1 = new DistributedAttackDetected(
            affectedIdentifiers: ['a@test.com'],
            context: $context1,
            reasonCode: 'velocity_spike',
            thresholdExceeded: 20
        );

        $event2 = new DistributedAttackDetected(
            affectedIdentifiers: ['b@test.com'],
            context: $context2,
            reasonCode: 'velocity_spike',
            thresholdExceeded: 20
        );

        // Same /24 network should produce same bucket
        $this->assertSame($event1->networkBucket, $event2->networkBucket);
    }
}
