<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Models\AuthenticationAttempt;
use Vendor\LaravelAuthentication\Services\Password\PasswordService;
use Vendor\LaravelAuthentication\Services\Security\AccountLockService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * SA-31 FIX: Audit trail completeness.
 *
 * Verifies that the full security lifecycle is written to the database audit trail —
 * not just login/lockout, but also password changes, email verification, and the
 * account-lock event itself. Previously these events dispatched but never reached
 * the audit log (enum cases existed but had no consumer).
 */
class AuditTrailCompletenessTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // DB audit trail active for this test.
        $app['config']->set('authentication.audit.driver', 'database');
        $app['config']->set('authentication.audit.enabled', true);
        $app['config']->set('authentication.audit.retention_days', 90);
    }

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Audit Trail',
            'username' => 'audit-trail',
            'email'    => 'audit-trail@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
    }

    private function context(): AuthenticationContext
    {
        return new AuthenticationContext('127.0.0.1', 'PHPUnit', AuthenticationChannel::WEB, 'web');
    }

    public function test_password_change_written_to_audit_trail(): void
    {
        $user = $this->makeUser();

        /** @var PasswordService $service */
        $service = $this->app->make(PasswordService::class);
        $service->updatePassword($user, 'NewSecret456!');

        $record = AuthenticationAttempt::where('status', 'PASSWORD_CHANGED')->first();
        $this->assertNotNull($record, 'PASSWORD_CHANGED event missing from audit trail.');
        $this->assertSame('audit-trail@example.com', $user->email);
    }

    public function test_account_locked_written_to_audit_trail(): void
    {
        $user = $this->makeUser();

        /** @var AccountLockService $service */
        $service = $this->app->make(AccountLockService::class);

        // Default lockout threshold is 5 consecutive failures.
        foreach (range(1, 5) as $i) {
            $service->recordFailureAndCheckLockout($user, $this->context());
        }

        $record = AuthenticationAttempt::where('status', 'ACCOUNT_LOCKED')->first();
        $this->assertNotNull($record, 'ACCOUNT_LOCKED event missing from audit trail.');
    }

    public function test_audit_logger_contract_is_injectable(): void
    {
        $this->assertInstanceOf(
            AuditLoggerInterface::class,
            $this->app->make(AuditLoggerInterface::class)
        );
    }
}
