<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Red-team test: passkey loginOptions cache-flooding protection.
 *
 * Finding: tiap request ke loginOptions menyimpan challenge (32-byte base64)
 * di cache dengan TTL 5 menit. Tanpa rate limit, attacker bisa membanjiri
 * cache store (DoS). Fix: feature rate limiter 'passkeys' (60/min per IP).
 */
class PasskeyRateLimitTest extends TestCase
{
    public function test_passkey_login_options_are_rate_limited_per_ip(): void
    {
        $maxAttempts = (int) config('authentication.security.rate_limits.passkeys.max_attempts', 60);
        $throttled = false;

        // Loop lebih banyak dari max — harus kena 429.
        for ($i = 0; $i < $maxAttempts + 5; $i++) {
            $response = $this->postJson('/api/v1/auth/passkey/login-options', []);

            if ($response->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled, 'Passkey loginOptions tidak pernah kena rate limit — cache flooding mungkin!');
    }

    public function test_passkey_login_options_ok_before_limit(): void
    {
        $response = $this->postJson('/api/v1/auth/passkey/login-options', []);

        // Sebelum limit: 200 dengan challenge + rpId.
        $response->assertOk()
            ->assertJsonStructure(['challenge', 'rpId', 'timeout']);
    }
}