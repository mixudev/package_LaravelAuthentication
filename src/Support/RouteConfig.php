<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException;

/**
 * Resolves and validates package route wiring so a host configuration edit
 * cannot silently remove authentication from a protected endpoint.
 *
 * Two invariants live here:
 * 1. The configured API auth middleware must be a non-empty list of strings.
 *    An empty list would leave "authenticated" API routes open to anyone.
 * 2. Route names use Laravel's conventional bare names by default. Hosts can
 *    opt into a namespace with routes.web.route_name_prefix.
 */
final class RouteConfig
{
    /**
     * Middleware aliases that authenticate the caller. Anything else (throttle,
     * session-security, bindings) is defence in depth and may be configured
     * separately — it never counts as authentication.
     *
     * A guard suffix (`auth:sanctum`) is matched on the alias before the colon.
     *
     * @var array<int, string>
     */
    private const AUTH_MIDDLEWARE = [
        'auth',
        'auth.basic',
        'auth.session',
    ];

    /**
     * Resolve the API auth middleware or fail closed.
     *
     * @return array<int, string>
     */
    public static function apiAuthMiddleware(): array
    {
        $configured = config('authentication.routes.api.auth_middleware', ['auth:sanctum']);

        $middleware = array_values(array_unique(array_filter(
            is_array($configured) ? $configured : [$configured],
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        )));

        if ($middleware === []) {
            throw new AuthenticationConfigurationException(
                'authentication.routes.api.auth_middleware must list at least one authentication middleware; '
                . 'an empty list leaves authenticated API routes open to anonymous callers.'
            );
        }

        foreach ($middleware as $entry) {
            self::assertAuthenticates($entry);
        }

        return $middleware;
    }

    /**
     * Build a package route name under the configured web name prefix.
     *
     * @param string $name Logical route name, e.g. `login`.
     */
    public static function name(string $name): string
    {
        return self::webRouteNamePrefix() . $name;
    }

    /**
     * Route name prefix for package web routes.
     *
     * An empty prefix intentionally produces Laravel's conventional bare names.
     * A configured non-empty prefix is normalized with a trailing dot.
     */
    public static function webRouteNamePrefix(): string
    {
        $prefix = trim((string) config('authentication.routes.web.route_name_prefix', ''));

        if ($prefix === '') {
            return '';
        }

        return str_ends_with($prefix, '.') ? $prefix : $prefix . '.';
    }

    /**
     * URL prefix applied to package web routes.
     */
    public static function webUrlPrefix(): string
    {
        return trim((string) config('authentication.routes.web.prefix', ''), '/');
    }

    /**
     * Does this middleware entry authenticate the caller?
     *
     * Accepts a resolved middleware stack entry, which may be a string or a
     * middleware instance.
     */
    public static function isAuthMiddleware(mixed $middleware): bool
    {
        if (is_object($middleware)) {
            if (! method_exists($middleware, '__toString')) {
                return false;
            }

            $middleware = (string) $middleware;
        }

        if (! is_string($middleware)) {
            return false;
        }

        return in_array(explode(':', trim($middleware))[0], self::AUTH_MIDDLEWARE, true);
    }

    private static function assertAuthenticates(string $middleware): void
    {
        if (self::isAuthMiddleware($middleware)) {
            return;
        }

        throw new AuthenticationConfigurationException(
            'authentication.routes.api.auth_middleware must authenticate the caller; '
            . "[{$middleware}] does not perform authentication."
        );
    }
}