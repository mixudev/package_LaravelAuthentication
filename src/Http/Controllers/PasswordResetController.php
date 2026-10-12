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
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Http\Requests\ForgotPasswordRequest;
use Vendor\LaravelAuthentication\Http\Requests\ResetPasswordRequest;
use Vendor\LaravelAuthentication\Jobs\SendPasswordResetLinkJob;
use Vendor\LaravelAuthentication\Services\Password\PasswordService;
use Vendor\LaravelAuthentication\Services\Session\SessionManagerService;
use Vendor\LaravelAuthentication\Support\AuthenticationView;
use Vendor\LaravelAuthentication\Support\RouteConfig;
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
            abort(404, (string) __('authentication::messages.password_reset_disabled'));
        }

        // A stale published config can name a view that no longer exists; falling back
        // to JSON would make a browser GET return an API message.
        $viewName = AuthenticationView::resolve('forgot_password', 'authentication::pages.password.forgot-password');

        return view($viewName);
    }

    public function sendResetLinkEmail(ForgotPasswordRequest $request): RedirectResponse|JsonResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            abort(404, (string) __('authentication::messages.password_reset_disabled'));
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
        $email = (string) $request->input('email', '');

        // Store email for the reset-password form display (no email in URL).
        // Stored in session with a short-lived approach; the session is flash-like
        // and is cleared after the reset completes.
        $request->session()->put('password_reset_email', $email);

        // Optionally resolve display name (best-effort, no enumeration leak: we store
        // the name only if user already exists — but we store the email regardless).
        $userModel = config('authentication.user_model');
        if (is_string($userModel) && class_exists($userModel)) {
            try {
                $user = $userModel::query()->where('email', $email)->first();
                if ($user !== null && isset($user->name)) {
                    $request->session()->put('password_reset_name', (string) $user->name);
                }
            } catch (\Throwable) {
                // no-op: silently skip on any error; we never reveal user existence
            }
        }

        if ((bool) config('authentication.mail.queue', true)) {
            SendPasswordResetLinkJob::dispatch($email);
        } else {
            Password::broker()->sendResetLink($request->only('email'));
        }

        // Dispatch event regardless of status (user enumeration: no difference in response)
        $this->events->dispatch(new PasswordResetRequested(
            (string) $request->input('email', ''),
            AuthenticationContext::fromRequest($request)
        ));

        // Audit reset request (masked identifier, no enumeration signal)
        $this->auditService->logEvent(
            SecurityEventType::PASSWORD_RESET_REQUESTED,
            (string) $request->input('email', ''),
            AuthenticationContext::fromRequest($request)
        );

        // Normalize timing to prevent timing-based enumeration attacks
        usleep(random_int(50_000, 150_000));

        $genericMessage = (string) __('authentication::messages.password_reset_link_sent');

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
            abort(404, (string) __('authentication::messages.password_reset_disabled'));
        }

        // A stale published config can name a view that no longer exists; falling back
        // to JSON would make a browser GET return an API message.
        $viewName = AuthenticationView::resolve('reset_password', 'authentication::pages.password.reset-password');

        $email = session('password_reset_email');
        if ($email === null || $email === '') {
            $email = $request->query('email');
        }

        return view($viewName, [
            'token' => $token,
            'email' => $email,
            'name'  => session('password_reset_name'),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            abort(404, (string) __('authentication::messages.password_reset_disabled'));
        }

        $claimKey = $this->claimResetToken($request);

        if ($claimKey === null) {
            return back()->withErrors(['email' => $this->passwordResetStatusMessage(Password::INVALID_TOKEN)]);
        }

        // SEC: Strict allowlist — only token, email, password, password_confirmation
        // are passed to the broker. Any extra payload the attacker may have injected
        // is silently dropped here; it never reaches the broker callback.
        $allowed = ['email', 'password', 'password_confirmation', 'token', '_token', '_method'];
        $isJson  = $request->expectsJson();
        foreach (array_keys($request->all()) as $field) {
            if (!in_array($field, $allowed, true)) {
                if ($isJson) {
                    return response()->json([
                        'status'  => 'failed',
                        'message' => (string) __('authentication::messages.password_reset_invalid'),
                        'errors'  => [
                            'password' => [(string) __('authentication::messages.password_reset_invalid')],
                        ],
                    ], 422);
                }

                return back()->withErrors(['password' => (string) __('authentication::messages.password_reset_invalid')]);
            }
        }

        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');

        try {
            $status = Password::broker()->reset(
                $credentials,
                function ($user, $password) use ($request) {
                    $this->completeReset($user, $password, $request);
                }
            );
        } catch (AuthenticationException $e) {
            Cache::forget($claimKey);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['password' => $e->getMessage()]);
        }

        if ($status !== Password::PASSWORD_RESET) {
            Cache::forget($claimKey);
        }

        // Invalidate the password_reset_email session key after successful reset
        if ($status === Password::PASSWORD_RESET) {
            $request->session()->forget(['password_reset_email', 'password_reset_name']);
        }

        return $status === Password::PASSWORD_RESET
            ? redirect()->route(RouteConfig::name('login'))->with('status', $this->passwordResetStatusMessage($status))
            : back()->withErrors(['email' => $this->passwordResetStatusMessage($status)]);
    }

    public function apiSendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            return response()->json([
                'status'  => 'error',
                'message' => (string) __('authentication::messages.password_reset_disabled'),
            ], 403);
        }

        if ($this->isForgotPasswordThrottled($request)) {
            return response()->json([
                'status'  => 'throttled',
                'message' => $this->forgotPasswordThrottleMessage($request),
            ], 429);
        }

        // SEC-02 FIX: Timing normalization identical to web endpoint to prevent user enumeration
        $email = (string) $request->input('email', '');

        if ($request->hasSession()) {
            $request->session()->put('password_reset_email', $email);
            $userModel = config('authentication.user_model');
            if (is_string($userModel) && class_exists($userModel)) {
                try {
                    $user = $userModel::query()->where('email', $email)->first();
                    if ($user !== null && isset($user->name)) {
                        $request->session()->put('password_reset_name', (string) $user->name);
                    }
                } catch (\Throwable) {
                    // no-op
                }
            }
        }

        if ((bool) config('authentication.mail.queue', true)) {
            SendPasswordResetLinkJob::dispatch($email);
        } else {
            Password::broker()->sendResetLink($request->only('email'));
        }

        $this->events->dispatch(new PasswordResetRequested(
            (string) $request->input('email', ''),
            AuthenticationContext::fromRequest($request)
        ));

        // Audit reset request (masked identifier, no enumeration signal)
        $this->auditService->logEvent(
            SecurityEventType::PASSWORD_RESET_REQUESTED,
            (string) $request->input('email', ''),
            AuthenticationContext::fromRequest($request)
        );

        // Normalize timing to prevent timing-based enumeration attacks (match web endpoint)
        usleep(random_int(50_000, 150_000));

        // Always return generic success to prevent user enumeration
        return response()->json([
            'status'  => 'success',
            'message' => (string) __('authentication::messages.password_reset_generic'),
        ]);
    }

    public function apiResetPassword(ResetPasswordRequest $request): JsonResponse
    {
        if (! (bool) config('authentication.features.forgot_password.enabled', true)) {
            return response()->json([
                'status'  => 'error',
                'message' => (string) __('authentication::messages.password_reset_disabled'),
            ], 403);
        }

        $claimKey = $this->claimResetToken($request);

        if ($claimKey === null) {
            return response()->json([
                'status'  => 'failed',
                'message' => (string) __('authentication::messages.password_reset_invalid'),
            ], 400);
        }

        try {
            $status = Password::broker()->reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) use ($request) {
                    $this->completeReset($user, $password, $request);
                }
            );
        } catch (AuthenticationException $e) {
            Cache::forget($claimKey);

            return response()->json([
                'status'  => 'failed',
                'message' => $e->getMessage(),
                'errors'  => [
                    'password' => [$e->getMessage()],
                ],
            ], 422);
        }

        if ($status !== Password::PASSWORD_RESET) {
            Cache::forget($claimKey);
        }

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status'  => 'success',
                'message' => (string) __('authentication::messages.password_reset_done'),
            ]);
        }

        return response()->json([
            'status'  => 'failed',
            'message' => (string) __('authentication::messages.password_reset_invalid'),
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

        $context = AuthenticationContext::fromRequest($request);

        $this->passwordService->updatePassword($user, $password, $context);
        $this->sessionManager->revokeAllAfterCredentialChange($user);

        $this->events->dispatch(new PasswordResetCompleted(
            $user,
            $context
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

    /**
     * Laravel's PasswordBroker answers with host-app keys (`passwords.token`,
     * `passwords.reset`, `passwords.throttled`, ...). A host that never
     * publishes `lang/{locale}/passwords.php` gets the raw key rendered as
     * "passwords.token", so map every broker status onto the package catalogue.
     */
    private function passwordResetStatusMessage(string $status): string
    {
        $key = match ($status) {
            'passwords.token'     => 'password_reset_token_invalid',
            'passwords.user'      => 'password_reset_invalid',
            'passwords.throttled' => 'throttle_error_unknown',
            'passwords.sent'      => 'password_reset_link_sent',
            'passwords.reset'     => 'password_reset_done',
            default               => 'password_reset_invalid',
        };

        $message = (string) __('authentication::messages.'.$key);

        // A stale published package catalogue can miss the key; never show the raw key.
        return $message === 'authentication::messages.'.$key
            ? (string) __('authentication::messages.password_reset_invalid')
            : $message;
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
