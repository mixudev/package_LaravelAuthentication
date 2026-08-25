<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates password complexity based on configured policies in config/authentication.php.
 *
 * Every rule can be toggled individually via environment variables:
 *   AUTH_PASSWORD_MIN_LENGTH        — Minimum character count
 *   AUTH_PASSWORD_REQUIRE_UPPERCASE — Must contain uppercase letter
 *   AUTH_PASSWORD_REQUIRE_LOWERCASE — Must contain lowercase letter
 *   AUTH_PASSWORD_REQUIRE_NUMBERS   — Must contain a digit
 *   AUTH_PASSWORD_REQUIRE_SYMBOLS   — Must contain a special character
 *   AUTH_PASSWORD_SYMBOLS_CHARSET   — Custom set of allowed special characters
 */
class PasswordRule implements ValidationRule
{
    public function __construct(
        private readonly int    $minLength       = 8,
        private readonly bool   $requireUppercase = true,
        private readonly bool   $requireLowercase = true,
        private readonly bool   $requireNumbers   = true,
        private readonly bool   $requireSymbols   = true,
        private readonly string $symbolsCharset   = '@$!%*#?&_-+=[]{}|;:,.<>'
    ) {}

    /**
     * Instantiate from published config/authentication.php values.
     */
    public static function fromConfig(): self
    {
        return new self(
            minLength:        (int)    config('authentication.password.validation_rules.min_length',        8),
            requireUppercase: (bool)   config('authentication.password.validation_rules.require_uppercase',  true),
            requireLowercase: (bool)   config('authentication.password.validation_rules.require_lowercase',  true),
            requireNumbers:   (bool)   config('authentication.password.validation_rules.require_numbers',    true),
            requireSymbols:   (bool)   config('authentication.password.validation_rules.require_symbols',    true),
            symbolsCharset:   (string) config('authentication.password.validation_rules.symbols_charset',    '@$!%*#?&_-+=[]{}|;:,.<>'),
        );
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');
            return;
        }

        if (mb_strlen($value) < $this->minLength) {
            $fail("The :attribute must be at least {$this->minLength} characters long.");
            return;
        }

        if ($this->requireUppercase && ! preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
            return;
        }

        if ($this->requireLowercase && ! preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');
            return;
        }

        if ($this->requireNumbers && ! preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');
            return;
        }

        if ($this->requireSymbols) {
            $escapedCharset = preg_quote($this->symbolsCharset, '/');
            if (! preg_match("/[{$escapedCharset}]/", $value)) {
                $charset = $this->symbolsCharset ?: '@$!%*#?&';
                $fail("The :attribute must contain at least one special character ({$charset}).");
            }
        }
    }
}
