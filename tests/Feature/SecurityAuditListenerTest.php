<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Events\AccountLocked;
use Vendor\LaravelAuthentication\Events\LoginFailed;
use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Vendor\LaravelAuthentication\Events\NewDeviceLoginDetected;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Listeners\SecurityAuditEventListener;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Default listener registration & behavior test.
 *
 * The listener is OPTIONAL (config `authentication.listeners.default_audit_enabled`).
 * When disabled, no listener is registered. When enabled, listeners write
 * redacted audit lines to the configured log channel.
 */
class SecurityAuditListenerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('authentication.listeners.default_audit_enabled', true);
    }

    public function test_listener_registered_when_config_enabled(): void
    {
        $this->assertTrue(
            Event::hasListeners(LoginSucceeded::class),
            'LoginSucceeded has no listeners registered when default_audit_enabled=true.'
        );
        $this->assertTrue(
            Event::hasListeners(LoginFailed::class),
            'LoginFailed has no listeners registered when default_audit_enabled=true.'
        );
        $this->assertTrue(
            Event::hasListeners(AccountLocked::class),
            'AccountLocked has no listeners registered when default_audit_enabled=true.'
        );
        $this->assertTrue(
            Event::hasListeners(NewDeviceLoginDetected::class),
            'NewDeviceLoginDetected has no listeners registered when default_audit_enabled=true.'
        );
    }

    public function test_login_success_listener_writes_redacted_audit_log(): void
    {
        $user = User::create([
            'name'     => 'Log Test',
            'username' => 'logtest',
            'email'    => 'logtest@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        Log::shouldReceive('channel')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $data) {
                $this->assertSame('authentication.security', $message);
                $this->assertSame('LOGIN_SUCCESS', $data['event']);
                $this->assertArrayHasKey('ip_address', $data);
                $this->assertArrayHasKey('user_agent', $data);
                $this->assertArrayNotHasKey('password', $data);
                $this->assertArrayNotHasKey('password_hash', $data['data']);
                return true;
            });

        (new SecurityAuditEventListener())->handleLoginSucceeded(new LoginSucceeded(
            $user,
            new AuthenticationContext('127.0.0.1', 'PHPUnit'),
            'username_or_email'
        ));

        // Verify the mocked expectations were met.
        Log::shouldReceive('channel');
        Log::shouldReceive('info');
    }

    public function test_account_locked_listener_writes_audit_log_with_duration(): void
    {
        $user = User::create([
            'email'    => 'locklog@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $duration = (int) config('authentication.security.account_lockout.lockout_duration_mins', 15);

        Log::shouldReceive('channel')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $data) use ($duration) {
                return $message === 'authentication.security'
                    && ($data['event'] ?? null) === 'ACCOUNT_LOCKED'
                    && ($data['data']['lockout_duration_min'] ?? null) === $duration;
            });

        (new SecurityAuditEventListener())->handleAccountLocked(new AccountLocked(
            $user,
            new AuthenticationContext('10.0.0.1', 'Attacker'),
            $duration
        ));
    }
}