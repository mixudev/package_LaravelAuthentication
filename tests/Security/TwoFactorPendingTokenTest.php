<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Cache;
use Vendor\LaravelAuthentication\Support\TwoFactorPendingToken;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Red-team test: alur 2FA pending token (stateless API 2FA challenge).
 *
 * Attack surface:
 * 1. Token harus opaque — tidak boleh menebak / memanipulasi user_id dari token.
 * 2. Token single-use — replay token setelah sukses HARUS gagal.
 * 3. TTL configurable — set pendek di config, token kedaluwarsa lebih cepat.
 * 4. Hanya hash token yang jadi cache key — token asli tidak pernah jadi key.
 * 5. Resolve token yang tidak dikenal / sudah dipakai / kedaluwarsa = null.
 */
class TwoFactorPendingTokenTest extends TestCase
{
    private TwoFactorPendingToken $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = app(TwoFactorPendingToken::class);
    }

    public function test_issue_returns_opaque_token_not_containing_user_id(): void
    {
        $token = $this->service->issue(42);

        // Opaque: 64 char acak, TIDAK boleh mengandung "42" / user id.
        $this->assertSame(64, strlen($token));
        $this->assertStringNotContainsString('42', $token);
    }

    public function test_resolve_returns_user_id_for_valid_token(): void
    {
        $token = $this->service->issue(1337);

        $this->assertSame(1337, $this->service->resolve($token));
    }

    public function test_resolve_returns_null_for_unknown_token(): void
    {
        $this->assertNull($this->service->resolve('this-token-was-never-issued'));
    }

    public function test_consume_makes_token_single_use_and_replay_fails(): void
    {
        $token = $this->service->issue(7);
        $this->assertSame(7, $this->service->resolve($token));

        $this->service->consume($token);

        // Replay attack: token yang sudah dikonsumsi harus null.
        $this->assertNull($this->service->resolve($token));
    }

    public function test_token_expires_when_config_ttl_is_short(): void
    {
        // Set TTL 0 menit → token langsung kedaluwarsa.
        config(['authentication.features.two_factor.pending_token_ttl_minutes' => 0]);

        $token = $this->service->issue(99);

        $this->assertNull($this->service->resolve($token));
    }

    public function test_ttl_is_configurable(): void
    {
        config(['authentication.features.two_factor.pending_token_ttl_minutes' => 5]);

        $token = $this->service->issue(5);

        // Masih valid
        $this->assertSame(5, $this->service->resolve($token));
    }

    public function test_cache_key_uses_sha256_hash_of_token_not_raw_token(): void
    {
        $token = $this->service->issue(42);

        $rawKeyExists   = Cache::has('2fa.pending.' . $token);
        $hashedKeyExists = Cache::has('2fa.pending.' . hash('sha256', $token));

        $this->assertFalse($rawKeyExists, 'Token asli TIDAK boleh jadi cache key.');
        $this->assertTrue($hashedKeyExists, 'Hash token yang jadi cache key.');
    }

    public function test_two_different_tokens_do_not_collide(): void
    {
        $tokenA = $this->service->issue(1);
        $tokenB = $this->service->issue(2);

        $this->assertSame(1, $this->service->resolve($tokenA));
        $this->assertSame(2, $this->service->resolve($tokenB));
    }
}