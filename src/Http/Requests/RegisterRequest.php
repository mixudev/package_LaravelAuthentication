<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Vendor\LaravelAuthentication\DTO\RegisterData;
use Vendor\LaravelAuthentication\Rules\PasswordRule;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class RegisterRequest extends FormRequest
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
        /** @var AuthenticationConfig $config */
        $config = app(AuthenticationConfig::class);
        $userModel = $config->getUserModel();
        $emailColumn = $config->getIdentifierColumn('email');

        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'string', 'email', 'max:255', "unique:{$userModel},{$emailColumn}"],
            'password'              => ['required', 'string', 'confirmed', PasswordRule::fromConfig()],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function toDto(): RegisterData
    {
        return new RegisterData(
            name: (string) $this->input('name'),
            email: (string) $this->input('email'),
            password: (string) $this->input('password'),
            extra: $this->except(['password', 'password_confirmation'])
        );
    }
}
