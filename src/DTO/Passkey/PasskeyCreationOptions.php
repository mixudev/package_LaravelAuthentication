<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\DTO\Passkey;

/**
 * Immutable DTO representing WebAuthn PublicKeyCredentialCreationOptions.
 */
final class PasskeyCreationOptions
{
    /**
     * @param array<string, mixed> $rp
     * @param array<string, mixed> $user
     * @param array<int, array<string, mixed>> $pubKeyCredParams
     * @param array<string, mixed> $authenticatorSelection
     * @param array<int, array<string, mixed>> $excludeCredentials
     */
    public function __construct(
        public readonly string $challenge,
        public readonly array $rp,
        public readonly array $user,
        public readonly array $pubKeyCredParams,
        public readonly int $timeout,
        public readonly string $attestation,
        public readonly array $authenticatorSelection,
        public readonly array $excludeCredentials = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'challenge'              => $this->challenge,
            'rp'                     => $this->rp,
            'user'                   => $this->user,
            'pubKeyCredParams'       => $this->pubKeyCredParams,
            'timeout'                => $this->timeout,
            'attestation'            => $this->attestation,
            'authenticatorSelection' => $this->authenticatorSelection,
            'excludeCredentials'     => $this->excludeCredentials,
        ];
    }
}
