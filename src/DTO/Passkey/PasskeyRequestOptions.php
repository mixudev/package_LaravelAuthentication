<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\DTO\Passkey;

/**
 * Immutable DTO representing WebAuthn PublicKeyCredentialRequestOptions for assertion ceremony.
 */
final class PasskeyRequestOptions
{
    /**
     * @param array<int, array<string, mixed>> $allowCredentials
     */
    public function __construct(
        public readonly string $challenge,
        public readonly int $timeout,
        public readonly string $rpId,
        public readonly string $userVerification,
        public readonly array $allowCredentials = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'challenge'        => $this->challenge,
            'timeout'          => $this->timeout,
            'rpId'             => $this->rpId,
            'userVerification' => $this->userVerification,
        ];

        if (!empty($this->allowCredentials)) {
            $data['allowCredentials'] = $this->allowCredentials;
        }

        return $data;
    }
}
