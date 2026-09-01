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
        $identifierLabel = strtolower(\Vendor\LaravelAuthentication\Support\SecurityHelper::trans('authentication::messages.identifier_label'));

        return [
            'identifier.required' => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.required', ['attribute' => $identifierLabel]),
            'identifier.string'   => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.string', ['attribute' => $identifierLabel]),
            'code.required'       => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.required', ['attribute' => 'kode OTP']),
            'code.min'            => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.min.string', ['attribute' => 'kode OTP', 'min' => 4]),
            'code.max'            => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.max.string', ['attribute' => 'kode OTP', 'max' => 16]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'identifier' => strtolower(\Vendor\LaravelAuthentication\Support\SecurityHelper::trans('authentication::messages.identifier_label')),
            'code'       => 'kode OTP',
        ];
    }
}
