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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $passwordLabel = strtolower((string) __('authentication::messages.password_label'));

        return [
            'name.required'                  => __('validation.required', ['attribute' => 'nama']),
            'name.string'                    => __('validation.string', ['attribute' => 'nama']),
            'name.max'                       => __('validation.max.string', ['attribute' => 'nama', 'max' => 255]),
            'email.required'                 => __('validation.required', ['attribute' => 'email']),
            'email.email'                    => __('validation.email', ['attribute' => 'email']),
            'email.unique'                   => (string) __('authentication::messages.email_taken'),
            'password.required'              => __('validation.required', ['attribute' => $passwordLabel]),
            'password.confirmed'             => __('validation.confirmed', ['attribute' => $passwordLabel]),
            'password_confirmation.required' => __('validation.required', ['attribute' => 'konfirmasi kata sandi']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'                  => 'nama',
            'email'                 => 'email',
            'password'              => strtolower((string) __('authentication::messages.password_label')),
            'password_confirmation' => 'konfirmasi kata sandi',
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
