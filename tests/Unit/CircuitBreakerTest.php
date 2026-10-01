<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;
use Vendor\LaravelAuthentication\Support\CircuitBreaker;
use Vendor\LaravelAuthentication\Support\CircuitBreakerOpenException;

class CircuitBreakerTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_circuit_starts_closed_and_allows_requests(): void
    {
        $breaker = new CircuitBreaker('test.service', failureThreshold: 3, timeout: 5);

        $result = $breaker->call(fn() => 'success');

        $this->assertEquals('success', $result);
        $this->assertFalse($breaker->isOpen());
    }

    public function test_circuit_opens_after_threshold_failures(): void
    {
        $breaker = new CircuitBreaker('test.failure', failureThreshold: 3, timeout: 5);

        // Fail 3 times (threshold)
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call(fn() => throw new \RuntimeException('External service down'));
            } catch (\RuntimeException) {
                // Expected
            }
        }

        // Circuit should be OPEN now
        $this->assertTrue($breaker->isOpen());

        // Next call should fail fast without executing callback
        $this->expectException(CircuitBreakerOpenException::class);
        $breaker->call(fn() => 'should_not_execute');
    }

    public function test_circuit_resets_failure_count_on_success(): void
    {
        $breaker = new CircuitBreaker('test.reset', failureThreshold: 3, timeout: 5);

        // Fail twice (below threshold)
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(fn() => throw new \RuntimeException('Transient failure'));
            } catch (\RuntimeException) {
                // Expected
            }
        }

        // Success resets counter
        $breaker->call(fn() => 'recovered');

        // Another failure should not trip (counter reset)
        try {
            $breaker->call(fn() => throw new \RuntimeException('One more failure'));
        } catch (\RuntimeException) {
            // Expected
        }

        // Circuit still closed (only 1 failure after reset)
        $this->assertFalse($breaker->isOpen());
    }

    public function test_circuit_transitions_to_half_open_after_timeout(): void
    {
        $breaker = new CircuitBreaker('test.recovery', failureThreshold: 2, timeout: 1);

        // Trip circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(fn() => throw new \RuntimeException('Down'));
            } catch (\RuntimeException) {
            }
        }

        $this->assertTrue($breaker->isOpen());

        // Wait for timeout + recovery probe
        sleep(2);

        // Next successful call should close circuit
        $result = $breaker->call(fn() => 'recovered');

        $this->assertEquals('recovered', $result);
        $this->assertFalse($breaker->isOpen());
    }

    public function test_circuit_reopens_if_half_open_probe_fails(): void
    {
        $breaker = new CircuitBreaker('test.reopen', failureThreshold: 2, timeout: 1);

        // Trip circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(fn() => throw new \RuntimeException('Down'));
            } catch (\RuntimeException) {
            }
        }

        $this->assertTrue($breaker->isOpen());

        // Wait for timeout
        sleep(2);

        // Probe fails → should reopen immediately
        try {
            $breaker->call(fn() => throw new \RuntimeException('Still down'));
        } catch (\RuntimeException) {
        }

        $this->assertTrue($breaker->isOpen());
    }

    public function test_call_with_fallback_returns_fallback_on_open(): void
    {
        $breaker = new CircuitBreaker('test.fallback', failureThreshold: 2, timeout: 5);

        // Trip circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(fn() => throw new \RuntimeException('Down'));
            } catch (\RuntimeException) {
            }
        }

        $result = $breaker->callWithFallback(fn() => 'real_data', 'fallback_data');

        $this->assertEquals('fallback_data', $result);
    }

    public function test_manual_reset_closes_circuit(): void
    {
        $breaker = new CircuitBreaker('test.manual', failureThreshold: 2, timeout: 60);

        // Trip circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(fn() => throw new \RuntimeException('Down'));
            } catch (\RuntimeException) {
            }
        }

        $this->assertTrue($breaker->isOpen());

        // Admin manually resets
        $breaker->reset();

        $this->assertFalse($breaker->isOpen());

        // Should work immediately
        $result = $breaker->call(fn() => 'recovered');
        $this->assertEquals('recovered', $result);
    }

    public function test_get_metrics_returns_state_and_counters(): void
    {
        $breaker = new CircuitBreaker('test.metrics', failureThreshold: 3, timeout: 5);

        // Fail once
        try {
            $breaker->call(fn() => throw new \RuntimeException('Fail'));
        } catch (\RuntimeException) {
        }

        $metrics = $breaker->getMetrics();

        $this->assertEquals('closed', $metrics['state']);
        $this->assertEquals(1, $metrics['failures']);
        // opened_at intentionally omitted from getMetrics() to prevent timing/info disclosure
    }
}
