<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class RateLimitingAndBruteForceTest extends TestCase
{
    public function test_throttles_requests_after_max_failed_attempts(): void
    {
        User::create([
            'email'    => 'victim@example.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $loginData = new LoginData('victim@example.com', 'BadPassword123!');
        $context = new AuthenticationContext('192.168.1.50', 'AttackerAgent/1.0');

        // Exhaust 5 allowed attempts
        for ($i = 0; $i < 5; $i++) {
            try {
                $service->authenticate($loginData, $context);
            } catch (InvalidCredentialsException $e) {
                // Expected failure
            }
        }

        // 6th attempt should immediately throw AuthenticationThrottledException
        $this->expectException(AuthenticationThrottledException::class);
        $service->authenticate($loginData, $context);
    }
}
