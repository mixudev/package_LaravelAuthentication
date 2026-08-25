<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Tests\TestCase;

class SQLInjectionAndInputSanitizationTest extends TestCase
{
    public function test_sql_injection_payloads_do_not_bypass_authentication(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit');

        $injectionPayloads = [
            "' OR '1'='1",
            "admin' --",
            "admin' /*",
            "' UNION SELECT 1, 'admin', 'hash' --",
            "admin' OR 1=1 #",
        ];

        foreach ($injectionPayloads as $payload) {
            $loginData = new LoginData($payload, 'AnyPassword123!');

            $this->expectException(InvalidCredentialsException::class);
            $service->authenticate($loginData, $context);
        }
    }
}
