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
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Events\EmailVerified;

class EmailVerificationController extends Controller
{
    public function notice(): View|JsonResponse
    {
        if (view()->exists('authentication::verify-email')) {
            return view('authentication::verify-email');
        }

        return response()->json(['message' => 'Please verify your email address.']);
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
                : redirect()->route('login');
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
