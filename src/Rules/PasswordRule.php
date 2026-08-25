<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates password complexity based on configured policies.
 */
class PasswordRule implements ValidationRule
{
    public function __construct(
        private readonly int $minLength = 8,
        private readonly bool $requireMixedCase = true,
        private readonly bool $requireNumbers = true,
        private readonly bool $requireSymbols = true
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            minLength: (int) config('authentication.password.validation_rules.min_length', 8),
            requireMixedCase: (bool) config('authentication.password.validation_rules.require_mixed_case', true),
            requireNumbers: (bool) config('authentication.password.validation_rules.require_numbers', true),
            requireSymbols: (bool) config('authentication.password.validation_rules.require_symbols', true)
        );
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The password must be a string.');
            return;
        }

        if (mb_strlen($value) < $this->minLength) {
            $fail("The password must be at least {$this->minLength} characters long.");
            return;
        }

        if ($this->requireMixedCase && (!preg_match('/[a-z]/', $value) || !preg_match('/[A-Z]/', $value))) {
            $fail('The password must contain both uppercase and lowercase letters.');
            return;
        }

        if ($this->requireNumbers && !preg_match('/[0-9]/', $value)) {
            $fail('The password must contain at least one number.');
            return;
        }

        if ($this->requireSymbols && !preg_match('/[\W_]/', $value)) {
            $fail('The password must contain at least one special character.');
        }
    }
}
