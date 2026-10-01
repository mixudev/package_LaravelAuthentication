<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Circuit Breaker Pattern Implementation.
 *
 * ENTERPRISE: Protects against cascading failures when external dependencies
 * (OAuth providers, email queue, SMS gateway, geolocation API) become unavailable.
 *
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Circuit tripped after N failures, requests fail fast (no external call)
 * - HALF_OPEN: After timeout, allow 1 probe request to test recovery
 *
 * Usage:
 *   $breaker = new CircuitBreaker('oauth.google', failureThreshold: 5, timeout: 60);
 *   $result = $breaker->call(fn() => $socialite->driver('google')->user());
 *
 * Benefits:
 * - Fail fast when external service is down (don't wait for timeout)
 * - Auto-recovery after timeout period
 * - Prevents thundering herd on recovery
 * - Reduces load on failing dependency
 */
final class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    /**
     * @param string $name Unique identifier (e.g. 'oauth.google', 'email.queue')
     * @param int $failureThreshold Number of consecutive failures before opening
     * @param int $timeout Seconds to wait before attempting recovery (default: 60s)
     * @param int $successThreshold Successful calls required in half-open state (default: 1)
     */
    public function __construct(
        private readonly string $name,
        private readonly int $failureThreshold = 5,
        private readonly int $timeout = 60,
        private readonly int $successThreshold = 1
    ) {}

    /**
     * Execute callable through circuit breaker.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws CircuitBreakerOpenException Circuit is open (dependency down)
     * @throws Throwable Original exception if call fails in CLOSED/HALF_OPEN state
     */
    public function call(callable $callback): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset()) {
                $this->transitionTo(self::STATE_HALF_OPEN);
            } else {
                throw new CircuitBreakerOpenException(
                    "Circuit breaker [{$this->name}] is OPEN. Dependency unavailable."
                );
            }
        }

        try {
            $result = $callback();
            $this->recordSuccess();
            return $result;
        } catch (Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * Execute with fallback value on circuit open.
     *
     * @template T
     * @param callable(): T $callback
     * @param T $fallback
     * @return T
     */
    public function callWithFallback(callable $callback, mixed $fallback): mixed
    {
        try {
            return $this->call($callback);
        } catch (CircuitBreakerOpenException) {
            return $fallback;
        }
    }

    /**
     * Check if circuit is open (dependency down).
     */
    public function isOpen(): bool
    {
        return $this->getState() === self::STATE_OPEN;
    }

    /**
     * Manually reset circuit (admin intervention).
     */
    public function reset(): void
    {
        Cache::forget($this->stateKey());
        Cache::forget($this->failureCountKey());
        Cache::forget($this->successCountKey());
        Cache::forget($this->openedAtKey());
    }

    /**
     * Get current metrics (for monitoring/dashboard).
     *
     * @return array{state: string, failures: int, opened_at: ?int}
     */
    public function getMetrics(): array
    {
        return [
            'state' => $this->getState(),
            'failures' => (int) Cache::get($this->failureCountKey(), 0),
            'opened_at' => Cache::get($this->openedAtKey()),
        ];
    }

    // -------------------------------------------------------------------
    // Private Implementation
    // -------------------------------------------------------------------

    private function getState(): string
    {
        return (string) Cache::get($this->stateKey(), self::STATE_CLOSED);
    }

    private function transitionTo(string $state): void
    {
        Cache::put($this->stateKey(), $state, now()->addMinutes(10));

        if ($state === self::STATE_OPEN) {
            Cache::put($this->openedAtKey(), now()->timestamp, now()->addMinutes(10));
        }

        if ($state === self::STATE_CLOSED) {
            Cache::forget($this->failureCountKey());
            Cache::forget($this->successCountKey());
            Cache::forget($this->openedAtKey());
        }
    }

    private function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successCount = (int) Cache::get($this->successCountKey(), 0) + 1;
            Cache::put($this->successCountKey(), $successCount, now()->addMinutes(5));

            if ($successCount >= $this->successThreshold) {
                $this->transitionTo(self::STATE_CLOSED);
            }
        } elseif ($state === self::STATE_CLOSED) {
            Cache::forget($this->failureCountKey());
        }
    }

    private function recordFailure(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $this->transitionTo(self::STATE_OPEN);
            return;
        }

        if ($state === self::STATE_CLOSED) {
            $failureCount = (int) Cache::get($this->failureCountKey(), 0) + 1;
            Cache::put($this->failureCountKey(), $failureCount, now()->addMinutes(5));

            if ($failureCount >= $this->failureThreshold) {
                $this->transitionTo(self::STATE_OPEN);
            }
        }
    }

    private function shouldAttemptReset(): bool
    {
        $openedAt = Cache::get($this->openedAtKey());
        if ($openedAt === null) {
            return true;
        }

        $nowTs = (int) now()->timestamp;
        $openTs = (int) $openedAt;

        return ($nowTs - $openTs) >= $this->timeout;
    }

    private function stateKey(): string
    {
        return "circuit_breaker:{$this->name}:state";
    }

    private function failureCountKey(): string
    {
        return "circuit_breaker:{$this->name}:failures";
    }

    private function successCountKey(): string
    {
        return "circuit_breaker:{$this->name}:successes";
    }

    private function openedAtKey(): string
    {
        return "circuit_breaker:{$this->name}:opened_at";
    }
}

/**
 * Thrown when circuit breaker is OPEN and request is rejected.
 */
class CircuitBreakerOpenException extends \RuntimeException
{
}
