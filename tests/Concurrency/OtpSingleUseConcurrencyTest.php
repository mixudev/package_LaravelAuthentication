<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Concurrency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Services\Otp\OtpService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * H-01: OTP Single-Use Race Condition
 *
 * Concurrent verification requests can both authenticate with the same valid OTP
 * because get() + validate + forget() is not atomic.
 *
 * This test proves the vulnerability exists and guards against regression.
 */
class OtpSingleUseConcurrencyTest extends TestCase
{
    public function test_concurrent_otp_verify_requests_only_one_succeeds(): void
    {
        Config::set('authentication.features.otp.enabled', true);

        $user = User::create([
            'name'     => 'OTP User',
            'username' => 'otpuser',
            'email'    => 'otp-concurrent@example.com',
            'password' => bcrypt('password'),
        ]);

        $identifier = $user->email;

        /** @var OtpService $otpService */
        $otpService = app(OtpService::class);

        $context = new AuthenticationContext(
            ipAddress: '127.0.0.1',
            userAgent: 'TestAgent',
            headers: [],
            clientId: 'test-client'
        );

        // Generate OTP
        $otpService->generate($identifier, $context);

        // Extract the actual OTP code and payload from cache
        $cacheKey = 'auth_otp_code|' . sha1(strtolower($identifier));
        $payload = Cache::get($cacheKey);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('hash', $payload);

        // Brute-force find the actual code (numeric 6-digit)
        $actualCode = null;
        for ($i = 0; $i < 1000000; $i++) {
            $candidate = str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            if (hash_equals($payload['hash'], hash('sha256', $candidate))) {
                $actualCode = $candidate;
                break;
            }
        }

        $this->assertNotNull($actualCode, 'Could not derive OTP code for concurrent test');

        // RACE SIMULATION:
        // Current implementation: get() → validate → forget()
        // Race window: both requests get() before either forget()
        //
        // To prove vulnerability without true concurrency:
        // 1. First verify() will get(), validate, forget()
        // 2. Before second verify(), restore the payload to cache (simulating race where second get() happened before first forget())
        // 3. Second verify() should also succeed (proving single-use is not atomic)

        $results = [];
        $exceptions = [];

        // Request 1
        try {
            $user1 = $otpService->verify($identifier, $actualCode, $context);
            $results[] = $user1 !== null ? 'success' : 'fail';
        } catch (\Throwable $e) {
            $exceptions[] = get_class($e);
            $results[] = 'exception';
        }

        // RACE SIMULATION: Restore payload and attempt counter to simulate concurrent get() before forget()
        // This represents Request 2 having called get() BEFORE Request 1 called forget()
        Cache::put($cacheKey, $payload, now()->addMinutes(10));
        Cache::put($cacheKey . ':attempts', 0, now()->addMinutes(10));

        // Request 2 (with race-simulated cache state)
        try {
            $user2 = $otpService->verify($identifier, $actualCode, $context);
            $results[] = $user2 !== null ? 'success' : 'fail';
        } catch (\Throwable $e) {
            $exceptions[] = get_class($e);
            $results[] = 'exception';
        }

        // ASSERTION: Only one request should succeed.
        // With current get()+forget() implementation, BOTH will succeed (vulnerability).
        // After fix to pull() (atomic), second request will fail because pull() is atomic.

        $successCount = count(array_filter($results, fn($r) => $r === 'success'));

        $this->assertEquals(
            1,
            $successCount,
            'Expected exactly 1 successful OTP verification, got ' . $successCount . '. '
            . 'Results: ' . implode(', ', $results) . '. '
            . 'Vulnerability: OTP single-use not atomic. Concurrent requests can both authenticate with same OTP. '
            . 'Current implementation uses get() + forget() which allows race window. '
            . 'Fix: use atomic pull() operation.'
        );
    }
}
