<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Exceptions;

/**
 * Thrown when an account has been temporarily or permanently locked due to security policy.
 */
class AccountLockedException extends AuthenticationException
{
    protected string $errorCode = 'ACCOUNT_LOCKED';

    /**
     * `(string) __()` is not a constant expression, so it cannot sit in a default parameter
     * value. The lockout sentence is resolved in the constructor body instead,
     * which also means it honours the locale that is active when the exception is
     * thrown (queue workers and API requests may differ from the web request).
     *
     * Pass an explicit $message to override it; an empty string falls back to the
     * localized copy.
     */
    public function __construct(
        public readonly int $lockoutMinutes = 15,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : (string) trans('authentication::messages.account_locked'), $code, $previous);
    }
}
