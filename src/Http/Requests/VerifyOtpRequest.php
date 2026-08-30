<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Vendor\LaravelAuthentication\Rules\LoginIdentifierRule;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('remember')) {
            $this->merge([
                'remember' => $this->boolean('remember'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', new LoginIdentifierRule()],
            'code'       => ['required', 'string', 'min:4', 'max:16'],
            'remember'   => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $identifierLabel = strtolower((string) __('authentication::messages.identifier_label'));

        return [
            'identifier.required' => __('validation.required', ['attribute' => $identifierLabel]),
            'identifier.string'   => __('validation.string', ['attribute' => $identifierLabel]),
            'code.required'       => __('validation.required', ['attribute' => 'kode OTP']),
            'code.min'            => __('validation.min.string', ['attribute' => 'kode OTP', 'min' => 4]),
            'code.max'            => __('validation.max.string', ['attribute' => 'kode OTP', 'max' => 16]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'identifier' => strtolower((string) __('authentication::messages.identifier_label')),
            'code'       => 'kode OTP',
        ];
    }
}
