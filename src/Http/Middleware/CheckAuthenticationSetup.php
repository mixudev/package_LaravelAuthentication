<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Vendor\LaravelAuthentication\Support\SetupHealthChecker;

/**
 * CheckAuthenticationSetup Middleware
 *
 * Intercepts incoming requests to package routes and checks whether
 * the authentication package configuration is valid and complete.
 *
 * Behavior by environment:
 *   - local / staging  : Redirects to the setup warning UI page when
 *                        blocking errors are detected.
 *   - production        : Does NOT redirect — fail-closed exceptions
 *                        from the underlying services take effect as
 *                        normal (per the package's Strict Invariant #2).
 *
 * This middleware is registered automatically by AuthenticationServiceProvider
 * and applied to the guest authentication route group only.
 */
class CheckAuthenticationSetup
{
    public function __construct(
        private readonly SetupHealthChecker $checker,
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Only run in non-production environments to avoid any overhead
        // or unintended redirects in live systems.
        if ($this->isProductionEnvironment()) {
            return $next($request);
        }

        // Do not intercept the warning page itself (prevents redirect loops).
        if ($request->routeIs('authentication.setup.warning')) {
            return $next($request);
        }

        // If there are any blocking errors, redirect to the warning UI.
        if ($this->checker->hasErrors()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'configuration_error',
                    'message' => 'The authentication package has configuration errors. Please resolve them before using the authentication system.',
                    'issues'  => array_map(
                        fn ($issue) => [
                            'severity'    => $issue->severity,
                            'title'       => $issue->title,
                            'description' => $issue->description,
                            'fix'         => $issue->fix,
                            'category'    => $issue->category,
                        ],
                        array_filter($this->checker->check(), fn ($i) => $i->isError())
                    ),
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            return redirect()->route('authentication.setup.warning');
        }

        return $next($request);
    }

    /**
     * Determine if the current environment is production.
     * In production, middleware is a no-op and the normal fail-closed
     * exception flow takes effect.
     */
    private function isProductionEnvironment(): bool
    {
        return app()->environment('production');
    }
}
