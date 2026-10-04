<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

/**
 * Builds the localized throttle message from a retry window.
 *
 * Controllers and middleware reach the throttle state through different paths
 * (`AuthenticationThrottledException::$secondsRemaining`, `RateLimiter::availableIn()`,
 * or a decay config lookup). Every one of them previously called
 * `__('authentication::messages.throttle_error')` with no replacements, which
 * rendered the raw placeholder: "Too many attempts. Please try again in
 * :seconds seconds." A caller that forgot the replacement is a display bug, not
 * an enforcement bug, so it is fixed here once instead of at 12 call sites.
 */
final class ThrottleMessage
{
    /**
     * Localized throttle message for the given retry window.
     *
     * Falls back to a placeholder-free sentence when the configured decay is
     * unknown (0 seconds), because "in 0 seconds" is nonsense to a user.
     */
    public static function forSeconds(?int $seconds): string
    {
        $seconds = max(0, $seconds ?? 0);

        if ($seconds === 0) {
            return (string) __('authentication::messages.throttle_error_unknown');
        }

        return (string) __('authentication::messages.throttle_error', ['seconds' => $seconds]);
    }

    /**
     * Localized throttle message for a feature that uses minute-based decay.
     *
     * The retry window is always in seconds; this only documents the caller.
     */
    public static function forFeature(
        \Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface $rateLimiter,
        string $feature,
        ?string $identifier,
        string $ipAddress,
        string $clientId = 'default'
    ): string {
        return self::forSeconds($rateLimiter->availableIn($feature, $identifier, $ipAddress, $clientId));
    }
}