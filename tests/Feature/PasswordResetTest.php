<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Services\Password\PasswordService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_prevents_password_reuse_when_history_enabled(): void
    {
        $user = User::create([
            'email'    => 'history_user@example.com',
            'password' => Hash::make('InitialPassword123!'),
        ]);

        /** @var PasswordService $passwordService */
        $passwordService = app(PasswordService::class);

        // Update to a new password
        $passwordService->updatePassword($user, 'SecondPassword123!');

        // Attempt to update to the immediate previous password (should throw exception)
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('You cannot reuse any of your last 5 passwords.');

        $passwordService->updatePassword($user, 'SecondPassword123!');
    }
}
