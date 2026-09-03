<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Services\Security\AccountLockService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class AccountLockoutPersistenceTest extends TestCase
{
    private User $user;
    private AccountLockService $lockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Locky Test',
            'username' => 'lockytest',
            'email'    => 'locky@example.com',
            'password' => Hash::make('SecretPass123!'),
        ]);

        $this->lockService = app(AccountLockService::class);
        config([
            'authentication.security.account_lockout.enabled'               => true,
            'authentication.security.account_lockout.max_failed_attempts'   => 5,
            'authentication.security.account_lockout.lockout_duration_mins' => 15,
        ]);
    }

    public function test_lockout_state_persists_and_is_enforced(): void
    {
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit');

        $this->assertFalse($this->lockService->isLocked($this->user));

        // Record failures up to threshold (5)
        for ($i = 0; $i < 5; $i++) {
            $this->lockService->recordFailureAndCheckLockout($this->user, $context);
        }

        // Account must now be locked
        $this->assertTrue($this->lockService->isLocked($this->user));

        // Clearing failures must unlock (logout / successful auth path)
        $this->lockService->clearFailures($this->user);
        $this->assertFalse($this->lockService->isLocked($this->user));
    }
}
