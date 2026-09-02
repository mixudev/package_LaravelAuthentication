<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that an identifier string is within length and character constraints.
 */
class LoginIdentifierRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail((string) trans('authentication::messages.identifier_must_be_string', ['attribute' => $attribute]));
            return;
        }

        $length = mb_strlen(trim($value));

        if ($length < 3 || $length > 255) {
            $fail((string) trans('authentication::messages.identifier_length', ['attribute' => $attribute]));
            return;
        }

        // Prohibit control characters or null bytes
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $fail((string) trans('authentication::messages.identifier_invalid_chars', ['attribute' => $attribute]));
        }
    }
}
