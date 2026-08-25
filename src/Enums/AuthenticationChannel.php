<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Enums;

/**
 * Access channel initiating the authentication request.
 */
enum AuthenticationChannel: string
{
    case WEB     = 'web';
    case API     = 'api';
    case CLI     = 'cli';
    case WEBHOOK = 'webhook';
}
