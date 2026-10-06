<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Core;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\AuthenticationStrategyInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\AuthenticationResult;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Enums\AuthenticationStatus;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Events\LoginAttempted;
use Vendor\LaravelAuthentication\Events\LoginFailed;
use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Vendor\LaravelAuthentication\Events\LogoutPerformed;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Exceptions\InvalidStrategyException;
use Vendor\LaravelAuthentication\Exceptions\TwoFactorChallengeRequiredException;
use Vendor\LaravelAuthentication\Services\Security\AccountLockService;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\Contracts\AuthenticationAbusePolicyInterface;
use Vendor\LaravelAuthentication\Services\Session\DeviceTrustService;
use Vendor\LaravelAuthentication\Services\Session\NewDeviceDetectionService;
use Vendor\LaravelAuthentication\Services\Session\SessionSecurityService;
use Vendor\LaravelAuthentication\Services\TwoFactor\TwoFactorService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\AuthenticationStrategyRegistry;

/**
 * Purpose:
 * Central orchestration service executing the authentication lifecycle pipeline.
 *
 * Pipeline Flow:
 * Request -> Pre-check / Rate Limit -> Strategy Selection -> Identity Lookup
 * -> Credential Validation -> Post-check / Lockout -> Two-Factor Check
 * -> Session / Token Generation -> Device Registration -> Event Dispatch -> Security Audit -> Return Result
 */
class AuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Dispatcher $events,
        private readonly AuthenticationStrategyRegistry $strategyRegistry,
        private readonly AuthenticationAbusePolicyInterface $abusePolicy,
        private readonly AccountLockService $lockService,
        private readonly SessionSecurityService $sessionSecurity,
        private readonly TokenManagerInterface $tokenService,
        private readonly AuditLoggerInterface $auditService,
        private readonly TwoFactorService $twoFactorService,
        private readonly DeviceTrustService $deviceTrustService,
        private readonly NewDeviceDetectionService $newDeviceService,
        private readonly AuthenticationConfig $config
    ) {}

    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function authenticate(LoginData $data, AuthenticationContext $context): AuthenticationResult
    {
        if (!$this->isEnabled()) {
            throw new AuthenticationException((string) __('authentication::messages.auth_disabled_runtime'));
        }

        // 1. Dispatch LoginAttempted event
        $this->events->dispatch(new LoginAttempted($data->identifier, $context, $data->strategy));

        // 2. Resolve Strategy
        // Account lockout is checked before abuse throttling so durable account
        // lockout remains the authoritative response for known accounts.
        $strategy = $this->resolveStrategy($data);

        // 3. Resolve User Identity
        $user = $strategy->resolveUser($data, $context);

        // 4. Account Lockout Verification
        if ($user !== null && $this->lockService->isLocked($user)) {
            $this->auditService->logEvent(
                SecurityEventType::ACCOUNT_LOCKED,
                $data->identifier,
                $context,
                AuthenticationResult::failed(AuthenticationStatus::ACCOUNT_LOCKED, 'Account is locked.')
            );

            throw new AccountLockedException($this->config->getLockoutDurationMinutes());
        }

        // 5. Pre-check abuse policy rate limits (fail-fast DoS defense under high load)
        // If the client/network/account has already spent its rate budget from earlier failures,
        // reject immediately without performing CPU-intensive password hashing or allowing access.
        $preDecision = $this->abusePolicy->evaluate($data, $context);
        if (!$preDecision->allowed && in_array($preDecision->action, ['throttle', 'deny'], true)) {
            $this->auditService->logEvent(
                SecurityEventType::LOGIN_THROTTLED,
                $data->identifier,
                $context,
                AuthenticationResult::failed(AuthenticationStatus::THROTTLED, (string) __('authentication::messages.auth_too_many_attempts'))
            );

            throw new AuthenticationThrottledException(max(1, $preDecision->retryAfter));
        }

        // 6. Validate Password & Credentials
        $isValid = false;
        if ($user !== null) {
            $isValid = $strategy->validateCredentials($user, $data);
        } else {
            // Dummy hash check untuk normalize timing (user enumeration defense)
            // Prevent timing attack: non-existent user (5ms) vs wrong password (423ms)
            \Illuminate\Support\Facades\Hash::check(
                $data->password,
                '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' // bcrypt dummy
            );
        }

        // Fail Case: Invalid credentials or non-existent user (Identical timing / response for User Enumeration Defense)
        if (!$isValid || $user === null) {
            // Record failure in multi-dimensional abuse policy before checking lockout
            $this->abusePolicy->recordFailure($data, $context);

            if ($user !== null) {
                $this->lockService->recordFailureAndCheckLockout($user, $context);
            }

            // Check abuse policy throttle after lockout recorded.
            // Only hard verdicts (throttle/deny) block the request. A `challenge`
            // verdict is advisory: CaptchaService/CaptchaRequest enforces the CAPTCHA
            // on the next submission, so it must NOT become a hard exception here.
            $decision = $this->abusePolicy->evaluate($data, $context);
            if (!$decision->allowed && in_array($decision->action, ['throttle', 'deny'], true)) {
                $this->auditService->logEvent(
                    SecurityEventType::LOGIN_THROTTLED,
                    $data->identifier,
                    $context,
                    AuthenticationResult::failed(AuthenticationStatus::THROTTLED, (string) __('authentication::messages.auth_too_many_attempts'))
                );

                throw new AuthenticationThrottledException(max(1, $decision->retryAfter));
            }

            $this->events->dispatch(new LoginFailed($data->identifier, $context, 'Invalid credentials', $user));

            $this->auditService->logEvent(
                SecurityEventType::LOGIN_FAILURE,
                $data->identifier,
                $context,
                AuthenticationResult::failed(AuthenticationStatus::INVALID_CREDENTIALS)
            );

            throw new InvalidCredentialsException();
        }

        // 6. Success Preparation
        $this->abusePolicy->clearFailures($data, $context);
        $this->lockService->clearFailures($user);

        // 8. Two-Factor Authentication Check
        if ($this->twoFactorService->isEnabledFor($user)) {
            $isDeviceTrusted = request() ? $this->deviceTrustService->isTrusted($user, request()) : false;

            if (!$isDeviceTrusted) {
                if ($context->channel->value === 'web' && request()->hasSession()) {
                    request()->session()->put('auth.2fa.user_id', $user->getAuthIdentifier());
                    request()->session()->put('auth.2fa.remember', $data->remember);
                }

                throw new TwoFactorChallengeRequiredException($user);
            }
        }

        // 9. Establish Session or API Token
        $token = null;
        if ($context->channel->value === 'web') {
            $guard = $this->auth->guard($context->guard);
            if ($guard instanceof StatefulGuard && request()->hasSession()) {
                $this->sessionSecurity->loginUser($guard, $user, $data->remember, request());
            }
        } else {
            $token = $this->tokenService->createToken($user);
        }

        // 10. Record device / detect new device login
        $this->newDeviceService->handleLogin($user, $context);

        $result = AuthenticationResult::success($user, $token, [
            'strategy' => $strategy->name(),
            'channel'  => $context->channel->value,
        ]);

        $this->events->dispatch(new LoginSucceeded($user, $context, $strategy->name()));

        $this->auditService->logEvent(
            SecurityEventType::LOGIN_SUCCESS,
            $data->identifier,
            $context,
            $result
        );

        return $result;
    }

    public function logout(AuthenticationContext $context): void
    {
        $guard = $this->auth->guard($context->guard);
        $user = $guard->user();

        if ($context->channel->value === 'web') {
            if ($guard instanceof StatefulGuard) {
                $guard->logout();
            }

            // SEC-04 FIX: Invalidate 2FA device trust tokens server-side on logout so a
            // previously issued (and possibly stolen) trust cookie cannot be replayed.
            if ($user !== null) {
                $this->deviceTrustService->revokeUserTrust($user);
            }

            if (request()->hasSession()) {
                $this->sessionSecurity->invalidate(request());
            }
        } elseif ($user !== null) {
            $this->tokenService->revokeCurrentToken($user);
        }

        $this->events->dispatch(new LogoutPerformed($user, $context));

        $this->auditService->logEvent(
            SecurityEventType::LOGOUT,
            $user ? (string) $user->getAuthIdentifier() : null,
            $context
        );
    }

    protected function resolveStrategy(LoginData $data): AuthenticationStrategyInterface
    {
        $strategyName = $data->strategy ?: $this->config->getDefaultStrategy();

        if (!$this->strategyRegistry->has($strategyName)) {
            throw new InvalidStrategyException((string) __('authentication::messages.strategy_unsupported'));
        }

        return $this->strategyRegistry->get($strategyName);
    }
}
