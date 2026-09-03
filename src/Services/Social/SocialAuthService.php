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
use Vendor\LaravelAuthentication\Services\Security\AuthenticationAuditService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Service managing OAuth 2.0 / Social authentication flows via Laravel Socialite.
 */
class SocialAuthService implements SocialAuthServiceInterface
{
    public function __construct(
        private readonly CredentialResolverInterface $resolver,
        private readonly Dispatcher $events,
        private readonly Hasher $hasher,
        private readonly AuthenticationAuditService $auditService,
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

        /** @var object $socialUser */
        $socialUser = $driver->user();

        $email = method_exists($socialUser, 'getEmail') ? $socialUser->getEmail() : ($socialUser->email ?? null);
        $name = method_exists($socialUser, 'getName') ? $socialUser->getName() : ($socialUser->name ?? ($socialUser->nickname ?? 'OAuth User'));

        if (empty($email)) {
            throw new AuthenticationException("Unable to retrieve verified email address from [{$provider}].");
        }

        // SEC-06/14 FIX: When the provider exposes an email-verified claim, require it before
        // allowing sign-in/linking. Prevents account takeover via an unverified social email
        // colliding with an existing local account.
        $emailVerified = null;
        if (property_exists($socialUser, 'user') || (is_object($socialUser) && isset($socialUser->user))) {
            $raw = (array) $socialUser->user;
            if (array_key_exists('email_verified', $raw)) {
                $emailVerified = (bool) $raw['email_verified'];
            }
        }
        if ($emailVerified === false) {
            throw new AuthenticationException("Unable to confirm the verified status of the email address from [{$provider}].");
        }
        // Note: providers without an email_verified claim (e.g. GitHub public email) fall through —
        // sign-in proceeds, but auto-registration is gated by the email-exists lookup below.

        $emailCol = $this->config->getIdentifierColumn('email');
        $user = $this->resolver->resolveByColumn($emailCol, $email);

        if ($user === null) {
            if (!$this->config->isSocialAutoRegisterEnabled()) {
                throw new AuthenticationException("No account linked to email [{$email}]. Registration is required.");
            }

            // Automatically create local user record
            $userModelClass = $this->config->getUserModel();
            /** @var Model&Authenticatable $user */
            $user = new $userModelClass();
            $passwordCol = $this->config->getIdentifierColumn('password');

            $user->forceFill([
                'name'         => $name ?: 'OAuth User',
                $emailCol      => $email,
                $passwordCol   => $this->hasher->make(Str::random(32)),
            ]);

            $user->save();
        }

        // BP-03 FIX: Social login tidak boleh membypass account lockout.
        // Cek setelah user resolve (baik existing maupun auto-registered).
        if ($this->lockService->isLocked($user)) {
            throw new AccountLockedException($this->config->getLockoutDurationMinutes());
        }

        $this->events->dispatch(new LoginSucceeded($user, $context, "social_{$provider}"));

        $this->auditService->logEvent(
            SecurityEventType::LOGIN_SUCCESS,
            $email,
            $context,
            null,
            ['provider' => $provider, 'action' => 'social_login', 'user_id' => $user->getAuthIdentifier()]
        );

        return $user;
    }
}
