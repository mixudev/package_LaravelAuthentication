<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Vendor\LaravelAuthentication\Rules\LoginIdentifierRule;

class SendOtpRequest extends FormRequest
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
            'identifier' => ['required', 'string', new LoginIdentifierRule()],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'identifier' => strtolower((string) __('authentication::messages.identifier_label')),
        ];
    }
}
