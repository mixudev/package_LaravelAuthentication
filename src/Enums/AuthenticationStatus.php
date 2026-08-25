<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Enums;

/**
 * Result status codes of authentication workflows.
 */
enum AuthenticationStatus: string
{
    case SUCCESS              = 'success';
    case INVALID_CREDENTIALS  = 'invalid_credentials';
    case THROTTLED            = 'throttled';
    case ACCOUNT_LOCKED       = 'account_locked';
    case MFA_REQUIRED         = 'mfa_required';
    case PASSWORD_EXPIRED     = 'password_expired';
    case DISABLED             = 'disabled';
}
