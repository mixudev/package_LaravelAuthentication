<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Vendor\LaravelAuthentication\Services\Security\CaptchaService;

class ValidCaptcha implements ValidationRule
{
    public function __construct(
        private readonly ?string $identifier = null,
        private readonly ?string $ipAddress = null
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var CaptchaService $captchaService */
        $captchaService = app(CaptchaService::class);

        if (!$captchaService->isEnabled()) {
            return;
        }

        $ip = $this->ipAddress ?: (string) request()->ip();

        // If adaptive threshold not met, pass through
        if (!$captchaService->shouldShowCaptcha($this->identifier, $ip)) {
            return;
        }

        $token = is_string($value) ? $value : '';

        if (!$captchaService->verify($token, $ip)) {
            $message = __('authentication::messages.captcha_failed', ['attribute' => $attribute]);
            $fail(is_string($message) ? $message : 'Captcha verification failed.');
        }
    }
}
