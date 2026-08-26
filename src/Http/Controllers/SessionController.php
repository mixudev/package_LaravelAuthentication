<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Services\SessionManagerService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class SessionController extends Controller
{
    public function __construct(
        private readonly SessionManagerService $sessionManager,
        private readonly AuthenticationConfig $config
    ) {}

    public function index(Request $request): HttpResponse|JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;
        $sessions = $this->sessionManager->getActiveSessions($user, $currentSessionId);

        if ($request->expectsJson()) {
            return response()->json([
                'sessions' => $sessions,
            ]);
        }

        $viewName = $this->config->getView('sessions', 'authentication::sessions');

        return response()->view($viewName, [
            'sessions'     => $sessions,
            'brandName'    => config('authentication.ui.brand_name', config('app.name', 'Laravel')),
            'brandTagline' => config('authentication.ui.brand_tagline', 'Manajemen Sesi & Perangkat'),
        ]);
    }

    public function destroy(Request $request, string $sessionId): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $this->sessionManager->revokeSession($user, $sessionId);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Session revoked successfully.',
            ]);
        }

        return back()->with('status', __('authentication::messages.session_revoked'));
    }

    public function destroyOthers(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        try {
            $this->sessionManager->revokeOtherSessions($user, (string) $request->input('password'), $currentSessionId);
        } catch (InvalidCredentialsException) {
            throw ValidationException::withMessages([
                'password' => [__('authentication::messages.invalid_password')],
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'All other sessions revoked successfully.',
            ]);
        }

        return back()->with('status', __('authentication::messages.other_sessions_revoked'));
    }
}
