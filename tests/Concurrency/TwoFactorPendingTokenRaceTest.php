<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Concurrency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Vendor\LaravelAuthentication\Support\TwoFactorPendingToken;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * H-03: 2FA Pending Token Replay Race Condition
 *
 * Concurrent API 2FA verification requests can both resolve the same pending token
 * and authenticate because resolve() uses get() and consume() happens after auth.
 *
 * Attack: Issue pending token → 2 concurrent verify requests with valid TOTP →
 * both resolve() succeed before either consume() → both authenticate.
 */
class TwoFactorPendingTokenRaceTest extends TestCase
{
    public function test_concurrent_resolve_only_one_succeeds(): void
    {
        $service = app(TwoFactorPendingToken::class);

        $userId = 123;
        $token = $service->issue($userId);

        // Simulate concurrent resolution: both requests start simultaneously
        // Both call resolve() before either calls consume()
        
        $results = [];
        $exceptions = [];

        // Request 1: resolve
        try {
            $resolved1 = $service->resolve($token);
            $results[] = $resolved1 !== null ? 'success' : 'fail';
        } catch (\Throwable $e) {
            $exceptions[] = get_class($e);
            $results[] = 'exception';
        }

        // RACE SIMULATION: Restore token to cache (simulating Request 2 having called resolve()
        // BEFORE Request 1 called consume())
        $cacheKey = $service->keyFor($token);
        Cache::put($cacheKey, $userId, now()->addMinutes(10));

        // Request 2: resolve (with race-simulated cache state)
        try {
            $resolved2 = $service->resolve($token);
            $results[] = $resolved2 !== null ? 'success' : 'fail';
        } catch (\Throwable $e) {
            $exceptions[] = get_class($e);
            $results[] = 'exception';
        }

        // ASSERTION: Only one resolve should succeed
        // With current get() implementation, BOTH succeed (vulnerability)
        // After fix to atomic resolve-and-consume, second fails

        $successCount = count(array_filter($results, fn($r) => $r === 'success'));

        $this->assertEquals(
            1,
            $successCount,
            'Expected exactly 1 successful token resolution, got ' . $successCount . '. '
            . 'Results: ' . implode(', ', $results) . '. '
            . 'Vulnerability: pending token resolve not atomic. Concurrent requests can both authenticate. '
            . 'Current: resolve() = get(), consume() after auth. '
            . 'Fix: atomic resolve-and-consume operation.'
        );
    }
}
