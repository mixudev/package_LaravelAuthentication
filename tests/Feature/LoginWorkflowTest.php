<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Events\LoginAttempted;
use Vendor\LaravelAuthentication\Events\LoginFailed;
use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class LoginWorkflowTest extends TestCase
{
    public function test_user_can_login_with_email_and_password(): void
    {
        Event::fake([LoginAttempted::class, LoginSucceeded::class]);

        $user = User::create([
            'name'     => 'Alice User',
            'username' => 'alice',
            'email'    => 'alice@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $loginData = new LoginData('alice@example.com', 'Password123!');
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit');

        $result = $service->authenticate($loginData, $context);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals($user->id, $result->user->getAuthIdentifier());

        Event::assertDispatched(LoginAttempted::class);
        Event::assertDispatched(LoginSucceeded::class);
    }

    public function test_user_can_login_with_username_and_password(): void
    {
        $user = User::create([
            'name'     => 'Bob User',
            'username' => 'bob_the_builder',
            'email'    => 'bob@example.com',
            'password' => Hash::make('BobSecret123!'),
        ]);

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $loginData = new LoginData('bob_the_builder', 'BobSecret123!');
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit');

        $result = $service->authenticate($loginData, $context);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals($user->id, $result->user->getAuthIdentifier());
    }

    public function test_login_fails_with_invalid_password(): void
    {
        Event::fake([LoginFailed::class]);

        User::create([
            'username' => 'charlie',
            'email'    => 'charlie@example.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $loginData = new LoginData('charlie@example.com', 'WrongPassword123!');
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit');

        $this->expectException(InvalidCredentialsException::class);
        $service->authenticate($loginData, $context);

        Event::assertDispatched(LoginFailed::class);
    }
}
