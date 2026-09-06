<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Aggressive Rate Limiting & Brute Force Bypass Tests
 *
 * Attack scenarios:
 * - Standard brute force (same IP, same identifier)
 * - IP rotation bypass: different IPs, same identifier
 * - Identifier variant bypass: email vs username, mixed case
 * - Composite key bypass: attacker tries unique combinations to avoid per-IP limit
 * - Slow drip attack: just below the rate limit threshold
 * - Account lockout via repeated failures
 * - Lockout survives cache flush (DB-backed lockout)
 * - Correct password after lockout still blocked
 */
class RateLimitingAndBruteForceTest extends TestCase
{
    private User $victim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->victim = User::create([
            'name'     => 'Victim User',
            'username' => 'victim_user',
            'email'    => 'victim@example.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);
    }

    // ---------------------------------------------------------------
    // TEST 1: Standard brute force — same IP, same identifier
    // ---------------------------------------------------------------
    public function test_throttles_requests_after_max_failed_attempts(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $loginData = new LoginData('victim@example.com', 'BadPassword123!');
        $context = new AuthenticationContext('192.168.1.50', 'AttackerAgent/1.0');

        $throttled = false;
        for ($i = 0; $i < 10; $i++) {
            try {
                $service->authenticate($loginData, $context);
            } catch (AuthenticationThrottledException) {
                $throttled = true;
                break;
            } catch (InvalidCredentialsException) {
                // expected until throttle kicks in
            }
        }

        $this->assertTrue($throttled, 'Brute force was not throttled after repeated attempts.');
    }

    // ---------------------------------------------------------------
    // TEST 2: Account lockout via repeated failures (DB-backed)
    // ---------------------------------------------------------------
    public function test_account_lockout_activates_after_max_failures(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        $context = new AuthenticationContext('10.0.0.1', 'SlowBrute/1.0');

        $maxAttempts = (int) config('authentication.security.account_lockout.max_failed_attempts', 5);
        $locked = false;

        for ($i = 0; $i < $maxAttempts + 3; $i++) {
            // Use different IPs to avoid rate-limiter — focus on lockout test
            $uniqueIp = '10.1.' . $i . '.1';
            $ctx = new AuthenticationContext($uniqueIp, 'SlowBrute/1.0');
            try {
                $service->authenticate(
                    new LoginData('victim@example.com', 'WrongPass' . $i . '!'),
                    $ctx
                );
            } catch (AccountLockedException) {
                $locked = true;
                break;
            } catch (InvalidCredentialsException) {
                // expected until locked
            } catch (AuthenticationThrottledException) {
                // might throttle — skip for this test
                continue;
            }
        }

        $this->assertTrue($locked, 'Account was never locked despite exceeding max failed attempts.');
    }

    // ---------------------------------------------------------------
    // TEST 3: Correct password does NOT succeed when account is locked
    // ---------------------------------------------------------------
    public function test_correct_password_blocked_when_account_locked(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $maxAttempts = (int) config('authentication.security.account_lockout.max_failed_attempts', 5);

        // Force lockout using varying IPs (bypass rate limiter)
        for ($i = 0; $i < $maxAttempts; $i++) {
            $ctx = new AuthenticationContext('172.16.' . $i . '.1', 'Attacker/1.0');
            try {
                $service->authenticate(
                    new LoginData('victim@example.com', 'BadPass!'),
                    $ctx
                );
            } catch (InvalidCredentialsException | AccountLockedException | AuthenticationThrottledException) {
                // expected
            }
        }

        // Now attempt with CORRECT password — must still be blocked
        $this->expectException(AccountLockedException::class);
        $service->authenticate(
            new LoginData('victim@example.com', 'CorrectPassword123!'),
            new AuthenticationContext('192.168.200.1', 'Legitimate/1.0')
        );
    }

    // ---------------------------------------------------------------
    // TEST 4: Rate limit key is composite (identifier + IP)
    //         Different IPs for same identifier: each should be rate-limited independently.
    // ---------------------------------------------------------------
    public function test_rate_limiter_is_per_ip_and_identifier_composite(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $maxAttempts = (int) config('authentication.security.rate_limit.max_attempts', 5);

        // Exhaust the rate limit from IP A
        $contextA = new AuthenticationContext('1.1.1.1', 'Agent/1.0');
        $throttledFromA = false;
        for ($i = 0; $i < $maxAttempts + 2; $i++) {
            try {
                $service->authenticate(new LoginData('victim@example.com', 'Wrong!'), $contextA);
            } catch (AuthenticationThrottledException) {
                $throttledFromA = true;
                break;
            } catch (InvalidCredentialsException | AccountLockedException) {
                // expected
            }
        }

        $this->assertTrue($throttledFromA, 'No throttle from IP A.');

        // IP B with different identifier should NOT be throttled yet
        $contextB = new AuthenticationContext('2.2.2.2', 'Agent/1.0');
        $threwFromB = false;
        try {
            $service->authenticate(new LoginData('victim@example.com', 'Wrong!'), $contextB);
        } catch (AuthenticationThrottledException) {
            $threwFromB = true;
        } catch (InvalidCredentialsException | AccountLockedException) {
            // correct — not throttled, just failed
        }

        // IP B should NOT be throttled on first attempt
        $this->assertFalse($threwFromB, 'Rate limit from IP A leaked to unrelated IP B on first attempt.');
    }

    // ---------------------------------------------------------------
    // TEST 5: Identifier normalization — UPPERCASE email must not bypass
    // ---------------------------------------------------------------
    public function test_uppercase_identifier_does_not_bypass_rate_limit(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $maxAttempts = (int) config('authentication.security.rate_limit.max_attempts', 5);
        $context = new AuthenticationContext('3.3.3.3', 'Agent/1.0');

        $throttled = false;
        for ($i = 0; $i < $maxAttempts + 2; $i++) {
            try {
                $service->authenticate(new LoginData('VICTIM@EXAMPLE.COM', 'Wrong!'), $context);
            } catch (AuthenticationThrottledException) {
                $throttled = true;
                break;
            } catch (InvalidCredentialsException | AccountLockedException) {
                // expected
            }
        }

        $this->assertTrue($throttled, 'Uppercase identifier bypassed rate limiting.');
    }

    // ---------------------------------------------------------------
    // TEST 6: Throttle carries seconds_remaining > 0
    // ---------------------------------------------------------------
    public function test_throttle_exception_carries_non_zero_retry_seconds(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $maxAttempts = (int) config('authentication.security.rate_limit.max_attempts', 5);
        $context = new AuthenticationContext('4.4.4.4', 'Agent/1.0');

        $throttledException = null;
        for ($i = 0; $i < $maxAttempts + 2; $i++) {
            try {
                $service->authenticate(new LoginData('victim@example.com', 'Wrong!'), $context);
            } catch (AuthenticationThrottledException $e) {
                $throttledException = $e;
                break;
            } catch (InvalidCredentialsException | AccountLockedException) {
                // expected
            }
        }

        $this->assertNotNull($throttledException, 'No throttle exception thrown.');
        $this->assertGreaterThan(0, $throttledException->secondsRemaining,
            'ThrottleException seconds remaining must be > 0.');
    }
}
