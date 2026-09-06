<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\RegistrationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\RegisterData;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Red-team test: registration mass-assignment / extra-field injection.
 *
 * Attack surface:
 * - Attacker POST /register dengan field ekstra (role, is_admin, email_verified_at,
 *   password_confirmation) berharap ikut ter-persist (mass assignment).
 * - RegisterRequest::toDto() harus DROP semua field selain name/email/password.
 * - RegisterData::extra tidak boleh mencemari create path package.
 * - unique email rule tetap menolak duplikat.
 */
class RegistrationInjectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // API register butuh token — fake, test ini fokus injection bukan Sanctum.
        $this->app->instance(TokenManagerInterface::class, new class implements TokenManagerInterface {
            public function createToken($user, string $tokenName = 'auth_token', array $abilities = ['*']): string
            {
                return 'fake_token_' . $user->getAuthIdentifier();
            }

            public function revokeAllTokens($user): void {}

            public function revokeCurrentToken($user): void {}
        });
    }

    public function test_register_with_extra_fields_ignores_attacker_columns(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Injected User',
            'email'                 => 'inject@example.com',
            'password'              => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            // Attacker-controlled extra fields:
            'role'                  => 'admin',
            'is_admin'              => 1,
            'email_verified_at'     => now()->toDateTimeString(),
            'remember_token'        => 'stolen-token',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        /** @var User $user */
        $user = User::where('email', 'inject@example.com')->first();
        $this->assertNotNull($user);

        // Kolom ekstra TIDAK boleh ter-persist.
        $this->assertNotEquals('admin', $user->role ?? null);
        $this->assertNotEquals(1, $user->is_admin ?? null);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->remember_token);

        // Password tetap ter-hash, bukan plaintext.
        $this->assertNotSame('StrongPass123!', $user->password);
        $this->assertTrue(Hash::check('StrongPass123!', $user->password));
    }

    public function test_register_service_ignores_extra_via_dto(): void
    {
        /** @var RegistrationServiceInterface $service */
        $service = app(RegistrationServiceInterface::class);

        $user = $service->register(
            new RegisterData(
                name: 'Dto Extra',
                email: 'dto-extra@example.com',
                password: 'StrongPass123!',
                extra: ['role' => 'admin', 'is_admin' => true]
            ),
            new AuthenticationContext('127.0.0.1', 'PHPUnit')
        );

        $this->assertNotNull($user->getAuthIdentifier());
        $this->assertNotEquals('admin', $user->role ?? null);
        $this->assertNotEquals(true, $user->is_admin ?? null);
        $this->assertSame('dto-extra@example.com', $user->email);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::create([
            'name'     => 'First',
            'email'    => 'dup@example.com',
            'password' => Hash::make('StrongPass123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Second',
            'email'                 => 'dup@example.com',
            'password'              => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, User::where('email', 'dup@example.com')->count());
    }
}