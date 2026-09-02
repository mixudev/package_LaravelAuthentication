<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security;

use Vendor\LaravelAuthentication\Contracts\CaptchaDriverInterface;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\Services\Security\Captcha\HcaptchaDriver;
use Vendor\LaravelAuthentication\Services\Security\Captcha\NullCaptchaDriver;
use Vendor\LaravelAuthentication\Services\Security\Captcha\RecaptchaDriver;
use Vendor\LaravelAuthentication\Services\Security\Captcha\TurnstileDriver;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class CaptchaService
{
    private CaptchaDriverInterface $driver;

    public function __construct(
        private readonly AuthenticationConfig $config,
        private readonly FeatureRateLimiterInterface $rateLimiter
    ) {
        $this->driver = $this->resolveDriver();
    }

    public function isEnabled(): bool
    {
        return $this->config->isCaptchaEnabled();
    }

    /**
     * Determine if CAPTCHA must be displayed/checked for the given request.
     * If trigger_after_failed_attempts == 0, it is always required.
     * If > 0, it requires CAPTCHA only if failed attempts >= threshold.
     */
    public function shouldShowCaptcha(?string $identifier, string $ipAddress): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $threshold = $this->config->getCaptchaTriggerThreshold();

        if ($threshold <= 0) {
            return true;
        }

        $attempts = $this->rateLimiter->attempts('login', $identifier, $ipAddress);

        return $attempts >= $threshold;
    }

    public function verify(?string $token, ?string $ipAddress = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        return $this->driver->verify($token, $ipAddress);
    }

    public function renderWidget(array $options = []): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        return $this->driver->renderWidget($this->config->getCaptchaSiteKey(), $options);
    }

    public function getSiteKey(): string
    {
        return $this->config->getCaptchaSiteKey();
    }

    public function getDriverName(): string
    {
        return $this->config->getCaptchaDriver();
    }

    protected function resolveDriver(): CaptchaDriverInterface
    {
        if (!$this->isEnabled()) {
            return new NullCaptchaDriver();
        }

        $driver = $this->config->getCaptchaDriver();
        $secretKey = $this->config->getCaptchaSecretKey();

        return match ($driver) {
            'turnstile'    => new TurnstileDriver($secretKey),
            'recaptcha_v2' => new RecaptchaDriver($secretKey, 'v2'),
            'recaptcha_v3' => new RecaptchaDriver($secretKey, 'v3'),
            'hcaptcha'     => new HcaptchaDriver($secretKey),
            default        => new NullCaptchaDriver(),
        };
    }
}
