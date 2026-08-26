<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Cache\RateLimiter;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\Normalizers\EmailNormalizer;

/**
 * Manages granular rate limiting per feature (login, otp_request, otp_verify, registration, 2fa, etc.)
 */
class FeatureRateLimiter implements FeatureRateLimiterInterface
{
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly AuthenticationConfig $config
    ) {}

    public function tooManyAttempts(string $feature, ?string $identifier, string $ipAddress): bool
    {
        $rateConfig = $this->config->getRateLimitConfig($feature);

        if (!$rateConfig['enabled']) {
            return false;
        }

        $key = $this->resolveKey($feature, $identifier, $ipAddress, $rateConfig['strategy']);

        return $this->rateLimiter->tooManyAttempts($key, $rateConfig['max_attempts']);
    }

    public function hit(string $feature, ?string $identifier, string $ipAddress): int
    {
        $rateConfig = $this->config->getRateLimitConfig($feature);

        if (!$rateConfig['enabled']) {
            return 0;
        }

        $key = $this->resolveKey($feature, $identifier, $ipAddress, $rateConfig['strategy']);
        $decaySeconds = $rateConfig['decay_minutes'] * 60;

        return $this->rateLimiter->hit($key, $decaySeconds);
    }

    public function clear(string $feature, ?string $identifier, string $ipAddress): void
    {
        $rateConfig = $this->config->getRateLimitConfig($feature);
        $key = $this->resolveKey($feature, $identifier, $ipAddress, $rateConfig['strategy']);

        $this->rateLimiter->clear($key);
    }

    public function availableIn(string $feature, ?string $identifier, string $ipAddress): int
    {
        $rateConfig = $this->config->getRateLimitConfig($feature);
        $key = $this->resolveKey($feature, $identifier, $ipAddress, $rateConfig['strategy']);

        return $this->rateLimiter->availableIn($key);
    }

    public function remaining(string $feature, ?string $identifier, string $ipAddress): int
    {
        $rateConfig = $this->config->getRateLimitConfig($feature);
        $key = $this->resolveKey($feature, $identifier, $ipAddress, $rateConfig['strategy']);

        return $this->rateLimiter->retriesLeft($key, $rateConfig['max_attempts']);
    }

    public function attempts(string $feature, ?string $identifier, string $ipAddress): int
    {
        $rateConfig = $this->config->getRateLimitConfig($feature);
        $key = $this->resolveKey($feature, $identifier, $ipAddress, $rateConfig['strategy']);

        return $this->rateLimiter->attempts($key);
    }

    protected function resolveKey(string $feature, ?string $identifier, string $ipAddress, string $strategy): string
    {
        $normId = $identifier !== null ? EmailNormalizer::normalize($identifier) : '';

        return match ($strategy) {
            'ip'         => "auth_rl:{$feature}:ip:{$ipAddress}",
            'identifier' => "auth_rl:{$feature}:id:" . sha1($normId),
            default      => "auth_rl:{$feature}:comp:" . sha1("{$normId}|{$ipAddress}"),
        };
    }
}
