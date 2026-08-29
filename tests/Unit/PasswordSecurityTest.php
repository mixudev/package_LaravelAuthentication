<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use Illuminate\Hashing\BcryptHasher;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Vendor\LaravelAuthentication\Support\SecurityHelper;

class PasswordSecurityTest extends BaseTestCase
{
    public function test_hashing_and_verification_is_secure(): void
    {
        $hasher = new BcryptHasher(['rounds' => 4]);
        $plainPassword = 'SecretPassword123!';
        $hash = $hasher->make($plainPassword);

        $this->assertNotEquals($plainPassword, $hash);
        $this->assertTrue($hasher->check($plainPassword, $hash));
        $this->assertFalse($hasher->check('WrongPassword!', $hash));
    }

    public function test_security_helper_redacts_sensitive_keys(): void
    {
        $data = [
            'username' => 'alice',
            'password' => 'SuperSecret',
            'token'    => 'raw_jwt_token',
            'nested'   => [
                'api_token' => 'nested_secret',
                'role'      => 'admin',
            ],
        ];

        $redacted = SecurityHelper::redactSensitive($data);

        $this->assertEquals('alice', $redacted['username']);
        $this->assertEquals('[REDACTED]', $redacted['password']);
        $this->assertEquals('[REDACTED]', $redacted['token']);
        $this->assertEquals('[REDACTED]', $redacted['nested']['api_token']);
        $this->assertEquals('admin', $redacted['nested']['role']);
    }

    public function test_security_helper_masks_identifiers(): void
    {
        $this->assertEquals('j*****e@example.com', SecurityHelper::maskIdentifier('johndoe@example.com'));
        $this->assertEquals('a***n', SecurityHelper::maskIdentifier('admin'));
        $this->assertEquals('a***********r', SecurityHelper::maskIdentifier('administrator'));
    }
}
