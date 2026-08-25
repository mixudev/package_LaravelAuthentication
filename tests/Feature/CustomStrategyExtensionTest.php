<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\AuthenticationStrategyInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Support\AuthenticationStrategyRegistry;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class CustomStrategyExtensionTest extends TestCase
{
    public function test_can_register_and_authenticate_via_custom_employee_id_strategy(): void
    {
        $user = User::create([
            'name'        => 'Employee Dave',
            'employee_id' => 'EMP-8849',
            'password'    => Hash::make('EmployeeSecret123!'),
        ]);

        /** @var AuthenticationStrategyRegistry $registry */
        $registry = app(AuthenticationStrategyRegistry::class);

        // Define inline custom employee strategy
        $customStrategy = new class implements AuthenticationStrategyInterface {
            public function name(): string
            {
                return 'employee_id';
            }

            public function supports(LoginData $data): bool
            {
                return str_starts_with($data->identifier, 'EMP-');
            }

            public function resolveUser(LoginData $data, AuthenticationContext $context): ?Authenticatable
            {
                return User::where('employee_id', $data->identifier)->first();
            }

            public function validateCredentials(Authenticatable $user, LoginData $data): bool
            {
                return Hash::check($data->password, $user->getAuthPassword());
            }
        };

        $registry->register('employee_id', $customStrategy);

        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $loginData = new LoginData(
            identifier: 'EMP-8849',
            password: 'EmployeeSecret123!',
            strategy: 'employee_id'
        );
        $context = new AuthenticationContext('127.0.0.1', 'PHPUnit');

        $result = $service->authenticate($loginData, $context);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals($user->id, $result->user->getAuthIdentifier());
    }
}
