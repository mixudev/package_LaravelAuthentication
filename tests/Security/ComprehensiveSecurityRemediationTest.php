<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Http\Requests\LoginRequest;
use Vendor\LaravelAuthentication\Models\TwoFactorAuthentication;
use Vendor\LaravelAuthentication\Rules\PasswordRule;
use Vendor\LaravelAuthentication\Services\OtpService;
use Vendor\LaravelAuthentication\Services\PasswordService;
use Vendor\LaravelAuthentication\Services\TotpService;
use Vendor\LaravelAuthentication\Services\TwoFactorService;
use Vendor\LaravelAuthentication\Support\QrCodeGenerator;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class ComprehensiveSecurityRemediationTest extends TestCase
{
    /**
     * CRITICAL-001 Verification:
     * Calling setup() on an active 2FA user MUST NOT reset confirmed_at to null.
     */
    public function test_two_factor_setup_does_not_deactivate_active_2fa(): void
    {
        $user = User::create([
            'name'     => 'Alice 2FA',
            'email'    => 'alice2fa@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $twoFactorService = app(TwoFactorService::class);

        // 1. Initial setup and confirm
        $setupData = $twoFactorService->setup($user);
        $totp = app(TotpService::class);
        $validCode = $totp->calculateCode($setupData['secret'], time());
        $confirmed = $twoFactorService->confirm($user, $validCode);

        $this->assertTrue($confirmed);
        $this->assertTrue($twoFactorService->isEnabledFor($user));

        // 2. Re-visiting setup (GET /auth/two-factor/setup)
        $newSetupData = $twoFactorService->setup($user);

        // 3. User 2FA must STILL be enabled and confirmed
        $this->assertTrue($twoFactorService->isEnabledFor($user));

        $record = TwoFactorAuthentication::where('user_id', $user->id)->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->confirmed_at);
        $this->assertTrue($record->isConfirmed());
    }

    /**
     * HIGH-001 Verification:
     * QR Code generation must be completely local (SVG Data URI) with zero external network URLs.
     */
    public function test_qr_code_generator_is_local_svg_without_external_dependencies(): void
    {
        $totp = app(TotpService::class);
        $otpAuthUrl = $totp->getOtpAuthUrl('MyApp', 'alice@example.com', 'JBSWY3DPEHPK3PXP');

        $qrCodeUrl = $totp->getQrCodeUrl($otpAuthUrl);

        // Must be an inline data URI
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qrCodeUrl);
        $this->assertStringNotContainsString('api.qrserver.com', $qrCodeUrl);
        $this->assertStringNotContainsString('http://', $qrCodeUrl);
        $this->assertStringNotContainsString('https://', $qrCodeUrl);

        // Raw SVG verification
        $rawSvg = $totp->getQrCodeSvg($otpAuthUrl);
        $this->assertStringStartsWith('<svg', $rawSvg);
        $this->assertStringContainsString('</svg>', $rawSvg);
        $this->assertStringContainsString('<rect', $rawSvg);
    }

    /**
     * HIGH-002 Verification:
     * OTP verification must enforce 2FA challenge when 2FA is active on the account.
     */
    public function test_otp_login_enforces_2fa_challenge_when_enabled(): void
    {
        $user = User::create([
            'name'     => 'Bob 2FA',
            'email'    => 'bob2fa@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        // Enable 2FA on Bob's account
        $twoFactorService = app(TwoFactorService::class);
        $setup = $twoFactorService->setup($user);
        $code = app(TotpService::class)->calculateCode($setup['secret'], time());
        $twoFactorService->confirm($user, $code);

        $this->assertTrue($twoFactorService->isEnabledFor($user));

        // Generate OTP
        $otpService = app(OtpService::class);
        $context = new AuthenticationContext(
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            channel: AuthenticationChannel::WEB
        );
        $otpCode = $otpService->generate('bob2fa@example.com', $context);

        // Verify OTP via Web POST
        $response = $this->post('/otp/verify', [
            'identifier' => 'bob2fa@example.com',
            'code'       => $otpCode,
        ]);

        // Must redirect to two-factor challenge rather than dashboard
        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertEquals($user->id, session('auth.2fa.user_id'));
    }

    /**
     * MEDIUM-001 Verification:
     * PasswordRule must validate passwords with all special characters without PCRE range compilation warnings/errors.
     */
    public function test_password_rule_handles_all_symbol_charsets(): void
    {
        $rule = new PasswordRule(
            minLength: 8,
            requireUppercase: true,
            requireLowercase: true,
            requireNumbers: true,
            requireSymbols: true,
            symbolsCharset: '@$!%*#?&_-+=[]{}|;:,.<>'
        );

        $failed = false;
        $failCallback = function (string $msg) use (&$failed) {
            $failed = true;
        };

        // Valid password containing special character from charset
        $rule->validate('password', 'ValidPass123_!', $failCallback);
        $this->assertFalse($failed, 'Password with special characters should pass validation without PCRE errors.');

        // Test with hyphen `-`
        $failed = false;
        $rule->validate('password', 'ValidPass123-', $failCallback);
        $this->assertFalse($failed, 'Password with hyphen should pass validation.');

        // Invalid password without symbols
        $failed = false;
        $rule->validate('password', 'ValidPass1234', $failCallback);
        $this->assertTrue($failed, 'Password without symbols must fail.');
    }

    /**
     * MEDIUM-003 Verification:
     * Stateless API password confirmation works via cache without 500 session errors.
     */
    public function test_stateless_api_password_confirmation_succeeds_without_session(): void
    {
        $user = User::create([
            'name'     => 'API User',
            'email'    => 'apiuser@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/v1/auth/confirm-password', [
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'confirmed' => true,
        ]);

        $this->assertNotNull(cache()->get('auth_pwd_confirmed:' . $user->id));
    }

    /**
     * LOW-001 Verification:
     * PasswordHistoryRepository supports UUID string identifiers without integer truncation.
     */
    public function test_password_history_supports_string_uuid_identifiers(): void
    {
        $user = new \Illuminate\Auth\GenericUser([
            'id'       => '018f3a2b-7c1e-7d9a-8b2c-123456789abc',
            'name'     => 'UUID User',
            'email'    => 'uuid@example.com',
            'password' => bcrypt('InitialPass123!'),
        ]);

        $repo = app(\Vendor\LaravelAuthentication\Contracts\PasswordHistoryRepositoryInterface::class);
        $repo->recordPassword($user, bcrypt('PastPassword123!'));

        $this->assertTrue($repo->isPreviouslyUsed($user, 'PastPassword123!', 5));
        $this->assertFalse($repo->isPreviouslyUsed($user, 'DifferentPassword123!', 5));
    }

    /**
     * Checkbox 'on' browser value verification:
     * Form requests properly accept standard HTML checkbox 'on' without boolean validation error.
     */
    public function test_checkbox_remember_accepts_browser_on_value(): void
    {
        $response = $this->post('/login', [
            'identifier' => 'test@example.com',
            'password'   => 'password123',
            'remember'   => 'on',
        ]);

        // It should proceed past validation without "The remember field must be true or false."
        $response->assertSessionDoesntHaveErrors('remember');
    }

    /**
     * Active 2FA Setup Guard:
     * When 2FA is already active and confirmed, accessing the setup page is rejected.
     */
    public function test_two_factor_setup_page_is_rejected_if_already_enabled(): void
    {
        $user = new \Illuminate\Auth\GenericUser([
            'id'       => 999,
            'name'     => 'Active 2FA User',
            'email'    => 'active2fa@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        \Vendor\LaravelAuthentication\Models\TwoFactorAuthentication::create([
            'user_id'        => 999,
            'secret'         => 'JBSWY3DPEHPK3PXP',
            'recovery_codes' => ['code1', 'code2'],
            'confirmed_at'   => now(),
        ]);

        $response = $this->actingAs($user)->get('/auth/two-factor/setup');

        // Should redirect away rather than generating/displaying a new QR setup
        $response->assertRedirect();
        $response->assertSessionHas('status', 'Two-factor authentication is already enabled on your account.');
    }
}
