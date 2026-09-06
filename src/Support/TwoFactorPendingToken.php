<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

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

        $this->cache->put(
            $this->keyFor($token),
            $userId,
            now()->addMinutes($ttlMinutes)
        );

        return $token;
    }

    /**
     * Resolve user id dari token. Mengembalikan null jika token
     * tidak dikenal / sudah kedaluwarsa / sudah dipakai.
     */
    public function resolve(string $token): int|string|null
    {
        $userId = $this->cache->get($this->keyFor($token));

        return $userId === null ? null : $userId;
    }

    /**
     * Konsumsi token (single-use). Panggil setelah verifikasi 2FA sukses
     * ATAU GAGAL total — token tidak boleh dipakai dua kali.
     */
    public function consume(string $token): void
    {
        $this->cache->forget($this->keyFor($token));
    }

    public function keyFor(string $token): string
    {
        return self::CACHE_PREFIX . hash('sha256', $token);
    }
}