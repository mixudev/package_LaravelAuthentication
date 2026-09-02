<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Models\PasskeyCredential;
use Vendor\LaravelAuthentication\Services\Passkey\PasskeyService;
use Vendor\LaravelAuthentication\Support\WebAuthn\WebAuthnHelper;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class PasskeyForgeryTest extends TestCase
{
    private User $user;
    private PasskeyService $passkeyService;
    private mixed $privateKey;
    private string $publicKeyPem;
    private string $credentialId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Security Target',
            'username' => 'sectarget',
            'email'    => 'target@example.com',
            'password' => Hash::make('SuperSecret123!'),
        ]);

        $this->passkeyService = app(PasskeyService::class);

        // Generate genuine EC P-256 key pair for security assertions
        $res = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $this->privateKey = $res;
        $details = openssl_pkey_get_details($res);
        $this->assertIsArray($details);
        $this->publicKeyPem = $details['key'];
        $this->credentialId = 'cred_sec_' . bin2hex(random_bytes(8));
    }

    private function createValidAuthData(string $rpId = 'localhost', int $flags = 0x05, int $signCount = 1): string
    {
        $rpIdHash = hash('sha256', $rpId, true);
        $flagsByte = chr($flags); // UP (0x01) + UV (0x04)
        $signCountBytes = pack('N', $signCount);

        return $rpIdHash . $flagsByte . $signCountBytes;
    }

    public function test_rejects_arbitrary_or_unparseable_public_key_on_registration(): void
    {
        $options = $this->passkeyService->generateCreationOptions($this->user);
        $clientDataJSON = WebAuthnHelper::base64UrlEncode(json_encode([
            'type'      => 'webauthn.create',
            'challenge' => $options->challenge,
            'origin'    => 'http://localhost',
        ]) ?: '');

        $payload = [
            'id'       => $this->credentialId,
            'rawId'    => $this->credentialId,
            'response' => [
                'clientDataJSON'    => $clientDataJSON,
                'attestationObject' => 'malicious_arbitrary_garbage_data',
            ],
        ];

        $this->expectException(AuthenticationException::class);
        $this->passkeyService->registerPasskey($this->user, $payload, 'Malicious Key');
    }

    public function test_rejects_forged_or_invalid_cryptographic_signature(): void
    {
        // 1. Register legitimate credential
        PasskeyCredential::create([
            'user_id'          => $this->user->id,
            'name'             => 'Legit Authenticator',
            'credential_id'    => $this->credentialId,
            'public_key'       => $this->publicKeyPem,
            'attestation_type' => 'none',
            'sign_count'       => 0,
        ]);

        // 2. Request authentication challenge
        $options = $this->passkeyService->generateRequestOptions();
        $authData = $this->createValidAuthData();
        $clientDataRaw = json_encode([
            'type'      => 'webauthn.get',
            'challenge' => $options->challenge,
            'origin'    => 'http://localhost',
        ]) ?: '';

        // 3. Forged signature (garbage bytes)
        $forgedSignature = WebAuthnHelper::base64UrlEncode('fake_signature_bytes_12345678901234567890');

        $assertionPayload = [
            'id'       => $this->credentialId,
            'rawId'    => $this->credentialId,
            'response' => [
                'clientDataJSON'    => WebAuthnHelper::base64UrlEncode($clientDataRaw),
                'authenticatorData' => WebAuthnHelper::base64UrlEncode($authData),
                'signature'         => $forgedSignature,
            ],
        ];

        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit', AuthenticationChannel::WEB, 'web');

        $this->expectException(InvalidCredentialsException::class);
        $this->passkeyService->authenticate($assertionPayload, $context);
    }

    public function test_rejects_modified_authenticator_data_after_signing(): void
    {
        PasskeyCredential::create([
            'user_id'          => $this->user->id,
            'name'             => 'Legit Authenticator',
            'credential_id'    => $this->credentialId,
            'public_key'       => $this->publicKeyPem,
            'attestation_type' => 'none',
            'sign_count'       => 0,
        ]);

        $options = $this->passkeyService->generateRequestOptions();
        $originalAuthData = $this->createValidAuthData();
        $clientDataRaw = json_encode([
            'type'      => 'webauthn.get',
            'challenge' => $options->challenge,
            'origin'    => 'http://localhost',
        ]) ?: '';

        // Sign over original auth data
        $signedData = $originalAuthData . hash('sha256', $clientDataRaw, true);
        openssl_sign($signedData, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        // Attacker tampers with auth data (e.g. changes sign count or flags)
        $tamperedAuthData = $this->createValidAuthData('localhost', 0x05, 9999);

        $assertionPayload = [
            'id'       => $this->credentialId,
            'rawId'    => $this->credentialId,
            'response' => [
                'clientDataJSON'    => WebAuthnHelper::base64UrlEncode($clientDataRaw),
                'authenticatorData' => WebAuthnHelper::base64UrlEncode($tamperedAuthData),
                'signature'         => WebAuthnHelper::base64UrlEncode($signature),
            ],
        ];

        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit', AuthenticationChannel::WEB, 'web');

        $this->expectException(InvalidCredentialsException::class);
        $this->passkeyService->authenticate($assertionPayload, $context);
    }

    public function test_rejects_wrong_rp_id_hash(): void
    {
        PasskeyCredential::create([
            'user_id'          => $this->user->id,
            'name'             => 'Legit Authenticator',
            'credential_id'    => $this->credentialId,
            'public_key'       => $this->publicKeyPem,
            'attestation_type' => 'none',
            'sign_count'       => 0,
        ]);

        $options = $this->passkeyService->generateRequestOptions();
        // Auth data signed for an attacker domain 'evil.com'
        $evilAuthData = $this->createValidAuthData('evil.com');
        $clientDataRaw = json_encode([
            'type'      => 'webauthn.get',
            'challenge' => $options->challenge,
            'origin'    => 'http://localhost',
        ]) ?: '';

        $signedData = $evilAuthData . hash('sha256', $clientDataRaw, true);
        openssl_sign($signedData, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        $assertionPayload = [
            'id'       => $this->credentialId,
            'rawId'    => $this->credentialId,
            'response' => [
                'clientDataJSON'    => WebAuthnHelper::base64UrlEncode($clientDataRaw),
                'authenticatorData' => WebAuthnHelper::base64UrlEncode($evilAuthData),
                'signature'         => WebAuthnHelper::base64UrlEncode($signature),
            ],
        ];

        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit', AuthenticationChannel::WEB, 'web');

        $this->expectException(InvalidCredentialsException::class);
        $this->passkeyService->authenticate($assertionPayload, $context);
    }

    public function test_rejects_replayed_challenge(): void
    {
        PasskeyCredential::create([
            'user_id'          => $this->user->id,
            'name'             => 'Legit Authenticator',
            'credential_id'    => $this->credentialId,
            'public_key'       => $this->publicKeyPem,
            'attestation_type' => 'none',
            'sign_count'       => 0,
        ]);

        $options = $this->passkeyService->generateRequestOptions();
        $authData = $this->createValidAuthData();
        $clientDataRaw = json_encode([
            'type'      => 'webauthn.get',
            'challenge' => $options->challenge,
            'origin'    => 'http://localhost',
        ]) ?: '';

        $signedData = $authData . hash('sha256', $clientDataRaw, true);
        openssl_sign($signedData, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        $assertionPayload = [
            'id'       => $this->credentialId,
            'rawId'    => $this->credentialId,
            'response' => [
                'clientDataJSON'    => WebAuthnHelper::base64UrlEncode($clientDataRaw),
                'authenticatorData' => WebAuthnHelper::base64UrlEncode($authData),
                'signature'         => WebAuthnHelper::base64UrlEncode($signature),
            ],
        ];

        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit', AuthenticationChannel::WEB, 'web');

        // First attempt succeeds
        $result = $this->passkeyService->authenticate($assertionPayload, $context);
        $this->assertTrue($result->isSuccess());

        // Replay attempt fails immediately as challenge was invalidated
        $this->expectException(InvalidCredentialsException::class);
        $this->passkeyService->authenticate($assertionPayload, $context);
    }

    public function test_rejects_missing_user_present_flag(): void
    {
        PasskeyCredential::create([
            'user_id'          => $this->user->id,
            'name'             => 'Legit Authenticator',
            'credential_id'    => $this->credentialId,
            'public_key'       => $this->publicKeyPem,
            'attestation_type' => 'none',
            'sign_count'       => 0,
        ]);

        $options = $this->passkeyService->generateRequestOptions();
        // Flag with UP bit (0x01) cleared = 0x00
        $noUpAuthData = $this->createValidAuthData('localhost', 0x00);
        $clientDataRaw = json_encode([
            'type'      => 'webauthn.get',
            'challenge' => $options->challenge,
            'origin'    => 'http://localhost',
        ]) ?: '';

        $signedData = $noUpAuthData . hash('sha256', $clientDataRaw, true);
        openssl_sign($signedData, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        $assertionPayload = [
            'id'       => $this->credentialId,
            'rawId'    => $this->credentialId,
            'response' => [
                'clientDataJSON'    => WebAuthnHelper::base64UrlEncode($clientDataRaw),
                'authenticatorData' => WebAuthnHelper::base64UrlEncode($noUpAuthData),
                'signature'         => WebAuthnHelper::base64UrlEncode($signature),
            ],
        ];

        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit', AuthenticationChannel::WEB, 'web');

        $this->expectException(InvalidCredentialsException::class);
        $this->passkeyService->authenticate($assertionPayload, $context);
    }

    public function test_detects_cloned_authenticator_sign_count_regression(): void
    {
        PasskeyCredential::create([
            'user_id'          => $this->user->id,
            'name'             => 'Legit Authenticator',
            'credential_id'    => $this->credentialId,
            'public_key'       => $this->publicKeyPem,
            'attestation_type' => 'none',
            'sign_count'       => 50, // Stored count is 50
        ]);

        $options = $this->passkeyService->generateRequestOptions();
        // Incoming count is 40 (less than stored 50 -> cloned or replayed)
        $regressedAuthData = $this->createValidAuthData('localhost', 0x05, 40);
        $clientDataRaw = json_encode([
            'type'      => 'webauthn.get',
            'challenge' => $options->challenge,
            'origin'    => 'http://localhost',
        ]) ?: '';

        $signedData = $regressedAuthData . hash('sha256', $clientDataRaw, true);
        openssl_sign($signedData, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        $assertionPayload = [
            'id'       => $this->credentialId,
            'rawId'    => $this->credentialId,
            'response' => [
                'clientDataJSON'    => WebAuthnHelper::base64UrlEncode($clientDataRaw),
                'authenticatorData' => WebAuthnHelper::base64UrlEncode($regressedAuthData),
                'signature'         => WebAuthnHelper::base64UrlEncode($signature),
            ],
        ];

        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit', AuthenticationChannel::WEB, 'web');

        $this->expectException(InvalidCredentialsException::class);
        $this->passkeyService->authenticate($assertionPayload, $context);
    }
}
