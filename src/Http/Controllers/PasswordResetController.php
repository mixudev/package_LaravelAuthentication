<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Events\PasswordResetCompleted;
use Vendor\LaravelAuthentication\Events\PasswordResetRequested;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Http\Requests\ForgotPasswordRequest;
use Vendor\LaravelAuthentication\Http\Requests\ResetPasswordRequest;
use Vendor\LaravelAuthentication\Services\Password\PasswordService;
use Vendor\LaravelAuthentication\Services\Session\SessionManagerService;
use Vendor\LaravelAuthentication\Support\ThrottleMessage;

class PasswordResetController extends Controller
{
    public function __construct(
        protected readonly PasswordService $passwordService,
        protected readonly Dispatcher $events,
        protected readonly AuditLoggerInterface $auditService,
        protected readonly FeatureRateLimiterInterface $rateLimiter,
        protected readonly SessionManagerService $sessionManager
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

        if ($this->isForgotPasswordThrottled($request)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'throttled',
                    'message' => $this->forgotPasswordThrottleMessage($request),
                ], 429);
            }

            throw ValidationException::withMessages([
                'email' => [$this->forgotPasswordThrottleMessage($request)],
            ]);
        }

        // Intentionally discard the broker status result.
        // We ALWAYS return the same generic success message to prevent user enumeration
        // (an attacker must not learn whether the submitted email exists in the database).
        $status = Password::broker()->sendResetLink($request->only('email'));

        // Dispatch event regardless of status (user enumeration: no difference in response)
        $this->events->dispatch(new PasswordResetRequested(
            (string) $request->input('email', ''),
            \Vendor\LaravelAuthentication\DTO\AuthenticationContext::fromRequest($request)
        ));

        // Audit reset request (masked identifier, no enumeration signal)
        $this->auditService->logEvent(
            SecurityEventType::PASSWORD_RESET_REQUESTED,
            (string) $request->input('email', ''),
            \Vendor\LaravelAuthentication\DTO\AuthenticationContext::fromRequest($request)
        );

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
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            abort(404, 'Password reset feature is currently disabled.');
        }

        $claimKey = $this->claimResetToken($request);

        if ($claimKey === null) {
            return back()->withErrors(['email' => trans(Password::INVALID_TOKEN)]);
        }

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($request) {
                $this->completeReset($user, $password, $request);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            Cache::forget($claimKey);
        }

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('authentication.login')->with('status', trans($status))
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

        if ($this->isForgotPasswordThrottled($request)) {
            return response()->json([
                'status'  => 'throttled',
                'message' => $this->forgotPasswordThrottleMessage($request),
            ], 429);
        }

        // SEC-02 FIX: Timing normalization identical to web endpoint to prevent user enumeration
        Password::broker()->sendResetLink($request->only('email'));

        $this->events->dispatch(new PasswordResetRequested(
            (string) $request->input('email', ''),
            \Vendor\LaravelAuthentication\DTO\AuthenticationContext::fromRequest($request)
        ));

        // Audit reset request (masked identifier, no enumeration signal)
        $this->auditService->logEvent(
            SecurityEventType::PASSWORD_RESET_REQUESTED,
            (string) $request->input('email', ''),
            \Vendor\LaravelAuthentication\DTO\AuthenticationContext::fromRequest($request)
        );

        // Normalize timing to prevent timing-based enumeration attacks (match web endpoint)
        usleep(random_int(50_000, 150_000));

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

        $claimKey = $this->claimResetToken($request);

        if ($claimKey === null) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Unable to reset password. The reset link is invalid or has expired.',
            ], 400);
        }

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($request) {
                $this->completeReset($user, $password, $request);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            Cache::forget($claimKey);
        }

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

    /**
     * Apply a validated password reset and terminate everything the old credential granted.
     *
     * PR-01: the callback runs inside PasswordBroker::reset(), which only deletes the
     * reset token afterwards. Without an explicit revocation here, any session or
     * remember-me token captured before the reset survives it, and the victim who reset
     * the password during a compromise is still compromised.
     *
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     */
    private function completeReset(
        \Illuminate\Contracts\Auth\CanResetPassword $user,
        #[\SensitiveParameter] string $password,
        Request $request
    ): void {
        if (!$user instanceof \Illuminate\Contracts\Auth\Authenticatable) {
            throw new \RuntimeException('Password reset user must implement Authenticatable.');
        }

        $this->passwordService->updatePassword($user, $password);
        $this->sessionManager->revokeAllAfterCredentialChange($user);

        $this->events->dispatch(new PasswordResetCompleted(
            $user,
            AuthenticationContext::fromRequest($request)
        ));
    }

    /**
     * Claim a reset credential before invoking Laravel's broker.
     *
     * PasswordBroker validates with exists() and deletes only after the callback,
     * leaving a concurrent redemption window. This marker is not the token store and
     * does not replace broker hashing/expiry validation; it is a short-lived atomic
     * per-email/token claim that serializes package requests before that window.
     */
    private function claimResetToken(Request $request): ?string
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $token = (string) $request->input('token', '');

        if ($email === '' || $token === '') {
            return null;
        }

        $claimKey = 'authentication:password-reset:claim:' . hash('sha256', $email . ':' . $token);
        $ttlMinutes = max(1, (int) config('auth.passwords.users.expire', 60));

        return Cache::add($claimKey, true, now()->addMinutes($ttlMinutes))
            ? $claimKey
            : null;
    }

    private function forgotPasswordThrottleMessage(Request $request): string
    {
        $ip = \Vendor\LaravelAuthentication\Support\ClientIpResolver::resolve($request);
        $clientId = AuthenticationContext::fromRequest($request)->clientId;
        $email = (string) $request->input('email', '');

        return ThrottleMessage::forSeconds(
            $this->rateLimiter->availableIn('forgot_password', $email !== '' ? $email : null, $ip, $clientId)
        );
    }

    private function isForgotPasswordThrottled(Request $request): bool
    {
        $ip = \Vendor\LaravelAuthentication\Support\ClientIpResolver::resolve($request);
        $clientId = AuthenticationContext::fromRequest($request)->clientId;
        $email = (string) $request->input('email', '');

        if ($this->rateLimiter->tooManyAttempts('forgot_password', $email !== '' ? $email : null, $ip, $clientId)) {
            return true;
        }

        $this->rateLimiter->hit('forgot_password', $email !== '' ? $email : null, $ip, $clientId);

        return false;
    }
}
