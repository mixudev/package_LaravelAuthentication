<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Models\TwoFactorAuthentication;
use Vendor\LaravelAuthentication\Services\TwoFactor\TotpService;
use Vendor\LaravelAuthentication\Services\TwoFactor\TwoFactorService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class TwoFactorChallengeFlowTest extends TestCase
{
    private User $user;
    private array $plainRecoveryCodes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Test User 2FA',
            'username' => 'test2fa',
            'email'    => 'test2fa@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $twoFactorService = app(TwoFactorService::class);
        $setup = $twoFactorService->setup($this->user);
        $this->plainRecoveryCodes = $setup['recovery_codes'];

        $validTotp = app(TotpService::class)->calculateCode($setup['secret']);
        $twoFactorService->confirm($this->user, $validTotp);
    }

    public function test_recovery_mode_remains_active_after_invalid_code(): void
    {
        session(['auth.2fa.user_id' => $this->user->id]);

        // Submit invalid recovery code
        $response = $this->post(route('authentication.two-factor.verify'), [
            'recovery_code' => 'WRONG-12345',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['recovery_code']);

        // Assert mode marker is flashed
        $this->assertEquals('recovery', session('auth.2fa.input_mode'));

        // GET challenge again and assert recovery mode is active
        $showResponse = $this->get(route('authentication.two-factor.challenge'));
        $showResponse->assertStatus(200);

        // The view should receive inputMode='recovery'
        $showResponse->assertViewHas('inputMode', 'recovery');
    }

    public function test_totp_mode_remains_active_after_invalid_code(): void
    {
        session(['auth.2fa.user_id' => $this->user->id]);

        // Submit invalid TOTP code
        $response = $this->post(route('authentication.two-factor.verify'), [
            'code' => '000000',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['code']);

        // Assert mode marker is flashed to totp (or absent, defaulting to totp)
        $this->assertContains(session('auth.2fa.input_mode'), ['totp', null]);

        // GET challenge again and assert TOTP mode is active
        $showResponse = $this->get(route('authentication.two-factor.challenge'));
        $showResponse->assertStatus(200);
        $showResponse->assertViewHas('inputMode', 'totp');
    }

    public function test_submitted_code_never_appears_in_session_or_old_input(): void
    {
        session(['auth.2fa.user_id' => $this->user->id]);

        $response = $this->post(route('authentication.two-factor.verify'), [
            'recovery_code' => 'SECRET-CODE',
        ]);

        $response->assertRedirect();

        // Assert secret code never flashed
        $this->assertNull(session('recovery_code'));
        $this->assertNull(session('code'));
        $this->assertNull(old('recovery_code'));
        $this->assertNull(old('code'));
    }

    public function test_valid_recovery_code_succeeds_and_redirects(): void
    {
        session(['auth.2fa.user_id' => $this->user->id]);

        $validCode = $this->plainRecoveryCodes[0];

        $response = $this->post(route('authentication.two-factor.verify'), [
            'recovery_code' => $validCode,
        ]);

        $response->assertRedirect(config('authentication.redirects.two_factor', '/dashboard'));
        $this->assertAuthenticatedAs($this->user);
    }
}
