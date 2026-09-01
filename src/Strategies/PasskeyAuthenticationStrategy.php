<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Strategies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Contracts\AuthenticationStrategyInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Models\PasskeyCredential;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Authentication strategy for WebAuthn / Passkey logins.
 */
class PasskeyAuthenticationStrategy implements AuthenticationStrategyInterface
{
    public function __construct(
        protected readonly AuthenticationConfig $config
    ) {}

    public function name(): string
    {
        return 'passkey';
    }

    public function supports(LoginData $data): bool
    {
        return $data->strategy === 'passkey' || isset($data->extra['credential_id']);
    }

    public function resolveUser(LoginData $data, AuthenticationContext $context): ?Authenticatable
    {
        $credentialId = (string) ($data->extra['credential_id'] ?? $data->identifier);

        /** @var PasskeyCredential|null $credential */
        $credential = PasskeyCredential::query()
            ->where('credential_id', $credentialId)
            ->first();

        if ($credential === null) {
            return null;
        }

        $userModel = $this->config->getUserModel();
        /** @var Model $instance */
        $instance = new $userModel();

        /** @var Authenticatable|null $user */
        $user = $instance->newQuery()->find($credential->user_id);

        return $user;
    }

    public function validateCredentials(Authenticatable $user, LoginData $data): bool
    {
        // Credential validation is handled cryptographically in PasskeyService assertion ceremony
        return true;
    }
}
