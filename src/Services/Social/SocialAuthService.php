<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Social;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Vendor\LaravelAuthentication\Contracts\CredentialResolverInterface;
use Vendor\LaravelAuthentication\Contracts\SocialAuthServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Services\Security\AccountLockService;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\CircuitBreaker;
use Vendor\LaravelAuthentication\Support\CircuitBreakerOpenException;

/**
 * Service managing OAuth 2.0 / Social authentication flows via Laravel Socialite.
 */
class SocialAuthService implements SocialAuthServiceInterface
{
    public function __construct(
        private readonly CredentialResolverInterface $resolver,
        private readonly Dispatcher $events,
        private readonly Hasher $hasher,
        private readonly AuditLoggerInterface $auditService,
        private readonly AuthenticationConfig $config,
        private readonly AccountLockService $lockService
    ) {}

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->isSocialEnabled();
    }

    public function isProviderEnabled(string $provider): bool
    {
        return $this->isEnabled() && $this->config->isSocialProviderEnabled($provider);
    }

    protected function getSocialiteDriver(string $provider): mixed
    {
        if (!class_exists('\Laravel\Socialite\Facades\Socialite')) {
            throw new AuthenticationConfigurationException(
                'Social authentication requires the [laravel/socialite] package. Please install it with: composer require laravel/socialite'
            );
        }

        if (!$this->isProviderEnabled($provider)) {
            throw new AuthenticationException("Social provider [{$provider}] is disabled or not configured.");
        }

        // Dynamically bridge credentials from config/authentication.php into Socialite
        $providerConfig = $this->config->getSocialProviderConfig($provider);
        if (!empty($providerConfig['client_id'])) {
            config([
                "services.{$provider}" => array_merge(
                    (array) config("services.{$provider}", []),
                    [
                        'client_id'     => $providerConfig['client_id'],
                        'client_secret' => $providerConfig['client_secret'] ?? '',
                        'redirect'      => $providerConfig['redirect'] ?? url("/auth/{$provider}/callback"),
                    ]
                ),
            ]);
        }

        /** @var \Laravel\Socialite\Contracts\Factory $factory */
        $factory = app(\Laravel\Socialite\Contracts\Factory::class);
        $driver = $factory->driver($provider);

        $scopes = (array) ($providerConfig['scopes'] ?? []);
        if (!empty($scopes) && method_exists($driver, 'scopes')) {
            $driver->scopes($scopes);
        }

        return $driver;
    }

    public function getRedirectResponse(string $provider): RedirectResponse
    {
        $driver = $this->getSocialiteDriver($provider);
        return $driver->redirect();
    }

    public function handleCallback(string $provider, AuthenticationContext $context, bool $stateless = false): Authenticatable
    {
        $driver = $this->getSocialiteDriver($provider);

        if ($stateless && method_exists($driver, 'stateless')) {
            $driver = $driver->stateless();
        }

        // PERF-08: Circuit breaker protects against cascading failure when OAuth provider is down.
        // After 5 consecutive failures, fail fast for 60 seconds before retry.
        $breaker = new CircuitBreaker("oauth.{$provider}", failureThreshold: 5, timeout: 60);

        try {
            /** @var object $socialUser */
            $socialUser = $breaker->call(fn() => $driver->user());
        } catch (CircuitBreakerOpenException $e) {
            throw new AuthenticationException(
                "OAuth provider [{$provider}] is temporarily unavailable. Please try again later.",
                previous: $e
            );
        }

        $email = method_exists($socialUser, 'getEmail') ? $socialUser->getEmail() : ($socialUser->email ?? null);
        $name = method_exists($socialUser, 'getName') ? $socialUser->getName() : ($socialUser->name ?? ($socialUser->nickname ?? 'OAuth User'));

        if (empty($email)) {
            throw new AuthenticationException("Unable to retrieve verified email address from [{$provider}].");
        }

        // SEC-06/14/15 HARDENING: Strict email verification check
        // Prevents account takeover where an attacker uses an unverified social email
        // colliding with an existing local account or creating an unverified account.
        $emailVerified = null;
        if (property_exists($socialUser, 'user') || (is_object($socialUser) && isset($socialUser->user))) {
            $raw = (array) $socialUser->user;
            if (array_key_exists('email_verified', $raw)) {
                $emailVerified = (bool) $raw['email_verified'];
            } elseif (array_key_exists('verified', $raw)) {
                $emailVerified = (bool) $raw['verified'];
            }
        }

        // GitHub-specific: GitHub does not return email_verified field in API response.
        // Treat GitHub provider as implicitly verified when email is present in the response,
        // because GitHub only returns verified primary email by default.
        if ($provider === 'github' && $emailVerified === null) {
            $emailVerified = true;
        }

        // Strict verification: if provider explicitly says unverified, always reject.
        if ($emailVerified === false) {
            throw new AuthenticationException("Unable to confirm the verified status of the email address from [{$provider}].");
        }

        $emailCol = $this->config->getIdentifierColumn('email');
        $user = $this->resolver->resolveByColumn($emailCol, $email);

        // SEC-CRITICAL: If linking to an existing account, fail-closed if email verification is not confirmed
        // Unless explicitly relaxed via config, linking existing accounts requires confirmed email verification.
        $requireStrictLink = (bool) config('authentication.features.social.strict_email_verification', true);
        if ($user !== null && $emailVerified !== true && $requireStrictLink) {
            throw new AuthenticationException("Cannot link existing account with unverified email from [{$provider}]. Verified email required.");
        }

        if ($user === null) {
            if (!$this->config->isSocialAutoRegisterEnabled()) {
                throw new AuthenticationException("No account linked to email [{$email}]. Registration is required.");
            }

            // Automatically create local user record
            $userModelClass = $this->config->getUserModel();
            /** @var Model&Authenticatable $user */
            $user = new $userModelClass();
            $passwordCol = $this->config->getIdentifierColumn('password');

            $payload = [
                'name'         => $name ?: 'OAuth User',
                $emailCol      => $email,
                $passwordCol   => $this->hasher->make(Str::random(32)),
            ];

            // If user model has email_verified_at column, populate it if email is verified
            if ($emailVerified === true && method_exists($user, 'hasCast') && $user->isFillable('email_verified_at')) {
                $payload['email_verified_at'] = now();
            } elseif ($emailVerified === true && in_array('email_verified_at', $user->getFillable(), true)) {
                $payload['email_verified_at'] = now();
            }

            $user->forceFill($payload);
            $user->save();
        }

        // BP-03 FIX: Social login tidak boleh membypass account lockout.
        // Cek setelah user resolve (baik existing maupun auto-registered).
        if ($this->lockService->isLocked($user)) {
            throw new AccountLockedException($this->config->getLockoutDurationMinutes());
        }

        $this->events->dispatch(new LoginSucceeded($user, $context, "social_{$provider}"));

        // HIGH-07 MITIGATION: Log provider for cross-provider account link detection
        // Future enhancement: add oauth_provider column + explicit consent flow
        $metadata = [
            'provider' => $provider,
            'action' => 'social_login',
            'user_id' => $user->getAuthIdentifier(),
            'email_verified' => $emailVerified === true ? 'true' : 'unverified_or_null',
        ];

        $this->auditService->logEvent(
            SecurityEventType::LOGIN_SUCCESS,
            $email,
            $context,
            null,
            $metadata
        );

        return $user;
    }
}
