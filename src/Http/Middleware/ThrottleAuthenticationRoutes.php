<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-IP safety net applied to every package route.
 *
 * This is a FLOOR, not the primary defence: feature-specific buckets in
 * FeatureRateLimiter are tighter and identifier-aware. This guarantees that a
 * route added later — or one whose in-controller check is forgotten — is never
 * left completely unbounded.
 *
 * Fails open when security.global_throttle.max_attempts is 0.
 */
final class ThrottleAuthenticationRoutes
{
    public function __construct(private readonly RateLimiter $rateLimiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $maxAttempts = (int) config('authentication.security.global_throttle.max_attempts', 120);

        if ($maxAttempts <= 0) {
            return $next($request);
        }

        $decaySeconds = max(1, (int) config('authentication.security.global_throttle.decay_minutes', 1)) * 60;
        $key = 'auth_global_rl:' . hash('sha256', (string) $request->ip());

        if ($this->rateLimiter->tooManyAttempts($key, $maxAttempts)) {
            $seconds = $this->rateLimiter->availableIn($key);

            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'throttled',
                    'message' => (string) __('authentication::messages.throttle_error', ['seconds' => $seconds]),
                ], 429, ['Retry-After' => (string) $seconds]);
            }

            abort(429, (string) __('authentication::messages.throttle_error', ['seconds' => $seconds]));
        }

        $this->rateLimiter->hit($key, $decaySeconds);

        return $next($request);
    }
}
