<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Security\Captcha;

use Illuminate\Support\Facades\Http;
use Vendor\LaravelAuthentication\Contracts\CaptchaDriverInterface;

class RecaptchaDriver implements CaptchaDriverInterface
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $version = 'v2'
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

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', $payload);

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function renderWidget(string $siteKey, array $options = []): string
    {
        if ($this->version === 'v3') {
            return <<<HTML
<script src="https://www.google.com/recaptcha/api.js?render={$siteKey}" async defer></script>
HTML;
        }

        $theme = $options['theme'] ?? 'light';
        return <<<HTML
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<div class="g-recaptcha" data-sitekey="{$siteKey}" data-theme="{$theme}"></div>
HTML;
    }
}
