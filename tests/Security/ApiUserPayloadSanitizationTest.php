<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Support\SafeUserPresenter;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * SEC-03: API Response User Payload Sanitization
 *
 * Semua endpoint API yang mengembalikan user HARUS memakai SafeUserPresenter
 * (whitelist: id, name, email, username) — TIDAK BOLEH mengembalikan Eloquent
 * model mentah yang menserialize hash password & kolom internal.
 */
class ApiUserPayloadSanitizationTest extends TestCase
{
    public function test_safe_user_presenter_exposes_only_whitelisted_fields(): void
    {
        $user = User::create([
            'name'     => 'Payload Test',
            'username' => 'payloadtest',
            'email'    => 'payload@example.com',
            'password' => Hash::make('SuperSecret123!'),
        ]);

        $presented = SafeUserPresenter::present($user);

        $this->assertSame($user->id, $presented['id']);
        $this->assertSame('Payload Test', $presented['name']);
        $this->assertSame('payloadtest', $presented['username']);
        $this->assertSame('payload@example.com', $presented['email']);

        // Hash password TIDAK BOLEH muncul di payload
        $this->assertArrayNotHasKey('password', $presented);
        $this->assertArrayNotHasKey('password_hash', $presented);
        $this->assertArrayNotHasKey('remember_token', $presented);
        $this->assertArrayNotHasKey('created_at', $presented);
        $this->assertArrayNotHasKey('updated_at', $presented);
    }

    public function test_login_api_does_not_expose_password_hash_in_response(): void
    {
        $password = 'SuperSecret123!';
        $user = User::create([
            'name'     => 'Api Login',
            'username' => 'apilogin',
            'email'    => 'apilogin@example.com',
            'password' => Hash::make($password),
        ]);
        $hash = $user->password;

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'apilogin@example.com',
            'password'   => $password,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'apilogin@example.com');

        $content = (string) $response->getContent();

        // Password hash & remember_token TIDAK BOLEH ada di response JSON
        $this->assertStringNotContainsString($hash, $content,
            'Password hash bocor ke response JSON login API!');
        $this->assertStringNotContainsString('remember_token', $content,
            'remember_token bocor ke response JSON login API!');
    }

    public function test_otp_verify_api_does_not_expose_password_hash(): void
    {
        $password = 'SuperSecret123!';
        $user = User::create([
            'name'     => 'Otp User',
            'username' => 'otpuser',
            'email'    => 'otpuser@example.com',
            'password' => Hash::make($password),
        ]);
        $hash = $user->password;

        // Generate OTP via service (bypass HTTP throttle complexity)
        $this->app['config']->set('authentication.features.otp.enabled', true);
        $this->app['config']->set('authentication.features.otp.send_email', false);

        $otpService = app(\Vendor\LaravelAuthentication\Contracts\OtpServiceInterface::class);
        $code = $otpService->generate('otpuser@example.com', new AuthenticationContext('127.0.0.1', 'PHPUnit'));

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'identifier' => 'otpuser@example.com',
            'code'       => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString($hash, $content,
            'Password hash bocor ke response JSON OTP verify!');
    }

    public function test_safe_user_presenter_handles_missing_optional_fields(): void
    {
        $user = User::create([
            'email'    => 'minimal@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $presented = SafeUserPresenter::present($user);

        $this->assertSame($user->id, $presented['id']);
        $this->assertSame('minimal@example.com', $presented['email']);

        // name/username nullable — hanya muncul jika ada
        $this->assertArrayNotHasKey('name', $presented);
        $this->assertArrayNotHasKey('username', $presented);
    }
}