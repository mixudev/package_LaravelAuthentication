<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Session;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;

/**
 * Manages web session lifecycle: session ID regeneration (fixation protection),
 * full session invalidation on logout, session CSRF token refresh, and
 * max-active-sessions enforcement (SA-28).
 */
class SessionSecurityService
{
    public function __construct(
        private readonly SessionManagerService $sessionManager
    ) {}

    public function regenerate(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
    }

    public function invalidate(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    public function loginUser(StatefulGuard $guard, Authenticatable $user, bool $remember, Request $request): void
    {
        $guard->login($user, $remember);
        $this->regenerate($request);

        // SA-28: enforce max active sessions on every new web login.
        if ($request->hasSession()) {
            $this->sessionManager->enforceMaxActiveSessions($user, $request->session()->getId());
        }
    }
}