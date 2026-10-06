<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use SensitiveParameter;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

interface OtpServiceInterface
{
    public function isEnabled(): bool;

    public function generate(string $identifier, AuthenticationContext $context): string;

    public function verify(string $identifier, #[SensitiveParameter] string $code, AuthenticationContext $context): ?Authenticatable;

    public function isThrottled(string $identifier, AuthenticationContext $context): bool;
}
