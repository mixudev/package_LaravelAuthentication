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
use Vendor\LaravelAuthentication\Services\Core\TokenService;
use Vendor\LaravelAuthentication\Services\Otp\OtpService;
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

    public function test_otp_failure_written_to_audit_trail(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        /** @var OtpService $service */
        $service = $this->app->make(OtpService::class);
        $code = $service->generate('otp-fail@example.com', $this->context());

        try {
            $service->verify('otp-fail@example.com', str_repeat('9', strlen($code)), $this->context());
            $this->fail('Expected InvalidCredentialsException.');
        } catch (\Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException) {
            // expected
        }

        $record = AuthenticationAttempt::where('status', 'OTP_FAILED')->first();
        $this->assertNotNull($record, 'OTP_FAILED event missing from audit trail.');
    }

    public function test_token_revoked_written_to_audit_trail(): void
    {
        $user = $this->makeUser();

        /** @var TokenService $service */
        $service = $this->app->make(TokenService::class);
        // User tanpa Sanctum: revoke best-effort, audit tetap ditulis.
        $service->revokeAllTokens($user);

        $record = AuthenticationAttempt::where('status', 'TOKEN_REVOKED')->first();
        $this->assertNotNull($record, 'TOKEN_REVOKED event missing from audit trail.');
    }

    public function test_password_reset_requested_written_to_audit_trail(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status');

        $record = AuthenticationAttempt::where('status', 'PASSWORD_RESET_REQUESTED')->first();
        $this->assertNotNull($record, 'PASSWORD_RESET_REQUESTED event missing from audit trail.');
    }
}
