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

    public function verify(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user !== null && $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Email is already verified.'])
                : redirect()->intended('/dashboard?verified=1');
        }

        if ($user !== null && $user->markEmailAsVerified()) {
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
