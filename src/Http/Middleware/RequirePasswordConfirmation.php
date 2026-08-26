<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Middleware ensuring sensitive actions require a recently confirmed password.
 */
class RequirePasswordConfirmation
{
    public function __construct(
        private readonly AuthenticationConfig $config
    ) {}

    public function handle(Request $request, Closure $next, ?int $customTimeout = null): Response
    {
        if (!$this->config->isConfirmPasswordEnabled()) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        $timeout = $customTimeout ?? $this->config->getConfirmPasswordTimeout();
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        if ((time() - $confirmedAt) > $timeout) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'                        => 'Password confirmation required.',
                    'password_confirmation_required' => true,
                ], 423);
            }

            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('password.confirm');
        }

        return $next($request);
    }
}
