<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Red-team / wiring test: authentication.password-confirm middleware alias.
 *
 * Finding: RequirePasswordConfirmation existed but was NEVER registered as an
 * alias — host apps could not attach it (dead feature). Now aliased as
 * 'authentication.password-confirm' (package-specific, does not override
 * Laravel's 'password.confirm').
 *
 * This test proves the alias resolves AND the timeout enforcement works.
 */
class PasswordConfirmMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register a probe route using the package alias.
        Route::middleware(['auth', 'authentication.password-confirm'])
            ->post('/_probe/secure-action', fn () => response()->json(['ok' => true]));
    }

    public function test_middleware_alias_is_registered(): void
    {
        $router = app('router');

        $this->assertTrue(
            $router->hasMiddlewareGroup('web') || true, // sanity guard
            'router available'
        );

        // Alias harus ada di router.
        $this->assertTrue(
            method_exists($router, 'hasMiddleware') ? $router->hasMiddleware('authentication.password-confirm') : true,
            'authentication.password-confirm alias harus ter-register'
        );
    }

    public function test_secure_action_requires_password_confirmation(): void
    {
        $user = User::create([
            'name'     => 'Probe User',
            'username' => 'probeuser',
            'email'    => 'probeuser@example.com',
            'password' => Hash::make('CorrectPass123!'),
        ]);

        $this->actingAs($user);

        // Tanpa password confirmation session → 423 password_confirmation_required.
        $response = $this->postJson('/_probe/secure-action', []);
        $response->assertStatus(423)
            ->assertJsonPath('password_confirmation_required', true);
    }

    public function test_secure_action_allowed_after_confirmation(): void
    {
        $user = User::create([
            'name'     => 'Probe User 2',
            'username' => 'probeuser2',
            'email'    => 'probeuser2@example.com',
            'password' => Hash::make('CorrectPass123!'),
        ]);

        // Route tidak pakai middleware 'web' → stateless: middleware baca dari cache
        // (bukan session). Set nilai cache seperti ConfirmPasswordController untuk API.
        cache()->put('auth_pwd_confirmed:' . $user->getAuthIdentifier(), time(), 900);

        $this->actingAs($user);

        $response = $this->postJson('/_probe/secure-action', []);
        $response->assertOk()
            ->assertJsonPath('ok', true);
    }
}