<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Concurrency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Vendor\LaravelAuthentication\Services\Passkey\PasskeyService;
use Vendor\LaravelAuthentication\Support\WebAuthn\WebAuthnHelper;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * H-04: Passkey Registration Challenge Replay Race Condition
 *
 * Concurrent passkey registration requests can both consume the same challenge
 * because registerPasskey() previously used get() + forget() (non-atomic).
 *
 * Fix: Atomic add() claim before get() prevents concurrent consumption.
 */
class PasskeyRegistrationRaceTest extends TestCase
{
    public function test_concurrent_registration_only_one_succeeds(): void
    {
        Config::set('authentication.features.passkey.enabled', true);

        $user = User::create([
            'name'     => 'Passkey User',
            'username' => 'passkeyuser',
            'email'    => 'passkey-race@example.com',
            'password' => bcrypt('password'),
        ]);

        /** @var PasskeyService $passkeyService */
        $passkeyService = app(PasskeyService::class);

        // Generate registration options (challenge)
        $options = $passkeyService->generateCreationOptions($user);
        $challenge = $options->challenge;

        // Generate genuine EC key pair for valid payload
        $ecKey = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $this->assertNotFalse($ecKey);
        $details = openssl_pkey_get_details($ecKey);
        $this->assertIsArray($details);
        $publicKeyPem = $details['key'];

        $credentialId = 'cred-race-' . bin2hex(random_bytes(8));
        $clientDataJSON = WebAuthnHelper::base64UrlEncode(json_encode([
            'type'      => 'webauthn.create',
            'challenge' => $challenge,
            'origin'    => 'http://localhost',
        ]) ?: '');

        $regPayload = [
            'id'       => $credentialId,
            'rawId'    => $credentialId,
            'type'     => 'public-key',
            'name'     => 'Race Test Key',
            'response' => [
                'clientDataJSON' => $clientDataJSON,
                'publicKey'      => WebAuthnHelper::base64UrlEncode($publicKeyPem),
                'transports'     => ['internal'],
            ],
        ];

        $results = [];
        $exceptions = [];

        // Request 1: register
        try {
            $credential1 = $passkeyService->registerPasskey($user, $regPayload, 'Key 1');
            $results[] = $credential1 !== null ? 'success' : 'fail';
        } catch (\Throwable $e) {
            $exceptions[] = get_class($e) . ': ' . substr($e->getMessage(), 0, 80);
            $results[] = 'exception';
        }

        // RACE SIMULATION: Restore challenge to simulate concurrent get() before forget()
        $userId = (string) $user->getAuthIdentifier();
        $challengeKey = "passkey_reg_challenge:{$userId}";
        $consumedKey = $challengeKey . ':consumed';
        Cache::put($challengeKey, $challenge, now()->addMinutes(5));
        // Do NOT clear the consumed marker — this proves the atomic gate works
        // If consumed marker is still set, second request must fail

        // Request 2: same challenge (should fail due to atomic consumed gate)
        try {
            $credential2 = $passkeyService->registerPasskey($user, $regPayload, 'Key 2');
            $results[] = $credential2 !== null ? 'success' : 'fail';
        } catch (\Throwable $e) {
            $exceptions[] = get_class($e) . ': ' . substr($e->getMessage(), 0, 80);
            $results[] = 'exception';
        }

        // ASSERTION: Only one registration should succeed
        // With fix (add() gate), second request fails because consumed marker still exists
        $successCount = count(array_filter($results, fn($r) => $r === 'success'));

        $this->assertEquals(
            1,
            $successCount,
            'Expected exactly 1 successful passkey registration, got ' . $successCount . '. '
            . 'Results: ' . implode(', ', $results) . '. '
            . 'Exceptions: ' . implode(' | ', $exceptions)
        );
    }
}
