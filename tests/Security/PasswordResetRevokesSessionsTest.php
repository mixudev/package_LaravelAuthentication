<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * PR-01: a password reset must terminate sessions that the old password granted.
 *
 * The reset callback in PasswordResetController only calls
 * PasswordService::updatePassword() and dispatches an event. Nothing revokes
 * existing sessions or rotates the remember-me token, so a session cookie or
 * remember-me token captured before the reset stays valid afterwards. The victim
 * resets the password precisely because they believe they are locking an attacker
 * out, and the package leaves the attacker in.
 *
 * The invariant: after a successful reset, no session or remember-me token issued
 * under the previous credential remains usable.
 */
final class PasswordResetRevokesSessionsTest extends TestCase
{
    private function seedSessionsTable(): void
    {
        Schema::create('sessions', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    private function seedPasswordResetTokensTable(): void
    {
        Schema::create('password_reset_tokens', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPasswordResetTokensTable();

        config(['auth.passwords.users.table' => 'password_reset_tokens']);

        // The replay guard is an atomic cache add(). Assert against the real store
        // behaviour, not a hand-rolled reconstruction of the gate.
        Cache::clear();
    }

    private function createUser(string $email, string $password): User
    {
        return User::create([
            'email'         => $email,
            'username'      => 'reset_' . substr(hash('sha256', $email), 0, 12),
            'password'      => Hash::make($password),
            'remember_token' => 'attacker-remember-token',
        ]);
    }

    private function performReset(User $user, string $newPassword): \Illuminate\Testing\TestResponse
    {
        $token = 'plaintext-reset-token-' . bin2hex(random_bytes(8));

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        return $this->postJson('/api/v1/auth/reset-password', [
            'token'    => $token,
            'email'    => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);
    }

    #[Test]
    public function a_successful_reset_revokes_pre_existing_sessions(): void
    {
        config(['session.driver' => 'database']);
        $this->seedSessionsTable();

        $user = $this->createUser('victim@example.test', 'OriginalPassword123!');

        // A session the attacker already holds, created under the old password.
        DB::table('sessions')->insert([
            'id'            => 'attacker-session-id',
            'user_id'       => $user->id,
            'ip_address'    => '203.0.113.9',
            'user_agent'    => 'attacker',
            'payload'       => base64_encode(serialize(['user_id' => $user->id])),
            'last_activity' => now()->getTimestamp(),
        ]);

        $response = $this->performReset($user, 'BrandNewPassword123!');

        $response->assertStatus(200);

        $this->assertSame(
            0,
            DB::table('sessions')->where('user_id', $user->id)->count(),
            'PR-01 invariant violated: sessions issued under the previous password must not '
            . 'survive a successful reset, otherwise a stolen session outlives the reset'
        );
    }

    #[Test]
    public function a_successful_reset_rotates_the_remember_me_token(): void
    {
        $user = $this->createUser('remembered@example.test', 'OriginalPassword123!');

        $this->assertSame('attacker-remember-token', $user->remember_token);

        $response = $this->performReset($user, 'BrandNewPassword123!');

        $response->assertStatus(200);

        $fresh = $user->fresh();

        $this->assertNotSame(
            'attacker-remember-token',
            $fresh->remember_token,
            'PR-01 invariant violated: the remember-me token must be rotated on reset so a '
            . 'captured "remember me" cookie stops working'
        );
    }

    #[Test]
    public function a_failed_reset_leaves_sessions_untouched(): void
    {
        config(['session.driver' => 'database']);
        $this->seedSessionsTable();

        $user = $this->createUser('untouched@example.test', 'OriginalPassword123!');

        DB::table('sessions')->insert([
            'id'            => 'legit-session-id',
            'user_id'       => $user->id,
            'ip_address'    => '198.51.100.4',
            'user_agent'    => 'victim',
            'payload'       => base64_encode(serialize(['user_id' => $user->id])),
            'last_activity' => now()->getTimestamp(),
        ]);

        // Submit with a token that was never issued.
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => 'never-issued-token',
            'email'                 => $user->email,
            'password'              => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertStatus(400);

        $this->assertSame(
            1,
            DB::table('sessions')->where('user_id', $user->id)->count(),
            'an invalid reset must not revoke the victim\'s own sessions'
        );
    }

    #[Test]
    public function a_reset_token_cannot_be_redeemed_twice(): void
    {
        $user = $this->createUser('single-use@example.test', 'OriginalPassword123!');
        $token = 'single-use-token-' . bin2hex(random_bytes(8));

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $payload = [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'FirstNewPassword123!',
            'password_confirmation' => 'FirstNewPassword123!',
        ];

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(200);

        // A captured/replayed reset request must be refused even though the broker
        // deletes the token only after the callback completes.
        $payload['password'] = 'SecondNewPassword123!';
        $payload['password_confirmation'] = 'SecondNewPassword123!';

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(400);

        $this->assertTrue(
            Hash::check('FirstNewPassword123!', $user->fresh()->password),
            'PR-02 invariant violated: a replayed reset must not overwrite the password'
        );
    }

    #[Test]
    public function a_reset_token_holder_cannot_reset_to_the_current_password(): void
    {
        $user = $this->createUser('unchanged@example.test', 'OriginalPassword123!');

        $response = $this->performReset($user, 'OriginalPassword123!');

        $this->assertNotSame(
            200,
            $response->getStatusCode(),
            'PR-04: resetting to the unchanged current password must be rejected'
        );

        $this->assertTrue(
            Hash::check('OriginalPassword123!', $user->fresh()->password),
            'the original password must remain in effect'
        );
    }

    #[Test]
    public function the_reset_link_is_delivered_and_consumed_once(): void
    {
        Notification::fake();

        $user = $this->createUser('once@example.test', 'OriginalPassword123!');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class);
    }
}