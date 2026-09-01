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
        $identifierLabel = strtolower(\Vendor\LaravelAuthentication\Support\SecurityHelper::trans('authentication::messages.identifier_label'));

        return [
            'identifier.required' => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.required', ['attribute' => $identifierLabel]),
            'identifier.string'   => \Vendor\LaravelAuthentication\Support\SecurityHelper::trans('validation.string', ['attribute' => $identifierLabel]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'identifier' => strtolower(\Vendor\LaravelAuthentication\Support\SecurityHelper::trans('authentication::messages.identifier_label')),
        ];
    }
}
