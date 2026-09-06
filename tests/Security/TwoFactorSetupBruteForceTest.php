<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Red-team test: 2FA setup/destroy brute force.
 *
 * Findings:
 * 1. TwoFactorSetupController::confirm (TOTP setup, 6-digit) tanpa rate limit —
 *    attacker dengan session bisa brute-force TOTP sebelum 2FA aktif.
 * 2. TwoFactorSetupController::destroy (disable 2FA, butuh password) tanpa rate
 *    limit — brute-force password untuk mematikan proteksi 2FA.
 *
 * Fix: keduanya pakai limiter 'confirm_password' (5/1min, per user+IP).
 */
class TwoFactorSetupBruteForceTest extends TestCase
{
    public function test_confirm_2fa_setup_brute_force_is_rate_limited(): void
    {
        $user = User::create([
            'name'     => 'Setup Bruteforce',
            'username' => 'setupbrute',
            'email'    => 'setupbrute@example.com',
            'password' => Hash::make('CorrectPass123!'),
        ]);

        $this->actingAs($user);

        $maxAttempts = (int) config('authentication.security.rate_limits.confirm_password.max_attempts', 5);
        $throttled = false;

        for ($i = 0; $i < $maxAttempts + 2; $i++) {
            $response = $this->postJson('/api/v1/auth/two-factor/confirm', [
                'code' => str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            ]);

            if ($response->status() === 422) {
                $content = (string) $response->getContent();
                if (str_contains($content, 'Too many attempts')) {
                    $throttled = true;
                    break;
                }
            }
        }

        $this->assertTrue($throttled, 'TOTP setup confirm tidak pernah kena throttle!');
    }

    public function test_disable_2fa_brute_force_is_rate_limited(): void
    {
        $user = User::create([
            'name'     => 'Disable Bruteforce',
            'username' => 'disablebrute',
            'email'    => 'disablebrute@example.com',
            'password' => Hash::make('CorrectPass123!'),
        ]);

        $this->actingAs($user);

        $maxAttempts = (int) config('authentication.security.rate_limits.confirm_password.max_attempts', 5);
        $throttled = false;

        for ($i = 0; $i < $maxAttempts + 2; $i++) {
            $response = $this->deleteJson('/api/v1/auth/two-factor/disable', [
                'password' => 'WrongPass' . $i . '!',
            ]);

            if ($response->status() === 422) {
                $content = (string) $response->getContent();
                if (str_contains($content, 'Too many attempts')) {
                    $throttled = true;
                    break;
                }
            }
        }

        $this->assertTrue($throttled, 'Disable 2FA tidak pernah kena throttle — brute-force password tidak dibatasi!');
    }
}