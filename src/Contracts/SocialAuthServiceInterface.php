<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

interface SocialAuthServiceInterface
{
    public function isEnabled(): bool;

    public function isProviderEnabled(string $provider): bool;

    public function getRedirectResponse(string $provider): RedirectResponse;

    public function handleCallback(string $provider, AuthenticationContext $context, bool $stateless = false): Authenticatable;
}
