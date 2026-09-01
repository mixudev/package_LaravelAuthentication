<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Models\PasskeyCredential;
use Vendor\LaravelAuthentication\Services\PasskeyService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class PasskeyAuthenticationTest extends TestCase
{
    private User $user;
    private PasskeyService $passkeyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Jane Doe',
            'username' => 'janedoe',
            'email'    => 'jane@example.com',
            'password' => Hash::make('SecretPass123!'),
        ]);

        $this->passkeyService = app(PasskeyService::class);
    }

    public function test_generates_creation_options_for_user(): void
    {
        $options = $this->passkeyService->generateCreationOptions($this->user);

        $this->assertNotEmpty($options->challenge);
        $this->assertEquals('Jane Doe', $options->user['displayName']);
        $this->assertEquals('none', $options->attestation);
    }

    public function test_registers_and_authenticates_passkey_successfully(): void
    {
        // 1. Generate creation options
        $options = $this->passkeyService->generateCreationOptions($this->user);
        $challenge = $options->challenge;

        // 2. Mock WebAuthn registration payload
        $credentialId = 'cred_xyz_' . bin2hex(random_bytes(8));
        $clientDataJSON = $this->passkeyService->base64UrlEncode(json_encode([
            'type'      => 'webauthn.create',
            'challenge' => $challenge,
            'origin'    => 'http://localhost',
        ]) ?: '');

        $regPayload = [
            'id'       => $credentialId,
            'rawId'    => $credentialId,
            'type'     => 'public-key',
            'name'     => 'MacBook TouchID',
            'response' => [
                'clientDataJSON'    => $clientDataJSON,
                'attestationObject' => base64_encode('mock_attestation_object'),
                'transports'        => ['internal', 'hybrid'],
            ],
        ];

        $credential = $this->passkeyService->registerPasskey($this->user, $regPayload, 'MacBook TouchID');

        $this->assertInstanceOf(PasskeyCredential::class, $credential);
        $this->assertEquals($credentialId, $credential->credential_id);
        $this->assertEquals($this->user->id, $credential->user_id);

        // 3. Generate authentication request options
        $requestOptions = $this->passkeyService->generateRequestOptions($this->user->email);
        $authChallenge = $requestOptions->challenge;

        // 4. Mock WebAuthn assertion payload
        $authClientDataJSON = $this->passkeyService->base64UrlEncode(json_encode([
            'type'      => 'webauthn.get',
            'challenge' => $authChallenge,
            'origin'    => 'http://localhost',
        ]) ?: '');

        $assertionPayload = [
            'id'       => $credentialId,
            'rawId'    => $credentialId,
            'type'     => 'public-key',
            'response' => [
                'clientDataJSON'    => $authClientDataJSON,
                'authenticatorData' => $this->passkeyService->base64UrlEncode('mock_auth_data'),
                'signature'         => $this->passkeyService->base64UrlEncode('mock_signature'),
            ],
        ];

        $context = new \Vendor\LaravelAuthentication\DTO\AuthenticationContext(
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            channel: \Vendor\LaravelAuthentication\Enums\AuthenticationChannel::WEB,
            guard: 'web'
        );
        $result = $this->passkeyService->authenticate($assertionPayload, $context);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals($this->user->id, $result->user?->getAuthIdentifier());
        $this->assertEquals('passkey', $result->metadata['strategy']);
    }

    public function test_can_list_and_delete_passkeys(): void
    {
        PasskeyCredential::create([
            'user_id'          => $this->user->id,
            'name'             => 'YubiKey 5',
            'credential_id'    => 'cred_yubi_123',
            'public_key'       => 'mock_key',
            'attestation_type' => 'none',
            'sign_count'       => 0,
        ]);

        $passkeys = $this->passkeyService->getUserPasskeys($this->user);
        $this->assertCount(1, $passkeys);
        $this->assertEquals('YubiKey 5', $passkeys->first()->name);

        $deleted = $this->passkeyService->deletePasskey($this->user, $passkeys->first()->id);
        $this->assertTrue($deleted);

        $passkeysAfter = $this->passkeyService->getUserPasskeys($this->user);
        $this->assertCount(0, $passkeysAfter);
    }
}
