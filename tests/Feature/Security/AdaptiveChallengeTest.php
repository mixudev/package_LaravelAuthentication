<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature\Security;

use Illuminate\Support\Facades\Cache;
use Vendor\LaravelAuthentication\Contracts\AuthenticationAbusePolicyInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Adaptive challenge escalation tests.
 * 
 * Security requirements:
 * - Challenge tokens are single-use (replay protection)
 * - Tokens expire after TTL
 * - Challenge escalation activates between soft and hard limits
 * - Challenge does NOT reveal account existence
 * - Token generation is cryptographically random
 */
class AdaptiveChallengeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /**
     * Test: Policy escalates to challenge when between thresholds.
     * 
     * RED: This will fail because AuthenticationAbusePolicy doesn't implement challenge logic yet.
     */
    public function test_escalates_to_challenge_between_thresholds(): void
    {
        config([
            'authentication.security.rate_limits.login.max_attempts' => 10,
            'authentication.security.rate_limits.login.challenge_threshold' => 5,
        ]);

        /** @var AuthenticationAbusePolicyInterface $policy */
        $policy = app(AuthenticationAbusePolicyInterface::class);

        $loginData = new LoginData('test@example.com', 'password');
        $context = new AuthenticationContext('192.168.1.1', 'TestAgent/1.0');

        // First 4 attempts: should allow without challenge
        for ($i = 0; $i < 4; $i++) {
            $decision = $policy->evaluate($loginData, $context);
            $this->assertEquals('allow', $decision->action);
            $policy->recordFailure($loginData, $context);
        }

        // 5th attempt: should trigger challenge (at threshold)
        $decision = $policy->evaluate($loginData, $context);
        $this->assertEquals('challenge', $decision->action);
        $this->assertFalse($decision->allowed);
        $this->assertNotEmpty($decision->reasonCode);
    }

    /**
     * Test: Challenge token can be generated and is cryptographically random.
     */
    public function test_generates_random_challenge_token(): void
    {
        /** @var AuthenticationAbusePolicyInterface $policy */
        $policy = app(AuthenticationAbusePolicyInterface::class);

        $loginData = new LoginData('test@example.com', 'password');
        $context = new AuthenticationContext('192.168.1.1', 'TestAgent/1.0');

        $token1 = $policy->generateChallengeToken($loginData, $context);
        $token2 = $policy->generateChallengeToken($loginData, $context);

        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token1);
    }

    /**
     * Test: Valid challenge token passes verification (single-use).
     */
    public function test_verifies_valid_challenge_token_once(): void
    {
        /** @var AuthenticationAbusePolicyInterface $policy */
        $policy = app(AuthenticationAbusePolicyInterface::class);

        $loginData = new LoginData('test@example.com', 'password');
        $context = new AuthenticationContext('192.168.1.1', 'TestAgent/1.0');

        $token = $policy->generateChallengeToken($loginData, $context);

        // First verification should succeed
        $this->assertTrue($policy->verifyChallengeToken($token, $loginData, $context));

        // Second verification with same token should fail (replay protection)
        $this->assertFalse($policy->verifyChallengeToken($token, $loginData, $context));
    }

    /**
     * Test: Expired challenge token fails verification.
     */
    public function test_rejects_expired_challenge_token(): void
    {
        config(['authentication.security.rate_limits.login.challenge_token_ttl' => 1]); // 1 second

        /** @var AuthenticationAbusePolicyInterface $policy */
        $policy = app(AuthenticationAbusePolicyInterface::class);

        $loginData = new LoginData('test@example.com', 'password');
        $context = new AuthenticationContext('192.168.1.1', 'TestAgent/1.0');

        $token = $policy->generateChallengeToken($loginData, $context);

        // Wait for expiry
        sleep(2);

        // Verification should fail due to expiry
        $this->assertFalse($policy->verifyChallengeToken($token, $loginData, $context));
    }

    /**
     * Test: Invalid/malformed challenge token fails verification.
     */
    public function test_rejects_invalid_challenge_token(): void
    {
        /** @var AuthenticationAbusePolicyInterface $policy */
        $policy = app(AuthenticationAbusePolicyInterface::class);

        $loginData = new LoginData('test@example.com', 'password');
        $context = new AuthenticationContext('192.168.1.1', 'TestAgent/1.0');

        // Various invalid tokens
        $this->assertFalse($policy->verifyChallengeToken('', $loginData, $context));
        $this->assertFalse($policy->verifyChallengeToken('invalid', $loginData, $context));
        $this->assertFalse($policy->verifyChallengeToken('a'.str_repeat('0', 63), $loginData, $context));
        $this->assertFalse($policy->verifyChallengeToken(str_repeat('x', 64), $loginData, $context));
    }

    /**
     * Test: Challenge token does NOT reveal account existence.
     * Tokens for existing and non-existing accounts must be indistinguishable.
     */
    public function test_challenge_does_not_reveal_account_existence(): void
    {
        /** @var AuthenticationAbusePolicyInterface $policy */
        $policy = app(AuthenticationAbusePolicyInterface::class);

        $existingUser = new LoginData('exists@example.com', 'password');
        $nonExistingUser = new LoginData('nonexists@example.com', 'password');
        $context = new AuthenticationContext('192.168.1.1', 'TestAgent/1.0');

        $token1 = $policy->generateChallengeToken($existingUser, $context);
        $token2 = $policy->generateChallengeToken($nonExistingUser, $context);

        // Both should generate valid tokens
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertEquals(strlen($token1), strlen($token2));
        
        // Both should be verifiable (until consumed)
        $this->assertTrue($policy->verifyChallengeToken($token1, $existingUser, $context));
        $this->assertTrue($policy->verifyChallengeToken($token2, $nonExistingUser, $context));
    }

    /**
     * Test: Challenge token is bound to identifier+IP (context).
     * Token generated for one context cannot be verified in another.
     */
    public function test_challenge_token_bound_to_context(): void
    {
        /** @var AuthenticationAbusePolicyInterface $policy */
        $policy = app(AuthenticationAbusePolicyInterface::class);

        $loginData = new LoginData('test@example.com', 'password');
        $context1 = new AuthenticationContext('192.168.1.1', 'TestAgent/1.0');
        $context2 = new AuthenticationContext('192.168.1.2', 'TestAgent/1.0');

        $token = $policy->generateChallengeToken($loginData, $context1);

        // Should verify in same context
        $this->assertTrue($policy->verifyChallengeToken($token, $loginData, $context1));

        // Regenerate and try different IP
        $token2 = $policy->generateChallengeToken($loginData, $context2);
        
        // Should NOT verify token from different IP context with original context
        $this->assertFalse($policy->verifyChallengeToken($token2, $loginData, $context1));
    }

    /**
     * Test: Integration with CaptchaService for challenge resolution.
     */
    public function test_integrates_with_captcha_service(): void
    {
        config([
            'authentication.captcha.enabled' => true,
            'authentication.captcha.driver' => 'turnstile',
            'authentication.security.captcha.secret_key' => 'test-secret',
                        'authentication.security.rate_limits.login.challenge_threshold' => 3,
                    ]);

        /** @var AuthenticationAbusePolicyInterface $policy */
        $policy = app(AuthenticationAbusePolicyInterface::class);

        $loginData = new LoginData('test@example.com', 'password');
        $context = new AuthenticationContext('192.168.1.1', 'TestAgent/1.0');

        // Trigger challenge threshold
        for ($i = 0; $i < 3; $i++) {
            $policy->recordFailure($loginData, $context);
        }

        $decision = $policy->evaluate($loginData, $context);
        $this->assertEquals('challenge', $decision->action);

        // Generate challenge token
        $token = $policy->generateChallengeToken($loginData, $context);
        $this->assertNotEmpty($token);

        // Verify challenge token is required before proceeding
        $this->assertFalse($policy->verifyChallengeToken('wrong-token', $loginData, $context));
    }
}
