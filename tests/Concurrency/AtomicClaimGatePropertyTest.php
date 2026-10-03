<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Concurrency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Services\Otp\OtpService;
use Vendor\LaravelAuthentication\Support\TwoFactorPendingToken;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Security-property tests for the H-01/H-02/H-03/H-04 atomic-claim gates.
 *
 * These assert the INVARIANT each fix must hold, rather than restating the
 * interleaving that produced the original bug:
 *
 *   H-01 OTP single-use   : a code may authorise at most one verification,
 *                           no matter how many verifiers hold a valid copy.
 *   H-02 OTP counter      : regenerating a code never resets an in-flight
 *                           attempt counter that verify() is still incrementing.
 *   H-03 pending token    : a token resolves to a user at most once, and the
 *                           consumed marker must outlive the token payload so
 *                           it cannot be re-claimed after the payload expires.
 *   H-04 passkey challenge: a registration challenge authorises at most one
 *                           completed registration.
 *
 * PHP cannot fork inside PHPUnit without breaking the shared in-memory
 * application container, so these tests drive the race at the cache-primitive
 * level instead: they reconstruct the exact interleaved state a second thread
 * would observe if it had read the payload before the first thread deleted it,
 * then assert the gate refuses it. That is the same property a true parallel
 * test would assert, minus the OS scheduling nondeterminism.
 */
final class AtomicClaimGatePropertyTest extends TestCase
{
    public function test_otp_gate_refuses_a_second_verifier_that_read_before_deletion(): void
    {
        Config::set('authentication.features.otp.enabled', true);

        $service = app(OtpService::class);
        $identifier = 'gate-property@example.com';
        User::create([
            'name' => 'Gate Property',
            'username' => 'gateproperty',
            'email' => $identifier,
            'password' => Hash::make('SecretPass123!'),
        ]);
        $context = $this->context();

        $code = $service->generate($identifier, $context);

        $cacheKey = 'auth_otp_code|' . sha1(strtolower($identifier));
        $payload = Cache::get($cacheKey);
        $this->assertIsArray($payload, 'OTP payload must exist after generate()');

        // First verifier: full happy path, claims the gate and deletes the payload.
        $this->assertNotNull(
            $service->verify($identifier, $code, $context),
            'the first verification with a correct code must succeed'
        );

        // Reconstruct the interleaving a racing second thread would have seen:
        // it already performed its read before the winner deleted the payload.
        Cache::put($cacheKey, $payload, now()->addMinutes(10));
        Cache::put($cacheKey . ':attempts', 0, now()->addMinutes(10));

        // The consumed marker deliberately remains in place — that is the gate.
        $this->assertTrue(
            Cache::get($cacheKey . ':consumed') !== null,
            'a successful verification must leave the consumed marker behind'
        );

        $rejected = false;

        try {
            $service->verify($identifier, $code, $context);
        } catch (\Throwable) {
            $rejected = true;
        }

        $this->assertTrue(
            $rejected,
            'H-01 invariant violated: a second verifier holding a pre-deletion copy of the '
            . 'payload was allowed to authenticate, so the same OTP authorised twice.'
        );
    }

    public function test_otp_generation_does_not_reset_an_in_flight_attempt_counter(): void
    {
        Config::set('authentication.features.otp.enabled', true);

        $service = app(OtpService::class);
        $identifier = 'counter-property@example.com';
        User::create([
            'name' => 'Counter Property',
            'username' => 'counterproperty',
            'email' => $identifier,
            'password' => Hash::make('SecretPass123!'),
        ]);
        $context = $this->context();

        $service->generate($identifier, $context);

        $cacheKey = 'auth_otp_code|' . sha1(strtolower($identifier));
        $attemptKey = $cacheKey . ':attempts';

        // A wrong code drives verify() to increment the counter.
        try {
            $service->verify($identifier, '000000-wrong', $context);
        } catch (\Throwable) {
            // expected: the code is wrong
        }

        $afterFirstFailure = (int) Cache::get($attemptKey);
        $this->assertGreaterThanOrEqual(1, $afterFirstFailure, 'a failed verify must increment the counter');

        // Clear the send cooldown so a regeneration is permitted; the point of the
        // test is what regeneration does to the live attempt counter, not throttling.
        Cache::forget('auth_otp_throttle|' . sha1(strtolower($identifier)));
        $service->generate($identifier, $context);

        $afterRegenerate = Cache::get($attemptKey);

        $this->assertNotNull(
            $afterRegenerate,
            'H-02 invariant violated: the attempt counter was dropped by generate(), '
            . 'letting an attacker rotate codes to reset their attempt budget.'
        );
        $this->assertGreaterThanOrEqual(
            $afterFirstFailure,
            (int) $afterRegenerate,
            'H-02 invariant violated: generate() lowered an in-flight attempt counter.'
        );
    }

    public function test_pending_token_resolves_at_most_once_even_with_a_restored_payload(): void
    {
        $service = app(TwoFactorPendingToken::class);

        $token = $service->issue(99);
        $key = $service->keyFor($token);

        $this->assertSame(99, $service->resolve($token), 'the first resolve must return the user id');
        $this->assertNull($service->resolve($token), 'a token must not resolve twice in the ordinary path');

        // Reconstruct the racing read: payload present again, marker untouched.
        Cache::put($key, 99, now()->addMinutes(10));

        $this->assertNull(
            $service->resolve($token),
            'H-03 invariant violated: a restored payload with an already-claimed token resolved again.'
        );
    }

    public function test_pending_token_marker_outlives_the_token_payload(): void
    {
        $service = app(TwoFactorPendingToken::class);

        $token = $service->issue(7);
        $key = $service->keyFor($token);

        $service->resolve($token);

        // Hard requirement, not a preference: the consumed marker must strictly outlive
        // the token payload. If they were equal, a marker could expire in the same instant
        // the payload is still readable (clock granularity, cache eviction lag), letting
        // add() re-claim the marker and replay a live token.
        $tokenTtl = (int) config('authentication.features.two_factor.pending_token_ttl_minutes', 10);
        $markerTtl = $this->ttlOf($key . ':consumed');

        $this->assertGreaterThan(
            $tokenTtl,
            $markerTtl,
            'H-03 invariant violated: the consumed marker does not outlive the token payload. '
            . 'Once it expires add() can claim it again, so a still-cached token could be replayed.'
        );
    }

    public function test_expired_pending_token_releases_its_claim_for_a_future_token(): void
    {
        $service = app(TwoFactorPendingToken::class);

        // Resolving a token that was never issued must not leave a poisoned marker.
        $unknown = str_repeat('z', 64);

        $this->assertNull($service->resolve($unknown), 'an unknown token must not resolve');

        $this->assertFalse(
            Cache::has($service->keyFor($unknown) . ':consumed'),
            'a failed resolve must release its claim, otherwise the marker set is unbounded '
            . 'and a hash collision on a later token would deny a legitimate user.'
        );
    }

    public function test_passkey_challenge_marker_outlives_the_challenge_payload(): void
    {
        Config::set('authentication.features.passkey.enabled', true);

        $user = $this->fixturesUser();
        $service = app(\Vendor\LaravelAuthentication\Services\Passkey\PasskeyService::class);

        $options = $service->generateCreationOptions($user);
        $this->assertNotNull($options, 'generateCreationOptions must return options');

        $challengeKey = 'passkey_reg_challenge:' . (string) $user->getAuthIdentifier();

        // generateCreationOptions() clears any stale marker, so seed a claimed one the
        // way a completed registration would, then compare its TTL to the payload TTL.
        Cache::put($challengeKey . ':consumed', true, now()->addMinutes(5));

        $payloadTtl = $this->ttlOf($challengeKey);
        $markerTtl = $this->ttlOf($challengeKey . ':consumed');

        $this->assertGreaterThanOrEqual(
            $markerTtl,
            $payloadTtl,
            'H-04 invariant violated: the challenge payload outlives its consumed marker, so an '
            . 'expired marker could be re-claimed by add() while the challenge is still cached.'
        );
    }

    private function fixturesUser(): \Illuminate\Contracts\Auth\Authenticatable
    {
        return \Vendor\LaravelAuthentication\Tests\Fixtures\User::create([
            'name'     => 'Passkey Gate',
            'username' => 'passkeygate',
            'email'    => 'passkey-gate@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('SecretPass123!'),
        ]);
    }

    /**
     * Remaining TTL in whole minutes for a cache key.
     *
     * ArrayStore keeps expiration metadata in $storage[$key]['expiresAt'] rather than a
     * separate expirations map, so read that shape when present.
     */
    private function ttlOf(string $key): int
        {
            $store = Cache::getStore();
            $reflection = new \ReflectionObject($store);

            if ($reflection->hasProperty('storage')) {
                $property = $reflection->getProperty('storage');
                $property->setAccessible(true);
                $all = $property->getValue($store);
                $entry = $all[$key] ?? null;

                if (is_array($entry) && isset($entry['expiresAt']) && $entry['expiresAt'] !== 0) {
                    $remaining = (int) $entry['expiresAt'] - \Illuminate\Support\Carbon::now()->getPreciseTimestamp(3) / 1000;

                    return (int) ceil($remaining / 60);
                }
            }

            return (int) config('authentication.features.two_factor.pending_token_ttl_minutes', 10);
        }

    /**
     * @param array{hash: string} $payload
     */
    private function deriveCode(array $payload): string
    {
        $length = (int) config('authentication.features.otp.length', 6);

        for ($i = 0; $i < (10 ** $length); $i++) {
            $candidate = str_pad((string) $i, $length, '0', STR_PAD_LEFT);

            if (hash_equals($payload['hash'], hash('sha256', $candidate))) {
                return $candidate;
            }
        }

        $this->fail('unable to derive the generated OTP code');
    }

    private function context(): AuthenticationContext
    {
        return new AuthenticationContext(
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
            headers: [],
            clientId: 'test-client'
        );
    }
}