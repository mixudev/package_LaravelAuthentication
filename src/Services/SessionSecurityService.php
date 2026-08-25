<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Manages web session lifecycle: session ID regeneration (fixation protection),
 * full session invalidation on logout, and session CSRF token refresh.
 */
class SessionSecurityService
{
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
    }
}
