<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Captcha;

use Illuminate\Support\Facades\Http;
use Vendor\LaravelAuthentication\Contracts\CaptchaDriverInterface;

class TurnstileDriver implements CaptchaDriverInterface
{
    public function __construct(
        private readonly string $secretKey
    ) {}

    public function verify(string $token, ?string $ipAddress = null): bool
    {
        if (empty($token) || empty($this->secretKey)) {
            return false;
        }

        try {
            $payload = [
                'secret'   => $this->secretKey,
                'response' => $token,
            ];

            if ($ipAddress !== null) {
                $payload['remoteip'] = $ipAddress;
            }

            $response = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', $payload);

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function renderWidget(string $siteKey, array $options = []): string
    {
        $theme = $options['theme'] ?? 'auto';
        return <<<HTML
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<div class="cf-turnstile" data-sitekey="{$siteKey}" data-theme="{$theme}"></div>
HTML;
    }
}
