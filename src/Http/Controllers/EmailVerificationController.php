<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Events\EmailVerified;
use Vendor\LaravelAuthentication\Support\AuthenticationView;

class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly AuditLoggerInterface $auditService
    ) {}

    public function notice(): View|JsonResponse
    {
        // This view never shipped, so the old `view()->exists()` guard always failed
        // and GET /email/verify answered with a JSON "verify your email" message
        // instead of a page. The view now exists and the guard is gone.
        $viewName = AuthenticationView::resolve('verify_email', 'authentication::pages.auth.verify-email');

        return view($viewName);
    }

    /**
     * BP-05 FIX: Validasi bahwa {id} dan {hash} dari URL cocok dengan user yang sedang login.
     * Tanpa validasi ini, signed URL milik orang lain bisa memverifikasi email user yang berbeda.
     *
     * Catatan: Route sudah dilindungi `signed` middleware, tapi controller harus tetap memvalidasi
     * bahwa URL tersebut memang ditujukan untuk user yang sedang terautentikasi (bukan user lain).
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('authentication.login');
        }

        // Pastikan {id} di URL sesuai dengan user yang login
        if ((string) $user->getKey() !== $id) {
            abort(403, 'This verification link does not belong to your account.');
        }

        // Pastikan {hash} di URL sesuai dengan email user yang login (sama dengan Illuminate\Auth\Middleware\EnsureEmailIsVerified)
        if (!hash_equals(sha1((string) ($user->getEmailForVerification() ?? '')), $hash)) {
            abort(403, 'Invalid email verification link. The hash does not match your current email address.');
        }

        if ($user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Email is already verified.'])
                : redirect()->intended('/dashboard?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            event(new EmailVerified($user, AuthenticationContext::fromRequest($request)));

            $this->auditService->logEvent(
                SecurityEventType::EMAIL_VERIFIED,
                (string) $user->getAuthIdentifier(),
                AuthenticationContext::fromRequest($request)
            );
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Email verified successfully.'])
            : redirect()->intended('/dashboard?verified=1');
    }

    public function resend(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user !== null && $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Email already verified.'])
                : redirect()->intended('/dashboard');
        }

        if ($user !== null) {
            $user->sendEmailVerificationNotification();
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Verification link sent.'])
            : back()->with('status', 'verification-link-sent');
    }
}
