<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Red-team test: session revoke-others password brute force.
 *
 * Finding: SessionController::destroyOthers menerima password tanpa rate
 * limit — attacker bisa brute-force password via endpoint ini (tidak seperti
 * confirm-password yang sudah di-limit). Fix: pakai feature rate limiter
 * 'confirm_password' (5 attempts / 5 menit, composite user+IP).
 */
class SessionRevokeOthersRateLimitTest extends TestCase
{
    public function test_revoke_others_rejects_wrong_password_and_rate_limits(): void
    {
        $user = User::create([
            'name'     => 'Session Brute',
            'username' => 'sessionbrute',
            'email'    => 'sessionbrute@example.com',
            'password' => Hash::make('CorrectPass123!'),
        ]);

        $this->actingAs($user);

        // Coba password salah berulang — harus kena throttle setelah max_attempts.
        $maxAttempts = (int) config('authentication.security.rate_limits.confirm_password.max_attempts', 5);
        $throttled = false;

        for ($i = 0; $i < $maxAttempts + 2; $i++) {
            $response = $this->postJson('/api/v1/auth/sessions/revoke-others', [
                'password' => 'WrongPass' . $i . '!',
            ]);

            if ($response->status() === 422) {
                $content = (string) $response->getContent();
                if (str_contains($content, 'throttle_error') || str_contains($content, 'Too many')) {
                    $throttled = true;
                    break;
                }
            }
        }

        // Harus kena throttle sebelum loop selesai — brute-force password dibatasi.
        $this->assertTrue($throttled, 'revoke-others tidak pernah kena throttle — brute force password tidak dibatasi!');
    }

    public function test_revoke_others_succeeds_with_correct_password(): void
    {
        $user = User::create([
            'name'     => 'Session Ok',
            'username' => 'sessionok',
            'email'    => 'sessionok@example.com',
            'password' => Hash::make('CorrectPass123!'),
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/auth/sessions/revoke-others', [
            'password' => 'CorrectPass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'All other sessions revoked successfully.');
    }
}