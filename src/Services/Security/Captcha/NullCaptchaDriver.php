<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security\Captcha;

use Vendor\LaravelAuthentication\Contracts\CaptchaDriverInterface;

class NullCaptchaDriver implements CaptchaDriverInterface
{
    public function verify(string $token, ?string $ipAddress = null): bool
    {
        return true;
    }

    public function renderWidget(string $siteKey, array $options = []): string
    {
        return '';
    }
}
