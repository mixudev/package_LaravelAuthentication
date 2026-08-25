<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

class LogoutController extends Controller
{
    public function __construct(
        protected readonly AuthenticationServiceInterface $authService
    ) {}

    public function logout(Request $request): RedirectResponse
    {
        $context = AuthenticationContext::fromRequest($request);
        $this->authService->logout($context);

        return redirect()->to(config('authentication.redirects.logout', '/login'));
    }

    public function apiLogout(Request $request): JsonResponse
    {
        $context = AuthenticationContext::fromRequest($request);
        $this->authService->logout($context);

        return response()->json([
            'status'  => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }
}
