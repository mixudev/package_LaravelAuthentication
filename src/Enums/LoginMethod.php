<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Enums;

/**
 * Supported default login authentication mechanisms.
 */
enum LoginMethod: string
{
    case USERNAME_OR_EMAIL = 'username_or_email';
    case EMAIL_PASSWORD    = 'email_password';
    case USERNAME_PASSWORD = 'username_password';
    case CUSTOM_IDENTIFIER = 'custom_identifier';
    case API_TOKEN         = 'api_token';
}
