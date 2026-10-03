<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Models\AccountLockout;
use Vendor\LaravelAuthentication\Services\Security\AccountLockService;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Regression coverage for the H-05 account lockout creation race.
 *
 * The first race-safe implementation created the row with failed_attempts = 1 and
 * then fell through to the shared increment, counting the very first failure twice.
 * Accounts therefore locked one attempt early, which surfaced as
 * AccountLockedException where callers expected a throttle.
 */
final class AccountLockoutFirstFailureCountTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function first_failure_counts_as_exactly_one_attempt(): void
    {
        $user = $this->makeUser();
        $service = app(AccountLockService::class);

        $service->recordFailureAndCheckLockout($user, $this->context());

        $record = AccountLockout::where('user_identifier', (string) $user->getAuthIdentifier())->firstOrFail();

        $this->assertSame(1, (int) $record->failed_attempts, 'first failure must count once, not twice');
        $this->assertNull($record->locked_until, 'first failure must not engage a lockout');
    }

    #[Test]
    public function lockout_engages_exactly_on_the_configured_final_attempt(): void
    {
        $user = $this->makeUser();
        $service = app(AccountLockService::class);
        $maxAttempts = (int) config('authentication.lockout.max_attempts', 5);

        $locked = false;
        $failuresBeforeLock = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $locked = $service->recordFailureAndCheckLockout($user, $this->context());
            $failuresBeforeLock = (int) AccountLockout::where('user_identifier', (string) $user->getAuthIdentifier())
                ->value('failed_attempts');

            if ($locked) {
                break;
            }
        }

        $this->assertTrue($locked, 'lockout must engage by the final configured attempt');
        $this->assertSame($maxAttempts, $failuresBeforeLock, 'counter must not overshoot max_attempts');
    }

    #[Test]
    public function concurrent_first_failures_create_exactly_one_lockout_row(): void
    {
        $user = $this->makeUser();
        $identifier = (string) $user->getAuthIdentifier();

        // Establish the row the way the service does.
        AccountLockout::query()->create([
            'user_identifier' => $identifier,
            'failed_attempts' => 1,
            'last_failure_at' => now(),
        ]);

        $duplicateInsertFailed = false;

        try {
            // Model the losing side of the concurrent-insert race.
            AccountLockout::query()->create([
                'user_identifier' => $identifier,
                'failed_attempts' => 1,
                'last_failure_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $duplicateInsertFailed = true;
        }

        $this->assertTrue(
            $duplicateInsertFailed,
            'unique constraint on user_identifier must reject a concurrent duplicate insert'
        );

        $rows = AccountLockout::where('user_identifier', $identifier)->count();

        $this->assertSame(1, $rows, 'exactly one lockout row may exist per identifier');
    }

    #[Test]
    public function lockout_identifier_is_stable_and_unmasked_so_the_unique_index_is_safe(): void
    {
        $user = $this->makeUser();

        $service = app(AccountLockService::class);
        $service->recordFailureAndCheckLockout($user, $this->context());

        $identifier = AccountLockout::query()->value('user_identifier');

        // A masked/low-entropy identifier could collide across distinct users, and the
        // UNIQUE index would then merge their lockout state — one account could lock
        // out an unrelated account. Assert the raw, full identifier is what is stored.
        $this->assertSame(
            (string) $user->getAuthIdentifier(),
            (string) $identifier,
            'lockout key must be the raw unique identifier, never a masked value'
        );
    }

    private function makeUser(): \Illuminate\Contracts\Auth\Authenticatable
    {
        $user = new class extends \Illuminate\Foundation\Auth\User
        {
            protected $table = 'users';

            protected $fillable = ['email', 'password'];

            public function getAuthIdentifierName()
            {
                return 'id';
            }
        };

        $user->forceFill(['id' => 4242, 'email' => 'lockout-first-failure@example.test', 'password' => bcrypt('secret-value')]);
        $user->save();

        return $user;
    }

    private function context(): AuthenticationContext
    {
        return new AuthenticationContext(
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );
    }
}