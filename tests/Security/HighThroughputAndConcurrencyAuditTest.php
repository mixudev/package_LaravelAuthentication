<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Models\AccountLockout;
use Vendor\LaravelAuthentication\Services\Security\AccountLockService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

final class HighThroughputAndConcurrencyAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(TokenManagerInterface::class, new class implements TokenManagerInterface {
            public function createToken($user, string $tokenName = 'auth_token', array $abilities = ['*']): string
            {
                return 'fake_token_' . $user->getAuthIdentifier();
            }

            public function revokeAllTokens($user): void {}

            public function revokeCurrentToken($user): void {}
        });

        $this->user = User::create([
            'name'     => 'Audit User',
            'username' => 'audituser',
            'email'    => 'audit@example.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        Config::set('authentication.security.abuse_policy.enabled', true);
        Config::set('authentication.security.account_lockout.enabled', true);
        Config::set('authentication.security.account_lockout.max_failed_attempts', 5);
        Config::set('authentication.security.account_lockout.lockout_duration_mins', 15);
    }

    #[Test]
    public function throttled_attacker_cannot_login_even_with_correct_password(): void
    {
        // When lockout is disabled, rate limiter handles throttling
        Config::set('authentication.security.account_lockout.enabled', false);

        $service = app(AuthenticationServiceInterface::class);
        $context = new AuthenticationContext('203.0.113.10', 'PHPUnit');

        // Exhaust the budget with invalid passwords
        for ($i = 0; $i < 5; $i++) {
            try {
                $service->authenticate(new LoginData('audit@example.com', 'WrongPass123!'), $context);
            } catch (InvalidCredentialsException|AuthenticationThrottledException) {
                // Expected
            }
        }

        // Attempt with CORRECT password while throttled: must be rejected with AuthenticationThrottledException
        $this->expectException(AuthenticationThrottledException::class);

        $service->authenticate(new LoginData('audit@example.com', 'CorrectPassword123!'), $context);
    }

    #[Test]
    public function locked_account_cannot_login_even_with_correct_password(): void
    {
        Config::set('authentication.security.account_lockout.enabled', true);

        $service = app(AuthenticationServiceInterface::class);
        $context = new AuthenticationContext('203.0.113.11', 'PHPUnit');

        for ($i = 0; $i < 5; $i++) {
            try {
                $service->authenticate(new LoginData('audit@example.com', 'WrongPass123!'), $context);
            } catch (InvalidCredentialsException|AccountLockedException|AuthenticationThrottledException) {
                // Expected
            }
        }

        // Attempt with CORRECT password while locked: must be rejected with AccountLockedException
        $this->expectException(AccountLockedException::class);

        $service->authenticate(new LoginData('audit@example.com', 'CorrectPassword123!'), $context);
    }

    #[Test]
    public function expired_account_lockout_resets_failed_attempts_cycle(): void
    {
        $lockService = app(AccountLockService::class);
        $context = new AuthenticationContext('198.51.100.1', 'PHPUnit');

        // Engage lockout: 5 failures
        for ($i = 0; $i < 5; $i++) {
            $lockService->recordFailureAndCheckLockout($this->user, $context);
        }

        $this->assertTrue($lockService->isLocked($this->user));

        // Simulate lockout expiration: travel 16 minutes into the future
        Carbon::setTestNow(now()->addMinutes(16));

        $this->assertFalse($lockService->isLocked($this->user), 'Lockout must be expired after duration');

        // Record 1 new failure after expiration: must NOT immediately re-lock
        $lockedAgain = $lockService->recordFailureAndCheckLockout($this->user, $context);

        $this->assertFalse($lockedAgain, 'A single failure after lockout expiration must not re-lock immediately');

        $record = AccountLockout::where('user_identifier', (string) $this->user->getAuthIdentifier())->first();
        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record->failed_attempts, 'Failed attempts must reset to 1 on first failure of a new cycle');
        $this->assertNull($record->locked_until, 'locked_until must be cleared on new cycle');

        Carbon::setTestNow(); // Reset time
    }

    #[Test]
    public function stale_failures_decay_after_lockout_duration_elapsed(): void
    {
        $lockService = app(AccountLockService::class);
        $context = new AuthenticationContext('198.51.100.2', 'PHPUnit');

        // Fail 4 times (under max_attempts of 5)
        for ($i = 0; $i < 4; $i++) {
            $lockService->recordFailureAndCheckLockout($this->user, $context);
        }

        $record = AccountLockout::where('user_identifier', (string) $this->user->getAuthIdentifier())->first();
        $this->assertSame(4, (int) $record->failed_attempts);

        // Simulate 20 minutes passing (decay window is 15 minutes)
        Carbon::setTestNow(now()->addMinutes(20));

        // Record a 5th attempt after decay window: should reset to attempt 1, NOT lock the account!
        $locked = $lockService->recordFailureAndCheckLockout($this->user, $context);

        $this->assertFalse($locked, 'Attempt after decay window must not engage lockout');

        $record->refresh();
        $this->assertSame(1, (int) $record->failed_attempts, 'Stale failure count must decay and reset to 1');

        Carbon::setTestNow();
    }

    #[Test]
    public function social_auth_api_callback_messages_are_localized(): void
    {
        app()->setLocale('id');

        $response = $this->postJson('/api/v1/auth/social/unknown-provider');
        $response->assertStatus(403);
        $this->assertStringContainsString('Masuk dengan unknown-provider tidak tersedia atau tidak didukung.', (string) $response->json('message'));

        app()->setLocale('en');

        $responseEn = $this->postJson('/api/v1/auth/social/unknown-provider');
        $responseEn->assertStatus(403);
        $this->assertStringContainsString('Social sign-in with unknown-provider is disabled or unsupported.', (string) $responseEn->json('message'));
    }

    #[Test]
    public function two_factor_challenge_api_verify_returns_json_when_user_missing(): void
    {
        // An API call without valid pending token or user must return 401 JSON
        $response = $this->postJson('/api/v1/auth/two-factor/verify', [
            'pending_token' => 'invalid-token-here',
            'code' => '123456',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function register_api_returns_sanitized_user_via_safe_user_presenter(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'New Safe User',
            'email'                 => 'safeuser@example.com',
            'username'              => 'safeuser',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);
        $user = $response->json('user');
        $this->assertIsArray($user);
        $this->assertArrayHasKey('id', $user);
        $this->assertArrayHasKey('name', $user);
        $this->assertArrayHasKey('email', $user);
        // Ensure sensitive Eloquent fields are never exposed
        $this->assertArrayNotHasKey('password', $user);
        $this->assertArrayNotHasKey('remember_token', $user);
    }
}
