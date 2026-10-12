<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Services\Password\PasswordService;
use Illuminate\Support\Facades\Schema;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        config(['auth.passwords.users.table' => 'password_reset_tokens']);
    }

    public function test_password_broker_statuses_use_package_messages(): void
    {
        $controller = app(\Vendor\LaravelAuthentication\Http\Controllers\PasswordResetController::class);
        $method = new \ReflectionMethod($controller, 'passwordResetStatusMessage');
        $method->setAccessible(true);

        foreach ([
            'passwords.token',
            'passwords.user',
            'passwords.throttled',
            'passwords.sent',
            'passwords.reset',
        ] as $status) {
            foreach (['en', 'id'] as $locale) {
                app()->setLocale($locale);
                $message = $method->invoke($controller, $status);

                $this->assertNotSame($status, $message);
                $this->assertStringNotContainsString('passwords.', $message);
                $this->assertStringNotContainsString('authentication::', $message);
            }
        }
    }

    public function test_prevents_password_reuse_when_history_enabled(): void
    {
        $user = User::create([
            'email'    => 'history_user@example.com',
            'password' => Hash::make('InitialPassword123!'),
        ]);

        /** @var PasswordService $passwordService */
        $passwordService = app(PasswordService::class);

        // Update to a new password
        $passwordService->updatePassword($user, 'SecondPassword123!');

        // Attempt to update to the immediate previous password (should throw exception)
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('You cannot reuse any of your last 5 passwords.');

        $passwordService->updatePassword($user, 'SecondPassword123!');
    }

    public function test_send_password_reset_link_dispatches_queued_job_by_default(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertSessionHas('status');
        \Illuminate\Support\Facades\Queue::assertPushed(\Vendor\LaravelAuthentication\Jobs\SendPasswordResetLinkJob::class, function ($job) {
            return $job->email === 'user@example.com';
        });
    }

    public function test_reset_password_with_same_current_password_returns_friendly_error_not_500(): void
    {
        $user = User::create([
            'email'    => 'reset_friendly@example.com',
            'password' => Hash::make('CurrentPassword123!'),
        ]);

        $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);

        $response = $this->from('/reset-password/' . $token)->post('/reset-password', [
            'token'                 => $token,
            'email'                 => 'reset_friendly@example.com',
            'password'              => 'CurrentPassword123!',
            'password_confirmation' => 'CurrentPassword123!',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);
    }
}

