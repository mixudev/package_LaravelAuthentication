<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\DTO\Passkey;

/**
 * Immutable DTO representing incoming WebAuthn assertion response from the client authenticator.
 */
final class PasskeyAssertion
{
    public function __construct(
        public readonly string $id,
        public readonly string $rawId,
        public readonly string $clientDataJSON,
        public readonly string $authenticatorData,
        public readonly string $signature,
        public readonly ?string $userHandle = null
    ) {}

    /**
     * Named constructor from client JSON payload.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $response = (array) ($payload['response'] ?? []);

        return new self(
            id: (string) ($payload['id'] ?? ''),
            rawId: (string) ($payload['rawId'] ?? $payload['id'] ?? ''),
            clientDataJSON: (string) ($response['clientDataJSON'] ?? ''),
            authenticatorData: (string) ($response['authenticatorData'] ?? ''),
            signature: (string) ($response['signature'] ?? ''),
            userHandle: isset($response['userHandle']) ? (string) $response['userHandle'] : null
        );
    }
}
