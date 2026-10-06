<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Vendor\LaravelAuthentication\Contracts\AuthenticationAbusePolicyInterface;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\RateLimitDecision;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\Normalizers\EmailNormalizer;

/**
 * Authentication abuse policy orchestrator.
 * 
 * Multi-dimensional rate limiting:
 * - account: per normalized identifier (blocks IP rotation)
 * - account_ip: per identifier+IP composite (legacy equivalent)
 * - client: per application scope
 * - network: per IP (blocks identifier rotation)
 * - global: platform-wide capacity
 * 
 * Evaluation: check all enabled dimensions, return first throttle/deny.
 * Backward compatibility: when abuse_policy disabled, delegates to legacy FeatureRateLimiter.
 */
class AuthenticationAbusePolicy implements AuthenticationAbusePolicyInterface
{
    public function __construct(
        private readonly FeatureRateLimiterInterface $rateLimiter,
        private readonly RateLimiter $cacheLimiter,
        private readonly AuthenticationConfig $config,
        private readonly ?DistributedAttackDetector $distributedDetector = null,
    ) {}

    public function evaluate(LoginData $data, AuthenticationContext $context): RateLimitDecision
    {
        $policyConfig = $this->config->getAbusePolicyConfig();

        // Backward compatibility: abuse_policy disabled = legacy behavior
        if (!$policyConfig['enabled']) {
            return $this->evaluateLegacy($data, $context);
        }

        // Multi-dimensional evaluation: check all enabled dimensions
        $normalizedId = EmailNormalizer::normalize($data->identifier);
        $normalizedIp = $this->normalizeIp($context->ipAddress);

        foreach ($policyConfig['dimensions'] as $dimension => $settings) {
            $key = $this->buildDimensionKey($dimension, $data, $context, $normalizedId, $normalizedIp);
            $maxAttempts = $settings['max_attempts'];

            if ($this->cacheLimiter->tooManyAttempts($key, $maxAttempts)) {
                $retryAfter = $this->cacheLimiter->availableIn($key);
                return RateLimitDecision::throttle($retryAfter, "{$dimension}_limit_exceeded");
            }
        }

        // Soft challenge threshold applies to the account budget before hard throttling.
        $challengeThreshold = (int) ($this->config->getRateLimitConfig('login')['challenge_threshold'] ?? 0);
        if ($challengeThreshold > 0 && isset($policyConfig['dimensions']['account'])) {
            $accountKey = $this->buildDimensionKey('account', $data, $context, $normalizedId, $normalizedIp);
            if ($this->cacheLimiter->attempts($accountKey) + 1 >= $challengeThreshold) {
                return RateLimitDecision::challenge(0, 'challenge_threshold_exceeded');
            }
        }

        // Distributed attack detection (optional)
        if ($this->distributedDetector !== null) {
            $risk = $this->distributedDetector->assess($context->ipAddress, $data->identifier);
            
            if ($risk->score >= 0.8) {
                return RateLimitDecision::challenge(0, 'distributed_attack_pattern');
            }
        }

        return RateLimitDecision::allow('within_all_budgets');
    }

    public function recordFailure(LoginData $data, AuthenticationContext $context): void
    {
        $policyConfig = $this->config->getAbusePolicyConfig();

        if (!$policyConfig['enabled']) {
            $this->rateLimiter->hit('login', $data->identifier, $context->ipAddress, $context->clientId);
            return;
        }

        $normalizedId = EmailNormalizer::normalize($data->identifier);
        $normalizedIp = $this->normalizeIp($context->ipAddress);

        foreach ($policyConfig['dimensions'] as $dimension => $settings) {
            $key = $this->buildDimensionKey($dimension, $data, $context, $normalizedId, $normalizedIp);
            $decaySeconds = $settings['decay_minutes'] * 60;
            $this->cacheLimiter->hit($key, $decaySeconds);
        }
    }

    public function clearFailures(LoginData $data, AuthenticationContext $context): void
    {
        $policyConfig = $this->config->getAbusePolicyConfig();

        if (!$policyConfig['enabled']) {
            $this->rateLimiter->clear('login', $data->identifier, $context->ipAddress, $context->clientId);
            return;
        }

        $normalizedId = EmailNormalizer::normalize($data->identifier);
        $normalizedIp = $this->normalizeIp($context->ipAddress);

        foreach ($policyConfig['dimensions'] as $dimension => $settings) {
            $key = $this->buildDimensionKey($dimension, $data, $context, $normalizedId, $normalizedIp);
            $this->cacheLimiter->clear($key);
        }
    }

    private function evaluateLegacy(LoginData $data, AuthenticationContext $context): RateLimitDecision
    {
        $rateConfig = $this->config->getRateLimitConfig('login');
        $attempts = $this->rateLimiter->attempts('login', $data->identifier, $context->ipAddress);
        $maxAttempts = $rateConfig['max_attempts'];
        $challengeThreshold = $rateConfig['challenge_threshold'] ?? 0;

        // Hard limit exceeded: throttle
        if ($attempts >= $maxAttempts) {
            $retryAfter = $this->rateLimiter->availableIn('login', $data->identifier, $context->ipAddress);
            return RateLimitDecision::throttle($retryAfter, 'composite_limit_exceeded');
        }

        // Soft limit exceeded but under hard limit: challenge
        // challengeThreshold is the attempt number (1-indexed), so we compare attempts+1
        if ($challengeThreshold > 0 && $attempts + 1 >= $challengeThreshold) {
            return RateLimitDecision::challenge(0, 'challenge_threshold_exceeded');
        }

        return RateLimitDecision::allow('within_composite_budget');
    }

    private function buildDimensionKey(
        string $dimension,
        LoginData $data,
        AuthenticationContext $context,
        ?string $normalizedIdentifier = null,
        ?string $normalizedIp = null
    ): string {
        $normalizedIdentifier ??= EmailNormalizer::normalize($data->identifier);
        $normalizedIp ??= $this->normalizeIp($context->ipAddress);

        $payload = match ($dimension) {
            'account'    => $normalizedIdentifier,
            'account_ip' => "{$normalizedIdentifier}|{$normalizedIp}",
            'client'     => "{$normalizedIdentifier}|{$context->clientId}",
            'network'    => $normalizedIp,
            'global'     => 'platform',
            default      => throw new \InvalidArgumentException("Unknown dimension: {$dimension}"),
        };

        $hash = hash('sha256', $payload);
        return "auth_rl:login:abuse:{$dimension}:{$hash}";
    }

    /**
     * Normalize IP to network prefix for rate limiting.
     * 
     * IPv4: /24 prefix (first 3 octets)
     * IPv6: /64 prefix (first 64 bits)
     * 
     * This prevents attackers from rotating IPs within the same subnet to bypass limits.
     */
    private function normalizeIp(string $ipAddress): string
    {
        $binary = @inet_pton($ipAddress);
        
        if ($binary === false) {
            return $ipAddress; // Invalid IP, use as-is
        }
        
        // IPv4: 4 bytes, extract /24 (first 3 bytes)
        if (strlen($binary) === 4) {
            return substr($binary, 0, 3) . "\x00";
        }
        
        // IPv6: 16 bytes, extract /64 (first 8 bytes)
        if (strlen($binary) === 16) {
            return substr($binary, 0, 8) . str_repeat("\x00", 8);
        }
        
        return $binary;
    }


    public function generateChallengeToken(LoginData $data, AuthenticationContext $context): string
    {
        $token = bin2hex(random_bytes(32));
        $rateConfig = $this->config->getRateLimitConfig('login');
        $ttl = max(1, (int) ($rateConfig['challenge_token_ttl'] ?? 300));

        // Store only a hash of the context; token consumption is atomic via pull().
        Cache::put(
            $this->buildChallengeKey($token),
            hash('sha256', $data->identifier . '|' . $context->ipAddress),
            $ttl
        );

        return $token;
    }

    public function verifyChallengeToken(string $token, LoginData $data, AuthenticationContext $context): bool
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return false;
        }

        $storedContext = Cache::pull($this->buildChallengeKey($token));
        $expectedContext = hash('sha256', $data->identifier . '|' . $context->ipAddress);

        return is_string($storedContext) && hash_equals($expectedContext, $storedContext);
    }

    private function buildChallengeKey(string $token): string
    {
        return "auth:challenge:{$token}";
    }

    public function getAccountAttempts(string $identifier, string $ipAddress, string $clientId = 'default'): int
    {
        $policyConfig = $this->config->getAbusePolicyConfig();

        if (!$policyConfig['enabled'] || !isset($policyConfig['dimensions']['account'])) {
            // Fallback: read the FeatureRateLimiter login counter
            return $this->rateLimiter->attempts('login', $identifier, $ipAddress, $clientId);
        }

        $normalizedIdentifier = EmailNormalizer::normalize($identifier);
        $hash = hash('sha256', $normalizedIdentifier);
        $key  = "auth_rl:login:abuse:account:{$hash}";

        return $this->cacheLimiter->attempts($key);
    }
}
