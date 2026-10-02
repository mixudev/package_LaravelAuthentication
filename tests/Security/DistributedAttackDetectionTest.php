<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Vendor\LaravelAuthentication\Services\Security\DistributedAttackDetector;
use Vendor\LaravelAuthentication\Tests\TestCase;

final class DistributedAttackDetectionTest extends TestCase
{
    public function test_many_identifiers_from_one_ip_raise_risk_without_hard_deny(): void
    {
        $detector = $this->detector();

        for ($i = 0; $i < 100; $i++) {
            $risk = $detector->assess('10.0.0.1', 'user-' . $i);
        }

        self::assertGreaterThanOrEqual(0.8, $risk->score);
        self::assertLessThanOrEqual(1.0, $risk->score);
        self::assertSame(100, $risk->distinctIdentifiers);
        self::assertFalse($risk->hardDeny);
    }

    public function test_many_ips_for_one_identifier_raise_risk(): void
    {
        $detector = $this->detector();

        for ($i = 0; $i < 100; $i++) {
            $risk = $detector->assess('10.0.1.' . $i, 'victim@example.test');
        }

        self::assertGreaterThanOrEqual(0.8, $risk->score);
        self::assertSame(100, $risk->distinctIps);
    }

    public function test_rotating_signals_both_dimensions(): void
    {
        $detector = $this->detector();

        for ($i = 0; $i < 100; $i++) {
            $detector->assess('10.1.0.' . ($i % 50), 'user-' . ($i % 40));
        }

        $risk = $detector->assess('10.1.0.1', 'user-5');
        self::assertGreaterThan(0.0, $risk->score);
        self::assertGreaterThan(1, $risk->distinctIdentifiers);
        self::assertGreaterThan(1, $risk->distinctIps);
    }

    public function test_score_bounded_at_one(): void
    {
        $detector = $this->detector();

        for ($i = 0; $i < 500; $i++) {
            $risk = $detector->assess('10.2.0.1', 'user-' . $i);
        }

        self::assertSame(1.0, $risk->score);
    }

    public function test_same_identifier_and_ip_does_not_inflate_score(): void
    {
        $detector = $this->detector();

        for ($i = 0; $i < 50; $i++) {
            $risk = $detector->assess('10.3.0.1', 'repeat@example.test');
        }

        self::assertLessThan(0.1, $risk->score);
        self::assertSame(1, $risk->distinctIdentifiers);
        self::assertSame(1, $risk->distinctIps);
    }

    public function test_low_risk_does_not_block(): void
    {
        $detector = $this->detector();

        $risk = $detector->assess('10.4.0.1', 'single@example.test');

        self::assertLessThan(0.02, $risk->score);
        self::assertFalse($risk->hardDeny);
    }

    private function detector(): DistributedAttackDetector
    {
        $repository = new Repository(new ArrayStore());
        $limiter = new RateLimiter($repository);

        return new DistributedAttackDetector($limiter, $repository, 300, 100, 100);
    }
}
