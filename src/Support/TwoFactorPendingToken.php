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

        // H-03: Clear any stale consumed marker from previous token with same hash (extremely rare)
        $this->cache->forget($key . ':consumed');

        return $token;
    }

    /**
     * Resolve user id dari token dan consume atomically (single-use).
     *
     * H-03 FIX: Use cache->add() gate to ensure only one concurrent request
     * can successfully resolve. This prevents replay attacks where multiple
     * requests resolve the same token before any consume() is called.
     */
    public function resolve(#[SensitiveParameter] string $token): int|string|null
    {
        $key = $this->keyFor($token);
        $consumedKey = $key . ':consumed';

        // Atomic claim: only first concurrent resolver succeeds
        if (!$this->cache->add($consumedKey, true, now()->addMinutes(15))) {
            return null; // Already consumed or concurrent resolution in progress
        }

        $userId = $this->cache->get($key);

        if ($userId === null) {
            // Token tidak ada/expired, hapus consumed marker
            $this->cache->forget($consumedKey);
            return null;
        }

        // Immediately consume to prevent any subsequent use
        $this->cache->forget($key);

        return $userId;
    }

    /**
     * Konsumsi token (single-use).
     *
     * DEPRECATED after H-03 fix: resolve() now consumes atomically.
     * Kept for backward compatibility but is now a no-op.
     */
    public function consume(#[SensitiveParameter] string $token): void
    {
        // No-op: resolve() already consumed the token atomically
        // Kept for backward compatibility with existing controller code
    }

    public function keyFor(#[SensitiveParameter] string $token): string
    {
        return self::CACHE_PREFIX . hash('sha256', $token);
    }
}