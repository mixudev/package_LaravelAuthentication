<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Enums;

/**
 * Security audit event taxonomy.
 */
enum SecurityEventType: string
{
    case LOGIN_SUCCESS               = 'LOGIN_SUCCESS';
    case LOGIN_FAILURE               = 'LOGIN_FAILURE';
    case LOGIN_THROTTLED             = 'LOGIN_THROTTLED';
    case LOGOUT                      = 'LOGOUT';
    case ACCOUNT_LOCKED              = 'ACCOUNT_LOCKED';
    case ACCOUNT_UNLOCKED            = 'ACCOUNT_UNLOCKED';
    case PASSWORD_CHANGED            = 'PASSWORD_CHANGED';
    case PASSWORD_RESET_REQUESTED    = 'PASSWORD_RESET_REQUESTED';
    case PASSWORD_RESET_COMPLETED    = 'PASSWORD_RESET_COMPLETED';
    case EMAIL_VERIFIED              = 'EMAIL_VERIFIED';
    case SESSION_REVOKED             = 'SESSION_REVOKED';
    case TOKEN_REVOKED               = 'TOKEN_REVOKED';
}
