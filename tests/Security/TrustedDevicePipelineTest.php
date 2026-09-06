<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Exceptions\TwoFactorChallengeRequiredException;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;
use Vendor\LaravelAuthentication\Models\TwoFactorAuthentication;
use Vendor\LaravelAuthentication\Services\Core\AuthenticationService;
use Vendor\LaravelAuthentication\Services\Session\DeviceTrustService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Red-team wiring test: trusted device must ACTUALLY skip 2FA in the full
 * login pipeline (AuthenticationService::authenticate), not just in the
 * DeviceTrustService unit.
 *
 * Finding: DeviceTrustService was unit-tested but the login pipeline hook
 * (isTrusted check before TwoFactorChallengeRequiredException) had no test —
 * the "trusted device skips 2FA" feature could silently disconnect.
 */
class TrustedDevicePipelineTest extends TestCase
{
    private User $user;
    private AuthenticationService $authService;
    private DeviceTrustService $trustService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'authentication.features.two_factor.enabled'                 => true,
            'authentication.features.two_factor.trust_device.enabled'     => true,
            'authentication.features.two_factor.trust_device.cookie_name' => 'auth_trusted_device',
        ]);

        $this->user = User::create([
            'name'     => 'Trust Pipe',
            'username' => 'trustpipe',
            'email'    => 'trustpipe@example.com',
            'password' => Hash::make('SuperSecret123!'),
        ]);

        // Confirm 2FA for the user (isEnabledFor requires isConfirmed)
        TwoFactorAuthentication::create([
            'user_id'      => $this->user->id,
            'secret'       => 'JBSWY3DPEHPK3PXP',
            'confirmed_at' => now(),
            'recovery_codes' => '[]',
        ]);

        $this->authService = app(AuthenticationService::class);
        $this->trustService = app(DeviceTrustService::class);
    }

    private function loginRequest(): Request
    {
        return Request::create('/login', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TrustPipe',
            'REMOTE_ADDR'     => '203.0.113.10',
        ]);
    }

    private function loginData(): LoginData
    {
        return new LoginData(
            identifier: 'trustpipe@example.com',
            password: 'SuperSecret123!',
            remember: false
        );
    }

    public function test_login_without_trust_cookie_requires_two_factor_challenge(): void
    {
        // Bind the request as the current app request so AuthenticationService
        // (via request()) sees it.
        $this->app->instance('request', $this->loginRequest());

        $context = new AuthenticationContext(
            '203.0.113.10',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TrustPipe',
            AuthenticationChannel::WEB,
            'web'
        );

        $this->expectException(TwoFactorChallengeRequiredException::class);
        $this->authService->authenticate($this->loginData(), $context);
    }

    public function test_login_with_valid_trust_cookie_skips_two_factor(): void
    {
        $request = $this->loginRequest();
        $this->app->instance('request', $request);

        // Issue a legitimate trust cookie for this device
        $cookie = $this->trustService->createTrustCookie($this->user, $request);
        $token = (string) $cookie->getValue();
        $this->assertNotEmpty($token);

        $trustRequest = Request::create('/login', 'POST', [], [
            'auth_trusted_device' => $token,
        ], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TrustPipe',
            'REMOTE_ADDR'     => '203.0.113.10',
        ]);
        $this->app->instance('request', $trustRequest);

        $context = new AuthenticationContext(
            '203.0.113.10',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TrustPipe',
            AuthenticationChannel::WEB,
            'web'
        );

        $result = $this->authService->authenticate($this->loginData(), $context);

        $this->assertTrue($result->isSuccess());
    }

    public function test_revoked_trust_cookie_no_longer_skips_two_factor(): void
    {
        $request = $this->loginRequest();
        $this->app->instance('request', $request);

        $cookie = $this->trustService->createTrustCookie($this->user, $request);
        $token = (string) $cookie->getValue();

        $this->trustService->revokeUserTrust($this->user);

        $trustRequest = Request::create('/login', 'POST', [], [
            'auth_trusted_device' => $token,
        ], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TrustPipe',
            'REMOTE_ADDR'     => '203.0.113.10',
        ]);
        $this->app->instance('request', $trustRequest);

        $context = new AuthenticationContext(
            '203.0.113.10',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TrustPipe',
            AuthenticationChannel::WEB,
            'web'
        );

        $this->expectException(TwoFactorChallengeRequiredException::class);
        $this->authService->authenticate($this->loginData(), $context);
    }
}