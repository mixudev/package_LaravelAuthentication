<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware dynamically enforcing a package-configured or passed guard parameter.
 */
class AuthenticateWithCustomGuard
{
    public function __construct(
        private readonly AuthFactory $auth
    ) {}

    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        $resolvedGuard = $guard ?? (string) config('authentication.guard', 'web');

        if (!$this->auth->guard($resolvedGuard)->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
