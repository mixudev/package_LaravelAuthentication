<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Events\AccountLocked;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Handles account lockout defense, tracking consecutive failures and managing lock expirations.
 */
class AccountLockService
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly Dispatcher $events,
        private readonly AuthenticationConfig $config
    ) {}

    public function isLocked(Authenticatable $user): bool
    {
        if (!$this->config->isLockoutEnabled()) {
            return false;
        }

        $lockKey = $this->getLockKey($user);
        return $this->cache->has($lockKey);
    }

    public function recordFailureAndCheckLockout(Authenticatable $user, AuthenticationContext $context): bool
    {
        if (!$this->config->isLockoutEnabled()) {
            return false;
        }

        $counterKey = $this->getCounterKey($user);
        $attempts = (int) $this->cache->get($counterKey, 0) + 1;
        $maxAttempts = $this->config->getLockoutMaxAttempts();

        if ($attempts >= $maxAttempts) {
            $lockoutMinutes = $this->config->getLockoutDurationMinutes();
            $this->cache->put($this->getLockKey($user), true, now()->addMinutes($lockoutMinutes));
            $this->cache->forget($counterKey);

            $this->events->dispatch(new AccountLocked($user, $context, $lockoutMinutes));
            return true;
        }

        $this->cache->put($counterKey, $attempts, now()->addMinutes(15));
        return false;
    }

    public function clearFailures(Authenticatable $user): void
    {
        $this->cache->forget($this->getCounterKey($user));
        $this->cache->forget($this->getLockKey($user));
    }

    protected function getLockKey(Authenticatable $user): string
    {
        return 'auth_account_locked|' . $user->getAuthIdentifier();
    }

    protected function getCounterKey(Authenticatable $user): string
    {
        return 'auth_account_failures|' . $user->getAuthIdentifier();
    }
}
