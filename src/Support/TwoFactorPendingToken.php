<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use SensitiveParameter;

/**
 * TwoFactorPendingToken
 *
 * Opaque single-use token untuk alur API 2FA challenge (stateless).
 *
 * Alur:
 * 1. Login API (username/password, OTP, social) menemukan user ber-2FA aktif
 *    dan device belum trusted → buat pending token acak 64-char, simpan
 *    user_id di cache dengan key hash(token), TTL dari config.
 * 2. Client kirim pending_token ke POST /two-factor/verify.
 * 3. Controller resolve user_id dari cache → verifikasi kode → HAPUS cache
 *    (single-use, anti-replay).
 *
 * Keamanan:
 * - Token opaque (Str::random(64)) — tidak berisi user_id yang bisa dimanipulasi.
 * - Hanya hash token yang jadi cache key — token asli tidak pernah di-log.
 * - TTL pendek (default 10 menit) membatasi window serangan.
 * - Satu verifikasi gagal → token tetap valid sampai TTL, tapi rate limiter
 *   per user_id membatasi percobaan tebak kode 2FA.
 */
final class TwoFactorPendingToken
{
    /**
     * Cache key prefix untuk pending tokens.
     */
    private const CACHE_PREFIX = '2fa.pending.';

    public function __construct(
        private readonly CacheRepository $cache
    ) {}

    /**
     * Buat pending token baru untuk user id.
     */
    public function issue(int|string $userId): string
    {
        $token = \Illuminate\Support\Str::random(64);
        $ttlMinutes = (int) config('authentication.features.two_factor.pending_token_ttl_minutes', 10);

        $key = $this->keyFor($token);
        $this->cache->put($key, $userId, now()->addMinutes($ttlMinutes));

        return $token;
    }

    /**
     * Resolve user id without consuming the token.
     *
     * The challenge may be retried after a mistyped code. Consumption happens
     * only after the challenge succeeds; the short TTL and challenge limiter
     * bound the replay window.
     */
    public function resolve(#[SensitiveParameter] string $token): int|string|null
    {
        $key = $this->keyFor($token);
        $ttlMinutes = max(1, (int) config('authentication.features.two_factor.pending_token_ttl_minutes', 10));

        // Claim before verification so concurrent requests cannot both complete the
        // challenge. The claim is released on an invalid code, allowing legitimate
        // retries, and remains after success to prevent replay of restored payloads.
        if (!$this->cache->add($key . ':consumed', true, now()->addMinutes($ttlMinutes + 1))) {
            return null;
        }

        $userId = $this->cache->get($key);
        if ($userId === null) {
            $this->cache->forget($key . ':consumed');
            return null;
        }

        return is_int($userId) || is_string($userId) ? $userId : null;
    }

    /** Release a claim after a failed challenge verification. */
    public function release(#[SensitiveParameter] string $token): void
    {
        $key = $this->keyFor($token);
        if ($this->cache->has($key)) {
            $this->cache->forget($key . ':consumed');
        }
    }

    /**
     * Consume a successfully verified pending token.
     */
    public function consume(#[SensitiveParameter] string $token): void
    {
        $this->cache->forget($this->keyFor($token));
    }

    public function keyFor(#[SensitiveParameter] string $token): string
    {
        return self::CACHE_PREFIX . hash('sha256', $token);
    }
}