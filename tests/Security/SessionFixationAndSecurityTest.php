<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Vendor\LaravelAuthentication\Http\Middleware\EnsureSessionSecurity;
use Vendor\LaravelAuthentication\Services\SessionSecurityService;
use Vendor\LaravelAuthentication\Tests\TestCase;

class SessionFixationAndSecurityTest extends TestCase
{
    public function test_session_regenerates_id_on_login(): void
    {
        $session = new Store('test_session', new ArraySessionHandler(60));
        $session->start();
        $initialId = $session->getId();

        $request = Request::create('/login', 'POST');
        $request->setLaravelSession($session);

        $service = new SessionSecurityService();
        $service->regenerate($request);

        $this->assertNotEquals($initialId, $request->session()->getId());
    }

    public function test_session_security_middleware_attaches_security_headers(): void
    {
        $middleware = new EnsureSessionSecurity();
        $request = Request::create('/dashboard', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }
}
