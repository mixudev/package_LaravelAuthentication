<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Otp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use SensitiveParameter;
use Vendor\LaravelAuthentication\Contracts\CredentialResolverInterface;
use Vendor\LaravelAuthentication\Contracts\OtpServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Events\OtpGenerated;
use Vendor\LaravelAuthentication\Events\OtpVerified;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Services\Security\AuthenticationAuditService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\Normalizers\EmailNormalizer;

/**
 * Service providing secure, rate-limited, single-use One-Time Password generation and verification.
 */
class OtpService implements OtpServiceInterface
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly Dispatcher $events,
        private readonly CredentialResolverInterface $resolver,
        private readonly AuthenticationAuditService $auditService,
        private readonly AuthenticationConfig $config
    ) {}

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->isOtpEnabled();
    }

    public function isThrottled(string $identifier, AuthenticationContext $context): bool
    {
        $throttleKey = $this->getThrottleKey($identifier);
        return $this->cache->has($throttleKey);
    }

    public function generate(string $identifier, AuthenticationContext $context): string
    {
        if (!$this->isEnabled()) {
            throw new AuthenticationException('OTP authentication is currently disabled.');
        }

        $normalized = EmailNormalizer::normalize($identifier);

        if ($this->isThrottled($normalized, $context)) {
            throw new AuthenticationException('An OTP was recently requested. Please wait before requesting another.');
        }

        $length = $this->config->getOtpLength();
        $type = $this->config->getOtpType();

        $code = $type === 'alphanumeric'
            ? Str::upper(Str::random($length))
            : str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $expiryMinutes = $this->config->getOtpExpiryMinutes();
        $maxAttempts = $this->config->getOtpMaxAttempts();

        $cacheKey = $this->getCacheKey($normalized);
        $payload = [
            'hash'         => hash('sha256', $code),
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
        ];

        $this->cache->put($cacheKey, $payload, now()->addMinutes($expiryMinutes));

        // Set cooldown throttle key
        $throttleSeconds = $this->config->getOtpThrottleSeconds();
        $this->cache->put($this->getThrottleKey($normalized), true, now()->addSeconds($throttleSeconds));

        // Attempt user lookup
        $emailCol = $this->config->getIdentifierColumn('email');
        $usernameCol = $this->config->getIdentifierColumn('username');
        $user = $this->resolver->resolveByColumns([$emailCol, $usernameCol], $normalized);

        // 1. Dispatch framework event only if user exists (prevent user enumeration & spamming)
        if ($user !== null) {
            $this->events->dispatch(new OtpGenerated($user, $normalized, $code, $context, $expiryMinutes));
            $this->dispatchOtpEmail($user, $normalized, $code, $expiryMinutes);
        }

        $this->auditService->logEvent(
            SecurityEventType::LOGIN_ATTEMPT,
            $normalized,
            $context,
            null,
            ['action' => 'otp_generated']
        );

        return $code;
    }

    /**
     * Dispatch OTP Email notification using configured Mailer.
     */
    protected function dispatchOtpEmail(?Authenticatable $user, string $identifier, string $code, int $expiryMinutes): void
    {
        if (! (bool) config('authentication.features.otp.send_email', true) || $user === null) {
            return;
        }

        $recipientEmail = null;

        $userEmailProp = $user->email ?? (method_exists($user, 'getEmailForPasswordReset') ? $user->getEmailForPasswordReset() : null);
        if (!empty($userEmailProp) && is_string($userEmailProp)) {
            $recipientEmail = $userEmailProp;
        }

        if (empty($recipientEmail) && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $recipientEmail = $identifier;
        }

        if (!empty($recipientEmail)) {
            try {
                $mailable = new \Vendor\LaravelAuthentication\Mail\OtpMail(
                    code: $code,
                    expiryMinutes: $expiryMinutes,
                    identifier: $identifier,
                    user: $user
                );

                if ((bool) config('authentication.mail.queue', false)) {
                    \Illuminate\Support\Facades\Mail::to($recipientEmail)->queue($mailable);
                } else {
                    \Illuminate\Support\Facades\Mail::to($recipientEmail)->send($mailable);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function verify(string $identifier, #[SensitiveParameter] string $code, AuthenticationContext $context): ?Authenticatable
    {
        if (!$this->isEnabled()) {
            throw new AuthenticationException('OTP authentication is currently disabled.');
        }

        $normalized = EmailNormalizer::normalize($identifier);
        $cacheKey = $this->getCacheKey($normalized);

        /** @var array{hash: string, attempts: int, max_attempts: int}|null $data */
        $data = $this->cache->get($cacheKey);

        if ($data === null) {
            throw new InvalidCredentialsException('The OTP code has expired or is invalid.');
        }

        $data['attempts']++;
        if ($data['attempts'] > $data['max_attempts']) {
            $this->cache->forget($cacheKey);
            throw new AuthenticationException('Too many invalid attempts. Please request a new OTP code.');
        }

        $this->cache->put($cacheKey, $data, now()->addMinutes($this->config->getOtpExpiryMinutes()));

        $inputHash = hash('sha256', trim($code));
        if (!hash_equals($data['hash'], $inputHash)) {
            throw new InvalidCredentialsException('The provided OTP code is incorrect.');
        }

        // Successfully verified: Invalidate immediately to prevent reuse
        $this->cache->forget($cacheKey);
        $this->cache->forget($this->getThrottleKey($normalized));

        $emailCol = $this->config->getIdentifierColumn('email');
        $usernameCol = $this->config->getIdentifierColumn('username');
        $user = $this->resolver->resolveByColumns([$emailCol, $usernameCol], $normalized);

        if ($user !== null) {
            $this->events->dispatch(new OtpVerified($user, $normalized, $context));

            $this->auditService->logEvent(
                SecurityEventType::LOGIN_SUCCESS,
                $normalized,
                $context,
                null,
                ['action' => 'otp_verified', 'user_id' => $user->getAuthIdentifier()]
            );
        }

        return $user;
    }

    protected function getCacheKey(string $identifier): string
    {
        return 'auth_otp_code|' . sha1($identifier);
    }

    protected function getThrottleKey(string $identifier): string
    {
        return 'auth_otp_throttle|' . sha1($identifier);
    }
}
