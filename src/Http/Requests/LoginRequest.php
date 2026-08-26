<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Rules\LoginIdentifierRule;
use Vendor\LaravelAuthentication\Rules\SecurityPolicyRule;
use Vendor\LaravelAuthentication\Rules\ValidCaptcha;
use Vendor\LaravelAuthentication\Services\CaptchaService;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $identifier = (string) ($this->input('identifier') ?? $this->input('email') ?? $this->input('username') ?? '');
        $ip         = (string) $this->ip();

        $rules = [
            'identifier' => ['required_without_all:email,username', 'string', new LoginIdentifierRule(), new SecurityPolicyRule()],
            'email'      => ['nullable', 'string', new LoginIdentifierRule()],
            'username'   => ['nullable', 'string', new LoginIdentifierRule()],
            'password'   => ['required', 'string'],
            'remember'   => ['nullable', 'boolean'],
            'strategy'   => ['nullable', 'string', 'max:64'],
        ];

        // Adaptif: tambahkan validasi CAPTCHA hanya jika service mengharuskannya
        /** @var CaptchaService $captchaService */
        $captchaService = app(CaptchaService::class);
        if ($captchaService->isEnabled() && $captchaService->shouldShowCaptcha($identifier ?: null, $ip)) {
            $rules['cf-turnstile-response'] = ['nullable', new ValidCaptcha($identifier ?: null, $ip)];
            $rules['g-recaptcha-response']  = ['nullable', new ValidCaptcha($identifier ?: null, $ip)];
            $rules['h-captcha-response']    = ['nullable', new ValidCaptcha($identifier ?: null, $ip)];
        }

        return $rules;
    }

    public function toDto(): LoginData
    {
        $identifier = (string) ($this->input('identifier') ?? $this->input('email') ?? $this->input('username'));

        return new LoginData(
            identifier: $identifier,
            password: (string) $this->input('password'),
            remember: $this->boolean('remember'),
            strategy: $this->input('strategy') ? (string) $this->input('strategy') : null,
            extra: $this->except(['password'])
        );
    }
}
