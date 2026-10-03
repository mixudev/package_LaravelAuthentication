<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Events\AccountLocked;
use Vendor\LaravelAuthentication\Models\AccountLockout;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Handles account lockout defense, tracking consecutive failures and managing lock expirations.
 *
 * SEC-07 FIX: State is persisted in the database (not cache) so lockout enforcement is
 * durable and consistent across multi-server / shared-cache deployments. A cache flush
 * can no longer bypass the lockout.
 */
class AccountLockService
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly AuthenticationConfig $config,
        private readonly AuditLoggerInterface $auditService
    ) {}

    public function isLocked(Authenticatable $user): bool
    {
        if (!$this->config->isLockoutEnabled()) {
            return false;
        }

        $record = $this->findRecord($user);

        return $record !== null && $record->isLocked();
    }

    public function recordFailureAndCheckLockout(Authenticatable $user, AuthenticationContext $context): bool
    {
        if (!$this->config->isLockoutEnabled()) {
            return false;
        }

        $maxAttempts = $this->config->getLockoutMaxAttempts();
        $userIdentifier = $this->identifierFor($user);

        // SEC-15 FIX: Guard against incrementing an already-locked account.
        // A request that started before the lock engaged must not push the counter
        // further, and must not re-dispatch AccountLocked / re-extend the window.
        if ($this->isLocked($user)) {
            return false;
        }

        // PERF-02 FIX: Use database transaction with pessimistic locking to prevent
        // race condition where concurrent requests can bypass max_attempts check.
        // Without lockForUpdate(), 10 concurrent requests can each read failed_attempts=4,
        // increment to 5, and save — bypassing the lockout threshold.
        return DB::transaction(function () use ($user, $userIdentifier, $maxAttempts, $context) {
            /** @var AccountLockout|null $record */
            $record = $this->findRecordForUpdate($userIdentifier);

            $wasCreated = false;

            if ($record === null) {
                // H-05 FIX: race-safe creation. A bare insert is not atomic, so two
                // requests arriving together for a never-seen identifier can both miss
                // the SELECT above and both attempt the insert. The UNIQUE index on
                // user_identifier is the arbiter: exactly one insert wins, the other
                // raises UniqueConstraintViolationException, which we absorb by
                // re-reading the winning row under lock.
                //
                // The new row already carries failed_attempts = 1, so the winner must
                // NOT fall through to the shared increment below — that would count the
                // very first failure twice and lock the account one attempt early.
                $created = new AccountLockout([
                    'user_identifier' => $userIdentifier,
                    'failed_attempts' => 1,
                    'last_failure_at' => Carbon::now(),
                ]);

                try {
                    $created->save();
                    $record = $created;
                    $wasCreated = true;
                } catch (UniqueConstraintViolationException) {
                    // Lost the insert race. Re-read the winner under lock and fall through
                    // to the shared increment below — this request also really failed, so
                    // recording it as +1 is correct.
                    $record = $this->findRecordForUpdate($userIdentifier);

                    if ($record === null) {
                        // The winner's transaction committed but rolled back before we
                        // re-read, or the row was removed by clearFailures(). Treating
                        // this as a lost failure count is the safe outcome: we do not
                        // fabricate a row, and we do not report a lockout we cannot
                        // justify from persisted state.
                        return false;
                    }
                }
            }

            if (! $wasCreated) {
                // SEC-15: re-check under the row lock — another request may have
                // engaged the lock between the pre-check and acquiring the row.
                if ($record->isLocked()) {
                    return false;
                }

                $record->failed_attempts = (int) $record->failed_attempts + 1;
                $record->last_failure_at = Carbon::now();
                $record->save();
            }

            if ($record->failed_attempts >= $maxAttempts) {
                $lockoutMinutes = $this->config->getLockoutDurationMinutes();
                $record->locked_until = Carbon::now()->addMinutes($lockoutMinutes);
                $record->save();

                $this->events->dispatch(new AccountLocked($user, $context, $lockoutMinutes));

                $this->auditService->logEvent(
                    SecurityEventType::ACCOUNT_LOCKED,
                    (string) $user->getAuthIdentifier(),
                    $context
                );

                return true;
            }

            return false;
        });
    }

    public function clearFailures(Authenticatable $user): void
    {
        AccountLockout::where('user_identifier', $this->identifierFor($user))->delete();
    }

    protected function findRecord(Authenticatable $user): ?AccountLockout
    {
        return AccountLockout::where('user_identifier', $this->identifierFor($user))->first();
    }

    /**
     * Reads an existing lockout row under a row-level write lock.
     *
     * PERF-02/H-05: the lock must be taken in the same transaction as the update,
     * otherwise two concurrent failures can both read the same counter value and each
     * write an increment, losing one of them.
     */
    protected function findRecordForUpdate(string $userIdentifier): ?AccountLockout
    {
        $record = AccountLockout::query()
            ->where('user_identifier', $userIdentifier)
            ->lockForUpdate()
            ->first();

        return $record instanceof AccountLockout ? $record : null;
    }

    protected function identifierFor(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }
}
