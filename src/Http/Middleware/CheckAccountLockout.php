<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vendor\LaravelAuthentication\Services\Security\AccountLockService;

/**
 * Validates that the authenticated user is not locked by administrative or automated policies.
 */
class CheckAccountLockout
{
    public function __construct(
        private readonly AccountLockService $lockService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $this->lockService->isLocked($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Account is temporarily locked. Please contact support.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'identifier' => 'Account is temporarily locked.',
            ]);
        }

        return $next($request);
    }
}
