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
        $newPasswordLabel = strtolower((string) __('authentication::messages.new_password_label'));

        return [
            'token.required'     => 'Token reset password tidak valid atau sudah kedaluwarsa.',
            'email.required'     => __('validation.required', ['attribute' => 'email']),
            'email.email'        => __('validation.email', ['attribute' => 'email']),
            'password.required'  => __('validation.required', ['attribute' => $newPasswordLabel]),
            'password.confirmed' => __('validation.confirmed', ['attribute' => $newPasswordLabel]),
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
            'password' => strtolower((string) __('authentication::messages.new_password_label')),
        ];
    }
}
