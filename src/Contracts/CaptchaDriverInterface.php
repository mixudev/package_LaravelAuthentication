<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

interface CaptchaDriverInterface
{
    /**
     * Verify the CAPTCHA response token.
     */
    public function verify(string $token, ?string $ipAddress = null): bool;

    /**
     * Get the HTML script/widget tags or metadata needed on the frontend.
     */
    public function renderWidget(string $siteKey, array $options = []): string;
}
