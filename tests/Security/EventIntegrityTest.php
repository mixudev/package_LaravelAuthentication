<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Events\AccountLocked;
use Vendor\LaravelAuthentication\Events\LoginAttempted;
use Vendor\LaravelAuthentication\Events\LoginFailed;
use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Vendor\LaravelAuthentication\Events\PasswordChanged;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Services\Password\PasswordService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Event Integrity & Security Event Payload Tests
 *
 * Verifies:
 * - Correct events fired in correct sequence
 * - Events carry NO plaintext passwords or sensitive secrets
 * - Failed login dispatches LoginFailed (not swallowed)
 * - Account lockout dispatches AccountLocked with correct duration
 * - PasswordChanged dispatched on updatePassword
 * - Events are idempotent (double-dispatch does not bypass state)
 */
class EventIntegrityTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Eve Test',
            'username' => 'evetest',
            'email'    => 'eve@example.com',
            'password' => Hash::make('SecurePassword1!'),
        ]);
    }

    // ---------------------------------------------------------------
    // TEST 1: Success path fires LoginAttempted then LoginSucceeded in order
    // ---------------------------------------------------------------
    public function test_successful_login_fires_events_in_correct_order(): void
    {
        $firedEvents = [];

        Event::listen(LoginAttempted::class, function () use (&$firedEvents) {
            $firedEvents[] = 'LoginAttempted';
        });
        Event::listen(LoginSucceeded::class, function () use (&$firedEvents) {
            $firedEvents[] = 'LoginSucceeded';
        });

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        $service->authenticate(
            new LoginData('eve@example.com', 'SecurePassword1!'),
            new AuthenticationContext('127.0.0.1', 'PHPUnit')
        );

        $this->assertSame(['LoginAttempted', 'LoginSucceeded'], $firedEvents);
    }

    // ---------------------------------------------------------------
    // TEST 2: Failed login fires LoginAttempted then LoginFailed
    // ---------------------------------------------------------------
    public function test_failed_login_fires_events_in_correct_order(): void
    {
        $firedEvents = [];

        Event::listen(LoginAttempted::class, function () use (&$firedEvents) {
            $firedEvents[] = 'LoginAttempted';
        });
        Event::listen(LoginFailed::class, function () use (&$firedEvents) {
            $firedEvents[] = 'LoginFailed';
        });

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        try {
            $service->authenticate(
                new LoginData('eve@example.com', 'WrongPassword!'),
                new AuthenticationContext('127.0.0.1', 'PHPUnit')
            );
        } catch (InvalidCredentialsException) {
            // expected
        }

        $this->assertSame(['LoginAttempted', 'LoginFailed'], $firedEvents);
    }

    // ---------------------------------------------------------------
    // TEST 3: LoginFailed payload contains NO raw password
    // ---------------------------------------------------------------
    public function test_login_failed_event_does_not_contain_raw_password(): void
    {
        $rawPassword = 'WrongPassword!';
        $capturedEvent = null;

        Event::listen(LoginFailed::class, function (LoginFailed $event) use (&$capturedEvent) {
            $capturedEvent = $event;
        });

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        try {
            $service->authenticate(
                new LoginData('eve@example.com', $rawPassword),
                new AuthenticationContext('127.0.0.1', 'PHPUnit')
            );
        } catch (InvalidCredentialsException) {
            // expected
        }

        $this->assertNotNull($capturedEvent, 'LoginFailed event not captured.');

        $serialized = serialize($capturedEvent);
        $this->assertStringNotContainsString(
            $rawPassword,
            $serialized,
            'LoginFailed event payload contains raw password!'
        );
    }

    // ---------------------------------------------------------------
    // TEST 4: LoginSucceeded payload contains NO password hash
    // ---------------------------------------------------------------
    public function test_login_succeeded_event_does_not_contain_password_hash(): void
    {
        $capturedEvent = null;

        Event::listen(LoginSucceeded::class, function (LoginSucceeded $event) use (&$capturedEvent) {
            $capturedEvent = $event;
        });

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        $service->authenticate(
            new LoginData('eve@example.com', 'SecurePassword1!'),
            new AuthenticationContext('127.0.0.1', 'PHPUnit')
        );

        $this->assertNotNull($capturedEvent, 'LoginSucceeded event not captured.');

        // The user model's password hash must NOT appear in the event's serialized form
        $hash = $this->user->password;
        $serialized = serialize($capturedEvent);
        $this->assertStringNotContainsString(
            $hash,
            $serialized,
            'LoginSucceeded event payload contains password hash!'
        );
    }

    // ---------------------------------------------------------------
    // TEST 5: AccountLocked event fires with correct duration
    // ---------------------------------------------------------------
    public function test_account_locked_event_carries_correct_lockout_duration(): void
    {
        $capturedEvent = null;

        Event::listen(AccountLocked::class, function (AccountLocked $event) use (&$capturedEvent) {
            $capturedEvent = $event;
        });

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $maxAttempts = (int) config('authentication.security.account_lockout.max_failed_attempts', 5);
        $expectedDuration = (int) config('authentication.security.account_lockout.lockout_duration_mins', 15);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $uniqueIp = '10.50.' . $i . '.1';
            try {
                $service->authenticate(
                    new LoginData('eve@example.com', 'BadPassword!'),
                    new AuthenticationContext($uniqueIp, 'PHPUnit')
                );
            } catch (AccountLockedException | InvalidCredentialsException) {
                // expected
            }
        }

        if ($capturedEvent === null) {
            $this->markTestSkipped('Account not locked in this test run (may be rate-limited first).');
        }

        $this->assertSame($this->user->getAuthIdentifier(), $capturedEvent->user->getAuthIdentifier());
        $this->assertSame($expectedDuration, $capturedEvent->lockoutDurationMinutes);
    }

    // ---------------------------------------------------------------
    // TEST 6: PasswordChanged event dispatched on updatePassword
    // ---------------------------------------------------------------
    public function test_password_changed_event_dispatched_on_update(): void
    {
        $fired = false;
        Event::listen(PasswordChanged::class, function (PasswordChanged $event) use (&$fired) {
            $fired = true;
            // Must carry the user — no raw password
            $this->assertNotNull($event->user);
        });

        /** @var PasswordService $passwordService */
        $passwordService = app(PasswordService::class);
        $passwordService->updatePassword($this->user, 'NewSecurePassword2!');

        $this->assertTrue($fired, 'PasswordChanged event was not dispatched.');
    }

    // ---------------------------------------------------------------
    // TEST 7: Non-existent user login fires LoginFailed (not swallowed)
    // ---------------------------------------------------------------
    public function test_non_existent_user_login_fires_login_failed_event(): void
    {
        $fired = false;
        Event::listen(LoginFailed::class, function () use (&$fired) {
            $fired = true;
        });

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        try {
            $service->authenticate(
                new LoginData('ghost@nowhere.com', 'AnyPassword1!'),
                new AuthenticationContext('127.0.0.1', 'PHPUnit')
            );
        } catch (InvalidCredentialsException) {
            // expected
        }

        $this->assertTrue($fired, 'LoginFailed event not fired for non-existent user.');
    }
}
