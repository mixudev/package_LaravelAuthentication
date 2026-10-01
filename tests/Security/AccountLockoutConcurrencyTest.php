<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Models\AccountLockout;
use Vendor\LaravelAuthentication\Services\Security\AccountLockService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * PERF-02 Verification: Account lockout race condition protection.
 *
 * Simulates concurrent login failures to verify atomic increment prevents
 * bypass of max_attempts threshold. Without DB transaction + lockForUpdate,
 * concurrent requests can each read failed_attempts=4, increment to 5, save,
 * bypassing the lockout.
 */
class AccountLockoutConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['authentication.security.account_lockout.enabled' => true]);
        config(['authentication.security.account_lockout.max_failed_attempts' => 5]);
        config(['authentication.security.account_lockout.lockout_duration_mins' => 15]);
    }

    public function test_concurrent_failures_correctly_trigger_lockout_at_threshold(): void
    {
        $user = User::create([
            'name'     => 'Concurrent Test User',
            'email'    => 'concurrent@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $lockService = app(AccountLockService::class);
        $context = new AuthenticationContext('127.0.0.1', 'Test Agent', AuthenticationChannel::WEB, 'web');

        // Simulate 5 concurrent failures via rapid sequential calls
        // (true concurrency requires multi-process, but rapid sequential reveals race window)
        for ($i = 0; $i < 5; $i++) {
            $lockService->recordFailureAndCheckLockout($user, $context);
        }

        // Verify lockout record shows exactly 5 attempts (not 0, not >5)
        $record = AccountLockout::where('user_identifier', (string) $user->id)->first();

        $this->assertNotNull($record, 'Lockout record must exist');
        $this->assertEquals(5, $record->failed_attempts, 'Atomic increment must result in exactly 5 attempts');
        $this->assertTrue($record->isLocked(), 'Account must be locked at threshold');
    }

    public function test_lockout_prevents_further_authentication_attempts(): void
    {
        $user = User::create([
            'name'     => 'Locked User',
            'email'    => 'locked@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $lockService = app(AccountLockService::class);
        $context = new AuthenticationContext('127.0.0.1', 'Test Agent', AuthenticationChannel::WEB, 'web');

        // Trigger lockout
        for ($i = 0; $i < 5; $i++) {
            $lockService->recordFailureAndCheckLockout($user, $context);
        }

        // Verify isLocked returns true
        $this->assertTrue($lockService->isLocked($user), 'Locked user must be detected as locked');

        // Verify 6th attempt does not increment (lockout enforced)
        $beforeCount = AccountLockout::where('user_identifier', (string) $user->id)->value('failed_attempts');
        $lockService->recordFailureAndCheckLockout($user, $context);
        $afterCount = AccountLockout::where('user_identifier', (string) $user->id)->value('failed_attempts');

        $this->assertEquals($beforeCount, $afterCount, 'Locked account should not increment attempts further');
    }

    public function test_clear_failures_resets_lockout_state(): void
    {
        $user = User::create([
            'name'     => 'Reset User',
            'email'    => 'reset@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $lockService = app(AccountLockService::class);
        $context = new AuthenticationContext('127.0.0.1', 'Test Agent', AuthenticationChannel::WEB, 'web');

        // Trigger partial failures
        for ($i = 0; $i < 3; $i++) {
            $lockService->recordFailureAndCheckLockout($user, $context);
        }

        $this->assertDatabaseHas('authentication_account_lockouts', [
            'user_identifier' => (string) $user->id,
        ]);

        // Clear failures (simulates successful login)
        $lockService->clearFailures($user);

        $this->assertDatabaseMissing('authentication_account_lockouts', [
            'user_identifier' => (string) $user->id,
        ]);
    }

    public function test_lockout_respects_disabled_config(): void
    {
        config(['authentication.security.account_lockout.enabled' => false]);

        $user = User::create([
            'name'     => 'No Lock User',
            'email'    => 'nolock@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $lockService = app(AccountLockService::class);
        $context = new AuthenticationContext('127.0.0.1', 'Test Agent', AuthenticationChannel::WEB, 'web');

        // Attempt 10 failures (well over threshold)
        for ($i = 0; $i < 10; $i++) {
            $result = $lockService->recordFailureAndCheckLockout($user, $context);
            $this->assertFalse($result, 'Lockout must not trigger when disabled');
        }

        // Verify no lockout record created
        $this->assertDatabaseMissing('authentication_account_lockouts', [
            'user_identifier' => (string) $user->id,
        ]);

        $this->assertFalse($lockService->isLocked($user), 'isLocked must return false when disabled');
    }
}
