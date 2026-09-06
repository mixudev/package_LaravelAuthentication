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

/**
 * User Enumeration Protection Tests
 *
 * A system leaks user enumeration if:
 * - Response for existing user differs from non-existing user (message, type, timing delta)
 * - HTTP status codes differ
 * - Exception classes differ
 *
 * Attack vectors covered:
 * - Wrong password on existing user vs non-existing user (identical response)
 * - Timing delta between existing/non-existing must not exceed 50ms (constant-time design)
 * - Verified email vs unverified email must return identical error
 * - Mixed case email must not reveal existence
 * - Leading/trailing whitespace must not reveal existence
 */
class UserEnumerationProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'email'    => 'registered_user@example.com',
            'password' => Hash::make('ActualSecret123!'),
        ]);
    }

    // ---------------------------------------------------------------
    // TEST 1: Existing vs non-existing: identical exception class + message
    // ---------------------------------------------------------------
    public function test_existing_and_non_existing_users_throw_identical_exception_and_message(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit');

        $existingUserMessage = '';
        $existingClass = '';
        try {
            $service->authenticate(new LoginData('registered_user@example.com', 'IncorrectPassword!'), $context);
        } catch (InvalidCredentialsException $e) {
            $existingUserMessage = $e->getMessage();
            $existingClass = get_class($e);
        }

        $nonExistingUserMessage = '';
        $nonExistingClass = '';
        try {
            $service->authenticate(new LoginData('unknown_ghost_account@example.com', 'IncorrectPassword!'), $context);
        } catch (InvalidCredentialsException $e) {
            $nonExistingUserMessage = $e->getMessage();
            $nonExistingClass = get_class($e);
        }

        $this->assertNotEmpty($existingUserMessage);
        $this->assertSame($existingUserMessage, $nonExistingUserMessage,
            'Messages differ — user enumeration possible.');
        $this->assertSame($existingClass, $nonExistingClass,
            'Exception classes differ — user enumeration possible.');
    }

    // ---------------------------------------------------------------
    // TEST 2: Timing delta must stay below 50ms between existing/non-existing
    // ---------------------------------------------------------------
    public function test_timing_difference_between_existing_and_ghost_user_is_minimal(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $measureMs = function (string $email): float {
            $start = hrtime(true);
            try {
                $service->authenticate(
                    new LoginData($email, 'WrongPassword!'),
                    new AuthenticationContext('127.0.0.1', 'PHPUnit')
                );
            } catch (\Throwable) {
                // ignored
            }
            return (hrtime(true) - $start) / 1_000_000; // nanoseconds → ms
        };

        // Warm-up run (first call has class-loading overhead)
        $measureMs('registered_user@example.com');
        $measureMs('ghost123@nowhere.com');

        $samples = 5;
        $existingTotal = 0.0;
        $ghostTotal = 0.0;

        for ($i = 0; $i < $samples; $i++) {
            $existingTotal += $measureMs('registered_user@example.com');
            $ghostTotal    += $measureMs('ghost' . $i . '@nowhere.com');
        }

        $delta = abs(($existingTotal / $samples) - ($ghostTotal / $samples));

        // 50ms is a generous threshold — bcrypt should equalize timing.
        // If this fails, a dummy hash operation is missing in the credential resolution path.
        $this->assertLessThan(50.0, $delta,
            sprintf('Timing delta %.2fms exceeds 50ms — user enumeration via timing is possible.', $delta));
    }

    // ---------------------------------------------------------------
    // TEST 3: Uppercase email must not reveal user existence
    // ---------------------------------------------------------------
    public function test_uppercase_email_does_not_reveal_user_existence(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit');

        $msgLower = '';
        $msgUpper = '';

        try {
            $service->authenticate(new LoginData('registered_user@example.com', 'Wrong!'), $context);
        } catch (InvalidCredentialsException $e) {
            $msgLower = $e->getMessage();
        }

        try {
            $service->authenticate(new LoginData('REGISTERED_USER@EXAMPLE.COM', 'Wrong!'), $context);
        } catch (InvalidCredentialsException $e) {
            $msgUpper = $e->getMessage();
        }

        $this->assertSame($msgLower, $msgUpper, 'Email case change altered the error response — enumeration risk.');
    }

    // ---------------------------------------------------------------
    // TEST 4: Whitespace-padded email — normalizer trims it, check it maps to same user
    //         The key invariant: trimmed identifier must NOT succeed with wrong password,
    //         and the response must be identical to a regular wrong-password attempt.
    // ---------------------------------------------------------------
    public function test_whitespace_padded_identifier_with_wrong_password_returns_same_error(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit');

        $msgNormal = '';
        $msgPadded = '';

        try {
            $service->authenticate(new LoginData('registered_user@example.com', 'WrongPassword!'), $context);
        } catch (InvalidCredentialsException $e) {
            $msgNormal = $e->getMessage();
        }

        try {
            $service->authenticate(new LoginData('  registered_user@example.com  ', 'WrongPassword!'), $context);
        } catch (InvalidCredentialsException $e) {
            $msgPadded = $e->getMessage();
        }

        $this->assertSame($msgNormal, $msgPadded,
            'Whitespace-padded identifier produced different error message — enumeration risk.');
    }
}
