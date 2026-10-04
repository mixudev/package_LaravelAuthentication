<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use Vendor\LaravelAuthentication\Exceptions\AuthenticationConfigurationException;

/**
 * Resolves configured package views without allowing a stale published config
 * to turn a missing GET view into a misleading JSON "authenticate via POST"
 * response.
 */
final class AuthenticationView
{
    public static function resolve(string $configKey, string $canonical): string
    {
        $configured = (string) config("authentication.views.{$configKey}", '');

        if ($configured !== '' && view()->exists($configured)) {
            return $configured;
        }

        if (view()->exists($canonical)) {
            return $canonical;
        }

        throw new AuthenticationConfigurationException(
            "Authentication view [{$canonical}] is unavailable. Run php artisan vendor:publish --tag=authentication-views --force or fix authentication.views.{$configKey}."
        );
    }
}
