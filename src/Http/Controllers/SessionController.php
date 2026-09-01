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
        $summary = $this->sessionManager->getSummary($user, $currentSessionId);

        /** @var \Vendor\LaravelAuthentication\Services\TwoFactorService $twoFactorService */
        $twoFactorService = app(\Vendor\LaravelAuthentication\Services\TwoFactorService::class);
        $isTwoFactorEnabled = $twoFactorService->isEnabledFor($user);

        /** @var \Vendor\LaravelAuthentication\Services\PasskeyService $passkeyService */
        $passkeyService = app(\Vendor\LaravelAuthentication\Services\PasskeyService::class);
        $passkeys = $passkeyService->getUserPasskeys($user);

        /** @var \Vendor\LaravelAuthentication\Services\AuthenticationAuditService $auditService */
        $auditService = app(\Vendor\LaravelAuthentication\Services\AuthenticationAuditService::class);
        $recentLogins = $auditService->getRecentLogins($user, 5);

        if ($request->expectsJson()) {
            return response()->json([
                'status'             => 'success',
                'user'               => $user,
                'is_2fa_enabled'     => $isTwoFactorEnabled,
                'passkeys'           => $passkeys,
                'summary'            => $summary,
                'sessions'           => $sessions,
                'recent_logins'      => $recentLogins,
            ]);
        }

        $viewName = $this->config->getView('sessions', 'authentication::sessions');

        return response()->view($viewName, [
            'user'               => $user,
            'isTwoFactorEnabled' => $isTwoFactorEnabled,
            'passkeys'           => $passkeys,
            'summary'            => $summary,
            'sessions'           => $sessions,
            'recentLogins'       => $recentLogins,
            'brandName'          => config('authentication.ui.brand_name', config('app.name', 'Laravel')),
            'brandTagline'       => config('authentication.ui.brand_tagline', 'Pusat Keamanan & Manajemen Akun'),
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
