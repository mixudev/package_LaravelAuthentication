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
            $fail('The identifier must be a valid string.');
            return;
        }

        $length = mb_strlen(trim($value));

        if ($length < 3 || $length > 255) {
            $fail('The identifier must be between 3 and 255 characters.');
            return;
        }

        // Prohibit control characters or null bytes
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $fail('The identifier contains invalid characters.');
        }
    }
}
