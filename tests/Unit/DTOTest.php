<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Vendor\LaravelAuthentication\DTO\AuthenticationResult;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Enums\AuthenticationStatus;

class DTOTest extends BaseTestCase
{
    public function test_login_data_factory_populates_correctly(): void
    {
        $dto = LoginData::fromArray([
            'email'    => 'alice@example.com',
            'password' => 'secret123',
            'remember' => true,
            'strategy' => 'email_password',
        ]);

        $this->assertEquals('alice@example.com', $dto->identifier);
        $this->assertEquals('secret123', $dto->password);
        $this->assertTrue($dto->remember);
        $this->assertEquals('email_password', $dto->strategy);
    }

    public function test_authentication_result_success_and_failed_factories(): void
    {
        $failed = AuthenticationResult::failed(AuthenticationStatus::INVALID_CREDENTIALS, 'Custom message');
        $this->assertFalse($failed->isSuccessful());
        $this->assertEquals(AuthenticationStatus::INVALID_CREDENTIALS, $failed->status);
        $this->assertEquals('Custom message', $failed->message);
    }
}
