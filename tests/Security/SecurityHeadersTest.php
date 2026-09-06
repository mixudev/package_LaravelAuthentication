<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Red-team / hardening test: security headers on package auth routes.
 *
 * Fix motivation: EnsureSessionSecurity middleware existed but was NEVER
 * registered (dead code) — no alias, no route attachment. Header-bypass
 * test: halaman auth harus selalu kirim X-Content-Type-Options/nosniff,
 * X-Frame-Options/SAMEORIGIN, Referrer-Policy.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_login_page_sends_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_register_page_sends_security_headers(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_api_login_route_sends_security_headers(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'nonexistent@example.com',
            'password'   => 'wrong',
        ]);

        // 422 (validation) atau 401 (credentials) — yang penting headers tetap ada.
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}