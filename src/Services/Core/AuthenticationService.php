<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Core;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\AuthenticationStrategyInterface;
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
use Vendor\LaravelAuthentication\Services\Security\AuthenticationAuditService;
use Vendor\LaravelAuthentication\Services\Security\LoginAttemptManager;
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
        private readonly LoginAttemptManager $attemptManager,
        private readonly AccountLockService $lockService,
        private readonly SessionSecurityService $sessionSecurity,
        private readonly TokenService $tokenService,
        private readonly AuthenticationAuditService $auditService,
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
            throw new AuthenticationException('Authentication service is currently disabled.');
        }

        // 1. Dispatch LoginAttempted event
        $this->events->dispatch(new LoginAttempted($data->identifier, $context, $data->strategy));

        // 2. Check Rate Limiter
        if ($this->attemptManager->isThrottled($data, $context)) {
            $secondsRemaining = $this->attemptManager->availableIn($data, $context);

            $this->auditService->logEvent(
                SecurityEventType::LOGIN_THROTTLED,
                $data->identifier,
                $context,
                AuthenticationResult::failed(AuthenticationStatus::THROTTLED, 'Too many login attempts.')
            );

            throw new AuthenticationThrottledException($secondsRemaining);
        }

        // 3. Resolve Strategy
        $strategy = $this->resolveStrategy($data);

        // 4. Resolve User Identity
        $user = $strategy->resolveUser($data, $context);

        // 5. Account Lockout Verification
        if ($user !== null && $this->lockService->isLocked($user)) {
            $this->auditService->logEvent(
                SecurityEventType::ACCOUNT_LOCKED,
                $data->identifier,
                $context,
                AuthenticationResult::failed(AuthenticationStatus::ACCOUNT_LOCKED, 'Account is locked.')
            );

            throw new AccountLockedException($this->config->getLockoutDurationMinutes());
        }

        // 6. Validate Password & Credentials
        $isValid = false;
        if ($user !== null) {
            $isValid = $strategy->validateCredentials($user, $data);
        }

        // Fail Case: Invalid credentials or non-existent user (Identical timing / response for User Enumeration Defense)
        if (!$isValid || $user === null) {
            $this->attemptManager->recordFailedAttempt($data, $context);

            if ($user !== null) {
                $this->lockService->recordFailureAndCheckLockout($user, $context);
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

        // 7. Success Preparation
        $this->attemptManager->clearAttempts($data, $context);
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
            throw new InvalidStrategyException("The requested authentication strategy [{$strategyName}] is not registered.");
        }

        return $this->strategyRegistry->get($strategyName);
    }
}
