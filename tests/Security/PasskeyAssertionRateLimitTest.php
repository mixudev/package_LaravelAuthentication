<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\TestCase;

class PasskeyAssertionRateLimitTest extends TestCase
{
    public function test_passkey_login_assertion_is_rate_limited(): void
    {
        $throttled = false;

        for ($i = 0; $i < 20; $i++) {
            $response = $this->postJson('/api/v1/auth/passkey/login', [
                'id'    => base64_encode('cred-id-' . $i),
                'type'  => 'public-key',
                'rawId' => base64_encode('cred-id-' . $i),
                'response' => [
                    'clientDataJSON'    => base64_encode('{}'),
                    'authenticatorData' => base64_encode('auth'),
                    'signature'         => base64_encode('sig'),
                    'userHandle'        => null,
                ],
            ]);

            if ($response->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled, 'PasskeyController::login has no rate limit — unlimited WebAuthn crypto verification.');
    }
}
