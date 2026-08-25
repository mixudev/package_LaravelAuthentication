<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\RegistrationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\RegisterData;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Events\UserRegistered;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_user_can_register_via_service(): void
    {
        Event::fake([UserRegistered::class]);

        /** @var RegistrationServiceInterface $service */
        $service = app(RegistrationServiceInterface::class);

        $dto = new RegisterData(
            name: 'Jane Doe',
            email: 'jane@example.com',
            password: 'SecurePassword123!'
        );

        $context = new AuthenticationContext(
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            channel: AuthenticationChannel::WEB
        );

        $user = $service->register($dto, $context);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('jane@example.com', $user->email);
        $this->assertTrue(Hash::check('SecurePassword123!', $user->password));

        Event::assertDispatched(UserRegistered::class);
    }

    public function test_registration_can_be_disabled(): void
    {
        config(['authentication.features.registration.enabled' => false]);

        /** @var RegistrationServiceInterface $service */
        $service = app(RegistrationServiceInterface::class);

        $this->assertFalse($service->isEnabled());
    }
}
