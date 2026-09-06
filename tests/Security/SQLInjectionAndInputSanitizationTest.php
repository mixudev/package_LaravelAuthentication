<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * SQL Injection & Input Sanitization Security Tests
 *
 * Attempts to bypass authentication using SQL injection, NoSQL-style payloads,
 * Unicode tricks, oversized input, null bytes, and control characters.
 * Every payload must throw InvalidCredentialsException — never succeed.
 */
class SQLInjectionAndInputSanitizationTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function injectionPayloadProvider(): array
    {
        return [
            'classic OR bypass'       => ["' OR '1'='1"],
            'comment terminator'      => ["admin' --"],
            'block comment'           => ["admin' /*"],
            'UNION SELECT injection'  => ["' UNION SELECT 1, 'admin', 'hash' --"],
            'hash comment bypass'     => ["admin' OR 1=1 #"],
            'tautology equals'        => ["' OR 1=1--"],
            'sleep attack attempt'    => ["'; SELECT SLEEP(5)--"],
            'stacked query attempt'   => ["admin'; DROP TABLE users; --"],
            'null byte terminator'    => ["admin\x00"],
            'overlong input'          => [str_repeat('A', 10_000)],
            'unicode lookalike @'     => ["admin＠example.com"],
            'CRLF injection'          => ["admin\r\nX-Injected: header"],
            'format string'           => ["%s%s%s%s%s%n"],
            'empty identifier'        => [''],
            'only whitespace'         => ['   '],
        ];
    }

    /**
     * @dataProvider injectionPayloadProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('injectionPayloadProvider')]
    public function test_injection_payload_does_not_bypass_authentication(string $payload): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit/SecurityTest');

        try {
            $result = $service->authenticate(new LoginData($payload, 'AnyPassword123!'), $context);
            // If we reach here — auth succeeded. That is a critical bypass.
            $this->fail("Auth succeeded for injection payload: " . json_encode($payload));
        } catch (InvalidCredentialsException $e) {
            // Correct: rejected as invalid credentials
            $this->assertNotEmpty($e->getMessage());
        } catch (\Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException $e) {
            // Also correct: rate limiter kicked in before resolving user
            $this->assertTrue(true);
        }
    }

    /**
     * Password field injection: even if identifier resolves a real user, password injection must fail.
     */
    public function test_password_field_injection_on_existing_user(): void
    {
        \Vendor\LaravelAuthentication\Tests\Fixtures\User::create([
            'email'    => 'sqltarget@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('CorrectPassword123!'),
        ]);

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit/SecurityTest');

        $passwordPayloads = [
            "' OR '1'='1",
            "1' OR '1'='1' --",
            "CorrectPassword123!' OR '1'='1",
            "",
            " ",
            "\x00",
        ];

        foreach ($passwordPayloads as $pwd) {
            try {
                $service->authenticate(new LoginData('sqltarget@example.com', $pwd), $context);
                $this->fail("Password injection bypassed auth: " . json_encode($pwd));
            } catch (InvalidCredentialsException) {
                // correct
            } catch (\Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException) {
                // rate-limited — also acceptable
                break;
            }
        }

        $this->assertTrue(true);
    }
}
