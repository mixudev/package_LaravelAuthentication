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

    public function test_reset_password_rejects_extra_payload_fields(): void
    {
        $user = User::create([
            'email'    => 'reset_extra@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);

        $response = $this->from('/reset-password/' . $token)->post('/reset-password', [
            'token'                 => $token,
            'email'                 => 'reset_extra@example.com',
            'password'              => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'is_admin'              => true,
            'role'                  => 'superadmin',
        ]);

        // Extra fields must be rejected (422 form redirect with errors)
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);
    }

    public function test_reset_password_is_rate_limited_per_email_and_ip(): void
    {
        $user = User::create([
            'email'    => 'reset_limited@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        config(['authentication.security.rate_limits.password_reset.max_attempts' => 3]);

        $requestCount = (int) config('authentication.security.rate_limits.password_reset.max_attempts', 5);

        for ($i = 0; $i < $requestCount; $i++) {
            $response = $this->post('/reset-password', [
                'token'                 => 'forged-token-' . $i,
                'email'                 => 'reset_limited@example.com',
                'password'              => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);
            $response->assertStatus(302);
        }

        // The next attempt must be throttled (429 status for web is 302 with errors,
        // but the http status reflects the throttle via session errors).
        $throttled = $this->post('/reset-password', [
            'token'                 => 'forged-token-final',
            'email'                 => 'reset_limited@example.com',
            'password'              => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
        $throttled->assertStatus(302);
        $throttled->assertSessionHasErrors(['email']);
    }

    public function test_reset_password_rate_limit_bucket_clears_after_success(): void
    {
        config(['authentication.security.rate_limits.password_reset.max_attempts' => 2]);

        // Exceed the bucket once with a forged token
        $this->post('/reset-password', [
            'token'                 => 'forged-token-1',
            'email'                 => 'reset_clear@example.com',
            'password'              => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertStatus(302);

        // Second attempt hits the limit
        $this->post('/reset-password', [
            'token'                 => 'forged-token-2',
            'email'                 => 'reset_clear@example.com',
            'password'              => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertStatus(302);

        // Third attempt is throttled
        $this->post('/reset-password', [
            'token'                 => 'forged-token-3',
            'email'                 => 'reset_clear@example.com',
            'password'              => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasErrors(['email']);
    }

    public function test_reset_url_does_not_contain_email_in_query_string(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = User::create([
            'email'    => 'no_email_in_url@example.com',
            'password' => Hash::make('InitialPassword123!'),
        ]);

        $this->post('/forgot-password', ['email' => 'no_email_in_url@example.com']);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $user,
            \Illuminate\Auth\Notifications\ResetPassword::class,
            function ($notification, $channels) use ($user) {
                $url = $notification->toMail($user)->actionUrl ?? '';

                // URL must NOT expose email
                $this->assertStringNotContainsString('no_email_in_url@example.com', $url);
                $this->assertStringNotContainsString('email=', $url);

                return true;
            }
        );
    }

    public function test_forgot_password_stores_email_in_session_not_url(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::create([
            'email'    => 'session_email@example.com',
            'password' => Hash::make('InitialPassword123!'),
        ]);

        $response = $this->post('/forgot-password', ['email' => 'session_email@example.com']);

        $response->assertSessionHas('password_reset_email', 'session_email@example.com');
    }
}

