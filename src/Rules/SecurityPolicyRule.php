<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates request payload against broad injection or anomalous patterns.
 */
class SecurityPolicyRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && str_contains($value, "\0")) {
            $fail((string) trans('authentication::messages.security_null_byte', ['attribute' => $attribute]));
        }
    }
}
