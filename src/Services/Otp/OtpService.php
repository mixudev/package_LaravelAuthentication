<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Otp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use SensitiveParameter;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialResolverInterface;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\Contracts\OtpServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Events\OtpGenerated;
use Vendor\LaravelAuthentication\Events\OtpVerified;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
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
        private readonly AuditLoggerInterface $auditService,
        private readonly AuthenticationConfig $config,
        private readonly FeatureRateLimiterInterface $rateLimiter
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

        // Enforce composite per-identifier+IP bucket first (pre-existing cooldown is per-identifier only)
        if ($this->rateLimiter->tooManyAttempts('otp_request', $normalized, $context->ipAddress, $context->clientId)) {
            throw new AuthenticationThrottledException(
                max(1, $this->rateLimiter->availableIn('otp_request', $normalized, $context->ipAddress, $context->clientId))
            );
        }

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
        $this->cache->forget($cacheKey . ':consumed');
        $payload = [
            'hash'         => hash('sha256', $code),
            'max_attempts' => $maxAttempts,
        ];

        $this->cache->put($cacheKey, $payload, now()->addMinutes($expiryMinutes));

        // H-02 FIX: Use add() instead of put() to prevent overwriting active counter
        // If verify() has already incremented the counter, this add() will fail (key exists)
        // and preserve the existing counter, preventing reset-to-zero race
        $this->cache->add($cacheKey . ':attempts', 0, now()->addMinutes($expiryMinutes));

        // Set cooldown throttle key
        $throttleSeconds = $this->config->getOtpThrottleSeconds();
        $this->cache->put($this->getThrottleKey($normalized), true, now()->addSeconds($throttleSeconds));

        // Hit the composite request bucket on every successful generate
        $this->rateLimiter->hit('otp_request', $normalized, $context->ipAddress, $context->clientId);

        // Attempt user lookup
        $emailCol = $this->config->getIdentifierColumn('email');
        $usernameCol = $this->config->getIdentifierColumn('username');
        $user = $this->resolver->resolveByColumns([$emailCol, $usernameCol], $normalized);

        // SECURITY: Always dispatch email when identifier is a valid email address,
        // regardless of whether user exists. This prevents user enumeration while
        // allowing legitimate users to receive codes. Non-email identifiers (username)
        // are ignored to prevent spam. The email view does not disclose account existence.
        $shouldSendEmail = filter_var($normalized, FILTER_VALIDATE_EMAIL);

        if ($shouldSendEmail) {
            $this->dispatchOtpEmail($user, $normalized, $code, $expiryMinutes);
        }

        // Dispatch framework event only if user exists (for internal hooks)
        if ($user !== null) {
            $this->events->dispatch(new OtpGenerated($user, $normalized, $context, $expiryMinutes));
        }

        $this->auditService->logEvent(
            SecurityEventType::LOGIN_ATTEMPT,
            $normalized,
            $context,
            null,
            ['action' => 'otp_generated', 'email_sent' => $shouldSendEmail]
        );

        return $code;
    }

    /**
     * Dispatch OTP Email notification using configured Mailer.
     *
     * @param Authenticatable|null $user User object if found, null otherwise
     * @param string $identifier The email address to send to
     * @param string $code The OTP code
     * @param int $expiryMinutes Code validity period
     */
    protected function dispatchOtpEmail(?Authenticatable $user, string $identifier, string $code, int $expiryMinutes): void
    {
        if (! (bool) config('authentication.features.otp.send_email', true)) {
            return;
        }

        // Only send to valid email addresses
        $recipientEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : null;

        // Fallback: try to extract email from user object if identifier is not an email
        if ($recipientEmail === null && $user !== null) {
            $userEmailProp = $user->email ?? (method_exists($user, 'getEmailForPasswordReset') ? $user->getEmailForPasswordReset() : null);
            if (!empty($userEmailProp) && is_string($userEmailProp)) {
                $recipientEmail = $userEmailProp;
            }
        }

        if (!empty($recipientEmail)) {
            try {
                $mailable = new \Vendor\LaravelAuthentication\Mail\OtpMail(
                    code: $code,
                    expiryMinutes: $expiryMinutes,
                    identifier: $identifier,
                    user: $user
                );

                $queueEnabled = (bool) config('authentication.mail.queue', false);

                if ($queueEnabled) {
                    \Illuminate\Support\Facades\Mail::to($recipientEmail)->queue($mailable);
                    \Illuminate\Support\Facades\Log::info('[AUTH] OTP email queued for background delivery', [
                        'recipient' => \Vendor\LaravelAuthentication\Support\SecurityHelper::maskIdentifier($recipientEmail),
                        'user_exists' => $user !== null,
                    ]);
                } else {
                    \Illuminate\Support\Facades\Mail::to($recipientEmail)->send($mailable);
                    \Illuminate\Support\Facades\Log::info('[AUTH] OTP email sent immediately (synchronous)', [
                        'recipient' => \Vendor\LaravelAuthentication\Support\SecurityHelper::maskIdentifier($recipientEmail),
                        'user_exists' => $user !== null,
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('OTP email dispatch failed', [
                    'recipient' => \Vendor\LaravelAuthentication\Support\SecurityHelper::maskIdentifier($recipientEmail),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                report($e);
            }
        } else {
            \Illuminate\Support\Facades\Log::warning('OTP email not sent: invalid recipient', [
                'identifier' => \Vendor\LaravelAuthentication\Support\SecurityHelper::maskIdentifier($identifier),
                'is_valid_email' => filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false,
            ]);
        }
    }

    public function verify(string $identifier, #[SensitiveParameter] string $code, AuthenticationContext $context): ?Authenticatable
    {
        if (!$this->isEnabled()) {
            throw new AuthenticationException('OTP authentication is currently disabled.');
        }

        $normalized = EmailNormalizer::normalize($identifier);

        if ($this->rateLimiter->tooManyAttempts('otp_verify', $normalized, $context->ipAddress, $context->clientId)) {
            throw new AuthenticationThrottledException(
                max(1, $this->rateLimiter->availableIn('otp_verify', $normalized, $context->ipAddress, $context->clientId))
            );
        }

        $cacheKey = $this->getCacheKey($normalized);

        /** @var array{hash: string, attempts: int, max_attempts: int}|null $data */
        $data = $this->cache->get($cacheKey);

        if ($data === null) {
            $this->rateLimiter->hit('otp_verify', $normalized, $context->ipAddress, $context->clientId);

            $this->auditService->logEvent(
                SecurityEventType::OTP_FAILED,
                $normalized,
                $context,
                null,
                ['reason' => 'expired_or_invalid']
            );

            throw new InvalidCredentialsException('The OTP code has expired or is invalid.');
        }

        // Track every verification request in the feature bucket before the per-code
        // counter can reject it, so rotating requests cannot bypass this budget.
        $this->rateLimiter->hit('otp_verify', $normalized, $context->ipAddress, $context->clientId);

        // SEC-CRITICAL FIX: Increment only; never overwrite the atomic counter.
        // generate() pre-seeds this key with its TTL. add() is a race-safe fallback
        // for OTP records created by older workers, and never overwrites an existing value.
        $attemptKey = $cacheKey . ':attempts';
        $this->cache->add($attemptKey, 0, now()->addMinutes($this->config->getOtpExpiryMinutes()));
        $currentAttempt = $this->cache->increment($attemptKey);

        if ($currentAttempt > $data['max_attempts']) {
            $this->cache->forget($cacheKey);
            $this->cache->forget($attemptKey);

            $this->auditService->logEvent(
                SecurityEventType::OTP_FAILED,
                $normalized,
                $context,
                null,
                ['reason' => 'max_attempts_exceeded']
            );

            throw new AuthenticationException('Too many invalid attempts. Please request a new OTP code.');
        }

        $inputHash = hash('sha256', trim($code));
        if (!hash_equals($data['hash'], $inputHash)) {
            $this->auditService->logEvent(
                SecurityEventType::OTP_FAILED,
                $normalized,
                $context,
                null,
                ['reason' => 'mismatch']
            );

            throw new InvalidCredentialsException('The provided OTP code is incorrect.');
        }

        // H-01 FIX: Claim successful consumption with an atomic add operation.
        // Only the first concurrent verifier can create this marker; all later
        // verifiers are rejected even if they read the payload before deletion.
        $consumedKey = $cacheKey . ':consumed';
        if (!$this->cache->add($consumedKey, true, now()->addMinutes($this->config->getOtpExpiryMinutes()))) {
            throw new InvalidCredentialsException('The OTP code has expired or is invalid.');
        }

        // Successfully verified: Invalidate immediately to prevent reuse
        $this->cache->forget($cacheKey);
        $this->cache->forget($cacheKey . ':attempts');
        $this->cache->forget($this->getThrottleKey($normalized));
        $this->rateLimiter->clear('otp_verify', $normalized, $context->ipAddress, $context->clientId);

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
