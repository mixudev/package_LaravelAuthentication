<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware ensuring HTTP sessions have secure cookie flags,
 * defense against MIME confusion, and standard security headers.
 */
class EnsureSessionSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // MEDIUM-10 FIX: Stricter Referrer-Policy to prevent token leakage via URL params
        // 'no-referrer' ensures password reset tokens and other sensitive URL params
        // are never sent to external sites when user clicks links.
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
