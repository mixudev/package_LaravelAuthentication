<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class SensitiveResponseTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Alice Bob',
            'username' => 'alice',
            'email'    => 'alice@example.com',
            'password' => Hash::make('SuperSecretPassword123!'),
        ]);
    }

    public function test_session_index_never_exposes_password_hash_or_sensitive_model_attributes(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/auth/sessions');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('user', $json);
        $userArray = $json['user'];

        // Must contain sanitized whitelist only
        $this->assertEquals($this->user->id, $userArray['id']);
        $this->assertEquals('Alice Bob', $userArray['name']);
        $this->assertEquals('alice@example.com', $userArray['email']);

        // MUST NOT contain password hash, remember_token, or internal properties
        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
        $this->assertArrayNotHasKey('two_factor_secret', $userArray);
        $this->assertArrayNotHasKey('recovery_codes', $userArray);
    }

    public function test_api_password_reset_returns_generic_error_without_user_enumeration(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email'                 => 'unknown_target@example.com',
            'password'              => 'NewValidPassword123!',
            'password_confirmation' => 'NewValidPassword123!',
            'token'                 => 'invalid_token_123',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status'  => 'failed',
            'message' => 'Unable to reset password. The reset link is invalid or has expired.',
        ]);
    }
}
