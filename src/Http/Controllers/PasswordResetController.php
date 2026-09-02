<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Vendor\LaravelAuthentication\Http\Requests\ForgotPasswordRequest;
use Vendor\LaravelAuthentication\Http\Requests\ResetPasswordRequest;
use Vendor\LaravelAuthentication\Services\PasswordService;

class PasswordResetController extends Controller
{
    public function __construct(
        protected readonly PasswordService $passwordService
    ) {}

    public function showLinkRequestForm(): View|JsonResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            abort(404, 'Password reset feature is currently disabled.');
        }

        $viewName = (string) config('authentication.views.forgot_password', 'authentication::forgot-password');

        if (view()->exists($viewName)) {
            return view($viewName);
        }

        return response()->json(['message' => 'Please request password reset link via POST.']);
    }

    public function sendResetLinkEmail(ForgotPasswordRequest $request): RedirectResponse|JsonResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            abort(404, 'Password reset feature is currently disabled.');
        }

        // Intentionally discard the broker status result.
        // We ALWAYS return the same generic success message to prevent user enumeration
        // (an attacker must not learn whether the submitted email exists in the database).
        Password::broker()->sendResetLink($request->only('email'));

        // Normalize timing to prevent timing-based enumeration attacks
        usleep(random_int(50_000, 150_000));

        /** @var string $genericMessage */
        $genericMessage = 'If an account with that email exists, a password reset link has been sent. Please check your inbox.';

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'success',
                'message' => $genericMessage,
            ]);
        }

        return back()->with('status', $genericMessage);
    }

    public function showResetForm(Request $request, string $token): View|JsonResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            abort(404, 'Password reset feature is currently disabled.');
        }

        $viewName = (string) config('authentication.views.reset_password', 'authentication::reset-password');

        if (view()->exists($viewName)) {
            return view($viewName, [
                'token' => $token,
                'email' => $request->query('email'),
            ]);
        }

        return response()->json([
            'message' => 'Please reset password via POST.',
            'token'   => $token,
            'email'   => $request->query('email'),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            abort(404, 'Password reset feature is currently disabled.');
        }

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $this->passwordService->updatePassword($user, $password);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', trans($status))
            : back()->withErrors(['email' => trans($status)]);
    }

    public function apiSendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Password reset feature is currently disabled.',
            ], 403);
        }

        $status = Password::broker()->sendResetLink($request->only('email'));

        // Always return generic success to prevent user enumeration
        return response()->json([
            'status'  => 'success',
            'message' => 'If an account exists with that email, a password reset link has been dispatched.',
        ]);
    }

    public function apiResetPassword(ResetPasswordRequest $request): JsonResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Password reset feature is currently disabled.',
            ], 403);
        }

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $this->passwordService->updatePassword($user, $password);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Password has been reset successfully.',
            ]);
        }

        return response()->json([
            'status'  => 'failed',
            'message' => 'Unable to reset password. The reset link is invalid or has expired.',
        ], 400);
    }
}
