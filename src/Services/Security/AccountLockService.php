<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
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
        private readonly AuthenticationConfig $config
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

        /** @var AccountLockout $record */
        $record = AccountLockout::firstOrCreate(
            ['user_identifier' => $this->identifierFor($user)],
            ['failed_attempts' => 0]
        );

        $record->failed_attempts = (int) $record->failed_attempts + 1;
        $record->last_failure_at = \Illuminate\Support\Carbon::now();

        if ($record->failed_attempts >= $maxAttempts) {
            $lockoutMinutes = $this->config->getLockoutDurationMinutes();
            $record->locked_until = \Illuminate\Support\Carbon::now()->addMinutes($lockoutMinutes);
            $record->save();

            $this->events->dispatch(new AccountLocked($user, $context, $lockoutMinutes));
            return true;
        }

        $record->save();
        return false;
    }

    public function clearFailures(Authenticatable $user): void
    {
        AccountLockout::where('user_identifier', $this->identifierFor($user))->delete();
    }

    protected function findRecord(Authenticatable $user): ?AccountLockout
    {
        return AccountLockout::where('user_identifier', $this->identifierFor($user))->first();
    }

    protected function identifierFor(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }
}
