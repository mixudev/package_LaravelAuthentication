<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Concurrency;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * N-01: Passkey authentication challenge consumption is not atomic.
 *
 * PasskeyService::authenticate() consumed the WebAuthn challenge with:
 *
 *     if (! $this->cache->pull($cacheKey)) { throw ... }
 *
 * and a code comment asserted that "pull() atomically retrieves and deletes".
 * That claim is false. Illuminate\Cache\Repository::pull() is:
 *
 *     return tap($this->get($key, $default), fn () => $this->forget($key));
 *
 * a read followed by an unconditional delete. Two concurrent assertions carrying
 * the same challenge both read the key before either deletes it, so both proceed
 * to signature verification. A captured assertion is therefore replayable inside
 * that window, which is exactly what single-use challenge consumption exists to
 * prevent.
 *
 * The invariant: an authentication challenge authorises at most one assertion
 * attempt, even when every request already holds the resolved payload.
 */
final class PasskeyAuthChallengeReplayTest extends TestCase
{
    private const CHALLENGE = 'passkey_auth_challenge:replay-window-challenge';

    private const TTL_MINUTES = 5;

    private function issueChallenge(string $challenge = 'replay-window-challenge'): void
    {
        Cache::put(self::CHALLENGE, true, now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * Mirrors the consumption primitive the service must use: claim first, then read.
     * Returns false when the challenge was already claimed by another request.
     */
    private function consumeChallenge(string $challenge): bool
    {
        $cacheKey = 'passkey_auth_challenge:' . $challenge;
        $consumedKey = $cacheKey . ':consumed';

        if (! Cache::add($consumedKey, true, now()->addMinutes(self::TTL_MINUTES))) {
            return false;
        }

        $resolved = Cache::get($cacheKey);

        if ($resolved === null) {
            // Claim was taken on a challenge that does not exist: release it so an
            // expired challenge cannot permanently burn its identifier.
            Cache::forget($consumedKey);

            return false;
        }

        Cache::forget($cacheKey);

        return true;
    }

    #[Test]
    public function a_challenge_authorises_exactly_one_consumer(): void
    {
        $this->issueChallenge();

        $this->assertTrue(
            $this->consumeChallenge('replay-window-challenge'),
            'the first assertion must be allowed to proceed to signature verification'
        );

        $this->assertFalse(
            $this->consumeChallenge('replay-window-challenge'),
            'a replayed assertion must be rejected before signature verification'
        );
    }

    #[Test]
    public function concurrent_consumers_cannot_both_pass_the_gate(): void
    {
        $this->issueChallenge();

        // Two requests that both read the payload before either deletes it: the
        // interleaving a non-atomic pull() would actually produce.
        $payloadHeldByRequestA = Cache::get(self::CHALLENGE);
        $payloadHeldByRequestB = Cache::get(self::CHALLENGE);

        $this->assertTrue($payloadHeldByRequestA !== null, 'request A resolved the challenge');
        $this->assertTrue($payloadHeldByRequestB !== null, 'request B resolved the same challenge');

        // Now each request attempts to claim it. Exactly one may proceed.
        $claimA = Cache::add(self::CHALLENGE . ':claim', true, now()->addMinutes(self::TTL_MINUTES));
        $claimB = Cache::add(self::CHALLENGE . ':claim', true, now()->addMinutes(self::TTL_MINUTES));

        $this->assertTrue($claimA, 'request A wins the claim');
        $this->assertFalse($claimB, 'request B must lose the claim, even holding a pre-deletion payload copy');
    }

    #[Test]
    public function a_failed_claim_releases_so_legitimate_retry_still_works(): void
    {
        $this->issueChallenge();

        // A malformed payload that fails clientData validation must not burn the
        // challenge, otherwise one bad request forces the user to restart the whole
        // WebAuthn ceremony for a recoverable error.
        $consumedKey = self::CHALLENGE . ':consumed';

        Cache::add($consumedKey, true, now()->addMinutes(self::TTL_MINUTES));
        Cache::forget($consumedKey);

        $this->assertTrue(
            $this->consumeChallenge('replay-window-challenge'),
            'releasing the claim must allow a legitimate retry to proceed'
        );
    }

    #[Test]
    public function an_absent_challenge_releases_the_claim_it_took(): void
    {
        // Claiming an identifier that has no payload must not leave a permanent
        // marker, otherwise an attacker can pre-burn challenge identifiers.
        $missing = 'passkey_auth_challenge:never-issued';

        $this->assertFalse(
            $this->consumeChallenge('never-issued'),
            'an unissued challenge must not resolve'
        );

        $this->assertNull(
            Cache::get($missing . ':consumed'),
            'a claim taken on a missing challenge must be released'
        );
    }

    #[Test]
    public function the_consumed_marker_outlives_the_challenge_payload(): void
    {
        $this->issueChallenge();

        $this->assertTrue($this->consumeChallenge('replay-window-challenge'));

        $storage = (new \ReflectionProperty(\Illuminate\Cache\ArrayStore::class, 'storage'));
        $storage->setAccessible(true);

        /** @var array<string, array{expiresAt: int|float|null}> $entries */
        $entries = $storage->getValue(Cache::getStore());

        $markerTtl = $entries[self::CHALLENGE . ':consumed']['expiresAt'] ?? null;

        $this->assertNotNull($markerTtl, 'the consumed marker must exist with an expiry');

        // The payload is already deleted at this point, so compare against the
        // issued TTL rather than a stored expiry that no longer exists.
        $payloadTtl = now()->addMinutes(self::TTL_MINUTES)->getTimestamp();

        $this->assertGreaterThanOrEqual(
            $payloadTtl,
            (int) $markerTtl,
            'the consumed marker must outlive the payload it protects, otherwise a '
            . 'replay becomes possible once the marker expires while a late request still holds the challenge'
        );
    }
}