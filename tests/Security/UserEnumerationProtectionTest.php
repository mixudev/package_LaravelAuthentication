<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class UserEnumerationProtectionTest extends TestCase
{
    public function test_existing_and_non_existing_users_throw_identical_exception_and_message(): void
    {
        User::create([
            'email'    => 'registered_user@example.com',
            'password' => Hash::make('ActualSecret123!'),
        ]);

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit');

        $existingUserMessage = '';
        try {
            $service->authenticate(new LoginData('registered_user@example.com', 'IncorrectPassword!'), $context);
        } catch (InvalidCredentialsException $e) {
            $existingUserMessage = $e->getMessage();
        }

        $nonExistingUserMessage = '';
        try {
            $service->authenticate(new LoginData('unknown_ghost_account@example.com', 'IncorrectPassword!'), $context);
        } catch (InvalidCredentialsException $e) {
            $nonExistingUserMessage = $e->getMessage();
        }

        $this->assertNotEmpty($existingUserMessage);
        $this->assertEquals($existingUserMessage, $nonExistingUserMessage);
    }
}
