<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Rules\LoginIdentifierRule;
use Vendor\LaravelAuthentication\Rules\SecurityPolicyRule;

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
        return [
            'identifier' => ['required_without_all:email,username', 'string', new LoginIdentifierRule(), new SecurityPolicyRule()],
            'email'      => ['nullable', 'string', new LoginIdentifierRule()],
            'username'   => ['nullable', 'string', new LoginIdentifierRule()],
            'password'   => ['required', 'string'],
            'remember'   => ['nullable', 'boolean'],
            'strategy'   => ['nullable', 'string', 'max:64'],
        ];
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
