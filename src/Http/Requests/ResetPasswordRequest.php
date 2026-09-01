<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Vendor\LaravelAuthentication\Rules\PasswordRule;

class ResetPasswordRequest extends FormRequest
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
            'token'    => ['required', 'string'],
            'email'    => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::fromConfig()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $newPasswordLabel = strtolower(\Vendor\LaravelAuthentication\Support\SecurityHelper::trans('authentication::messages.new_password_label'));

        return [
            'token.required'     => 'Token reset password tidak valid atau sudah kedaluwarsa.',
            'email.required'     => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.required', ['attribute' => 'email']),
            'email.email'        => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.email', ['attribute' => 'email']),
            'password.required'  => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.required', ['attribute' => $newPasswordLabel]),
            'password.confirmed' => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.confirmed', ['attribute' => $newPasswordLabel]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'token'    => 'token',
            'email'    => 'email',
            'password' => strtolower(\Vendor\LaravelAuthentication\Support\SecurityHelper::trans('authentication::messages.new_password_label')),
        ];
    }
}
