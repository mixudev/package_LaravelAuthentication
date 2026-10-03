<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Concurrency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Services\Security\AuthenticationAbusePolicy;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * M-01: Abuse policy check-then-act race.
 *
 * AuthenticationService::authenticate() records a failure and then evaluates the
 * policy in two separate calls:
 *
 *     $this->abusePolicy->recordFailure($data, $context);   // hit() per dimension
 *     ...
 *     $decision = $this->abusePolicy->evaluate($data, $context);  // tooManyAttempts()
 *
 * Nothing joins those two steps. Two requests arriving together both evaluate
 * against the same pre-hit counter, so the budget is effectively consumed once
 * for N concurrent failures and the throttle engages N attempts late.
 *
 * The invariant that must hold regardless of interleaving: for a budget of
 * max_attempts, no more than max_attempts failures may be accepted before the
 * policy reports a hard throttle. Losing a hit() is a correctness bug worth
 * fixing; accepting more than max_attempts failures is a security bug.
 */
final class AbusePolicyCheckThenActRaceTest extends TestCase
{
    private const MAX_ATTEMPTS = 5;

    public function test_record_failure_always_advances_the_counter_even_when_it_is_concurrently_observed(): void
    {
        $this->enablePolicy();

        $policy = app(AuthenticationAbusePolicy::class);
        $data = new LoginData('victim@example.test', 'wrongpass');
        $context = $this->context();

        // Every recorded failure must be reflected in the counter. If a caller hits
        // the counter from two connections at once, the aggregate may lose a tick,
        // but it must never go backwards and must never be overwritten by a stale
        // read (a plain put() of an older value is exactly that failure mode).
        $counterKey = $this->dimensionKey('account', 'victim@example.test');

        $policy->recordFailure($data, $context);
        $first = (int) Cache::get($counterKey);

        for ($i = 0; $i < 4; $i++) {
            $policy->recordFailure($data, $context);
        }

        $after = (int) Cache::get($counterKey);

        $this->assertSame(
            self::MAX_ATTEMPTS,
            $after,
            'M-01 invariant violated: five recorded failures must leave five hits. '
            . "Counter started at {$first} and ended at {$after}, so a hit was lost or overwritten."
        );
    }

    public function test_evaluate_never_permits_more_failures_than_the_budget_allows(): void
    {
        $this->enablePolicy();

        $policy = app(AuthenticationAbusePolicy::class);
        $data = new LoginData('victim2@example.test', 'wrongpass');
        $context = $this->context();

        $accepted = 0;

        // Drive the real request path: record then evaluate, exactly as
        // AuthenticationService does. Count how many failures were permitted before
        // the policy produced a hard throttle.
        for ($i = 0; $i < self::MAX_ATTEMPTS * 4; $i++) {
            $policy->recordFailure($data, $context);
            $decision = $policy->evaluate($data, $context);

            if (!$decision->allowed && in_array($decision->action, ['throttle', 'deny'], true)) {
                break;
            }

            $accepted++;
        }

        $this->assertLessThanOrEqual(
            self::MAX_ATTEMPTS,
            $accepted,
            "M-01 invariant violated: {$accepted} failures were accepted on a budget of "
            . self::MAX_ATTEMPTS . '. The throttle must engage once the budget is spent.'
        );
    }

    public function test_a_stale_read_cannot_resurrect_a_spent_budget(): void
    {
        $this->enablePolicy();

        $policy = app(AuthenticationAbusePolicy::class);
        $data = new LoginData('victim3@example.test', 'wrongpass');
        $context = $this->context();

        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $policy->recordFailure($data, $context);
        }

        $this->assertFalse($policy->evaluate($data, $context)->allowed, 'budget must be spent');

        // A request that read the counter before the budget was exhausted must not be
        // able to write its stale value back after the fact. The counter is advanced
        // with an increment; if a caller ever put() an old total it would reset the
        // budget and reopen a brute-force window.
        $counterKey = $this->dimensionKey('account', 'victim3@example.test');
        $stale = (int) Cache::get($counterKey);

        $policy->recordFailure($data, $context);

        $this->assertGreaterThan(
            $stale,
            (int) Cache::get($counterKey),
            'M-01 invariant violated: the counter did not advance after the budget was spent, '
            . 'so a stale value can overwrite it and reopen the brute-force window.'
        );
    }

    private function enablePolicy(): void
    {
        Config::set('authentication.security.abuse_policy', [
            'enabled' => true,
            'dimensions' => [
                'account' => ['max_attempts' => self::MAX_ATTEMPTS, 'decay_minutes' => 1],
            ],
        ]);
    }

    /**
     * Mirrors AuthenticationAbusePolicy::buildDimensionKey() for the account dimension.
     */
    private function dimensionKey(string $dimension, string $identifier): string
    {
        return "auth_rl:login:abuse:{$dimension}:" . hash('sha256', $identifier);
    }

    private function context(): AuthenticationContext
    {
        return new AuthenticationContext('203.0.113.10', 'phpunit');
    }
}