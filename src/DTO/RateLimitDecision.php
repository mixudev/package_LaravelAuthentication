<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\DTO;

/**
 * Immutable decision from authentication abuse policy evaluation.
 * 
 * Purpose:
 * Encapsulates rate-limiting and challenge decisions without exposing internal counter state.
 * 
 * Security considerations:
 * - reasonCode is for internal metrics/alerting only; never exposed to client.
 * - action is bounded enum-like string to prevent arbitrary values.
 */
final class RateLimitDecision
{
    /**
     * @param bool $allowed Whether the request is allowed to proceed
     * @param string $action One of: allow, challenge, throttle, deny
     * @param int $retryAfter Seconds until retry is allowed (0 if allowed immediately)
     * @param string $reasonCode Internal reason code for telemetry (never exposed to user)
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $action,
        public readonly int $retryAfter,
        public readonly string $reasonCode = 'default'
    ) {
        if (!in_array($action, ['allow', 'challenge', 'throttle', 'deny'], true)) {
            throw new \InvalidArgumentException("Invalid action: {$action}. Must be allow, challenge, throttle, or deny.");
        }

        if ($retryAfter < 0) {
            throw new \InvalidArgumentException("retryAfter must be non-negative, got: {$retryAfter}");
        }
    }

    /**
     * Factory: Allow the request immediately.
     */
    public static function allow(string $reasonCode = 'within_budget'): self
    {
        return new self(
            allowed: true,
            action: 'allow',
            retryAfter: 0,
            reasonCode: $reasonCode
        );
    }

    /**
     * Factory: Request a challenge (e.g., CAPTCHA) before allowing.
     */
    public static function challenge(int $retryAfter = 0, string $reasonCode = 'risk_threshold'): self
    {
        return new self(
            allowed: false,
            action: 'challenge',
            retryAfter: $retryAfter,
            reasonCode: $reasonCode
        );
    }

    /**
     * Factory: Throttle the request (rate limit exceeded).
     */
    public static function throttle(int $retryAfter, string $reasonCode = 'rate_limit_exceeded'): self
    {
        return new self(
            allowed: false,
            action: 'throttle',
            retryAfter: $retryAfter,
            reasonCode: $reasonCode
        );
    }

    /**
     * Factory: Hard deny (e.g., account locked, feature disabled).
     */
    public static function deny(string $reasonCode = 'policy_violation'): self
    {
        return new self(
            allowed: false,
            action: 'deny',
            retryAfter: 0,
            reasonCode: $reasonCode
        );
    }
}
