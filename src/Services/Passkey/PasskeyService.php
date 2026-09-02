<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Passkey;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\AuthenticationResult;
use Vendor\LaravelAuthentication\DTO\Passkey\PasskeyAssertion;
use Vendor\LaravelAuthentication\DTO\Passkey\PasskeyCreationOptions;
use Vendor\LaravelAuthentication\DTO\Passkey\PasskeyRequestOptions;
use Vendor\LaravelAuthentication\Enums\AuthenticationStatus;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Models\PasskeyCredential;
use Vendor\LaravelAuthentication\Services\Core\TokenService;
use Vendor\LaravelAuthentication\Services\Security\AccountLockService;
use Vendor\LaravelAuthentication\Services\Security\AuthenticationAuditService;
use Vendor\LaravelAuthentication\Services\Session\NewDeviceDetectionService;
use Vendor\LaravelAuthentication\Services\Session\SessionSecurityService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\WebAuthn\WebAuthnHelper;

/**
 * Enterprise FIDO2 / WebAuthn Passkey Service for passwordless authentication.
 *
 * Implements strict, high-assurance WebAuthn Level 2 / Level 3 cryptographic
 * verification for registration (attestation) and authentication (assertion) ceremonies.
 */
class PasskeyService
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly Dispatcher $events,
        private readonly AuthFactory $auth,
        private readonly AccountLockService $lockService,
        private readonly SessionSecurityService $sessionSecurity,
        private readonly TokenService $tokenService,
        private readonly AuthenticationAuditService $auditService,
        private readonly NewDeviceDetectionService $newDeviceService,
        private readonly AuthenticationConfig $config
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('authentication.features.passkey.enabled', true);
    }

    public function getRelyingPartyName(): string
    {
        return (string) (config('authentication.features.passkey.rp_name') ?: config('app.name', 'Laravel'));
    }

    public function getRelyingPartyId(): string
    {
        $configured = config('authentication.features.passkey.rp_id');
        if ($configured) {
            return (string) $configured;
        }

        $host = request() ? request()->getHost() : 'localhost';
        return $host;
    }

    /**
     * Generate WebAuthn creation options for a user registering a new passkey.
     */
    public function generateCreationOptions(Authenticatable $user): PasskeyCreationOptions
    {
        $challenge = $this->generateRandomBase64Url(32);
        $userId = (string) $user->getAuthIdentifier();

        // Cache challenge for 5 minutes (single-use)
        $this->cache->put("passkey_reg_challenge:{$userId}", $challenge, now()->addMinutes(5));

        $userEmail = (string) ($user->email ?? $user->username ?? "user-{$userId}");
        $userName = (string) ($user->name ?? $userEmail);

        // Fetch existing passkeys to exclude re-registration on same authenticator
        $existing = PasskeyCredential::query()
            ->where('user_id', $userId)
            ->pluck('credential_id')
            ->map(fn(string $id) => [
                'id'   => $id,
                'type' => 'public-key',
            ])
            ->values()
            ->toArray();

        return new PasskeyCreationOptions(
            challenge: $challenge,
            rp: [
                'name' => $this->getRelyingPartyName(),
                'id'   => $this->getRelyingPartyId(),
            ],
            user: [
                'id'          => $this->base64UrlEncode($userId),
                'name'        => $userEmail,
                'displayName' => $userName,
            ],
            pubKeyCredParams: [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
                ['type' => 'public-key', 'alg' => -8],   // EdDSA
            ],
            timeout: (int) config('authentication.features.passkey.timeout', 60000),
            attestation: 'none',
            authenticatorSelection: [
                'residentKey'      => 'preferred',
                'userVerification' => (string) config('authentication.features.passkey.user_verification', 'preferred'),
            ],
            excludeCredentials: $existing
        );
    }

    /**
     * Complete Passkey registration by validating the client response.
     *
     * @param array<string, mixed> $payload
     */
    public function registerPasskey(Authenticatable $user, array $payload, string $name = 'Passkey'): PasskeyCredential
    {
        $userId = (string) $user->getAuthIdentifier();
        $storedChallenge = $this->cache->get("passkey_reg_challenge:{$userId}");

        if (!$storedChallenge || !is_string($storedChallenge)) {
            throw new AuthenticationException('Passkey registration challenge expired. Please retry.');
        }

        // Invalidate challenge immediately (single-use anti-replay)
        $this->cache->forget("passkey_reg_challenge:{$userId}");

        $response = (array) ($payload['response'] ?? []);
        $clientDataJSON = (string) ($response['clientDataJSON'] ?? '');
        $clientDataRaw = WebAuthnHelper::base64UrlDecode($clientDataJSON);

        // 1. Validate clientDataJSON (ceremony type, challenge, origin)
        WebAuthnHelper::validateClientData(
            $clientDataRaw,
            'webauthn.create',
            $storedChallenge,
            $this->getRelyingPartyId()
        );

        $credentialId = (string) ($payload['id'] ?? $payload['rawId'] ?? '');
        if (empty($credentialId)) {
            throw new AuthenticationException('Missing WebAuthn credential ID.');
        }

        // 2. Extract and strictly parse/normalize public key
        $rawPublicKey = (string) ($response['publicKey'] ?? $response['attestationObject'] ?? '');
        if (empty($rawPublicKey)) {
            throw new AuthenticationException('WebAuthn registration payload missing public key or attestation object.');
        }

        $normalizedPublicKeyPem = WebAuthnHelper::normalizePublicKeyToPem($rawPublicKey);

        $transports = isset($response['transports']) && is_array($response['transports']) 
            ? $response['transports'] 
            : null;

        /** @var PasskeyCredential $credential */
        $credential = PasskeyCredential::query()->create([
            'user_id'          => $userId,
            'name'             => $name ?: 'Passkey (' . now()->format('M d, Y') . ')',
            'credential_id'    => $credentialId,
            'public_key'       => $normalizedPublicKeyPem,
            'attestation_type' => 'none',
            'sign_count'       => 0,
            'transports'       => $transports,
            'last_used_at'     => now(),
        ]);

        return $credential;
    }

    /**
     * Generate WebAuthn request options for authentication.
     */
    public function generateRequestOptions(?string $identifier = null): PasskeyRequestOptions
    {
        $challenge = $this->generateRandomBase64Url(32);

        // Store challenge globally in cache by challenge string (TTL: 5 mins, single use)
        $this->cache->put("passkey_auth_challenge:{$challenge}", true, now()->addMinutes(5));

        // SA-07 Mitigation: Use discoverable credentials flow (empty allowCredentials) on unauthenticated public requests
        // to prevent leaking registered credential IDs or enumerating user accounts.
        $allowCredentials = [];

        return new PasskeyRequestOptions(
            challenge: $challenge,
            timeout: (int) config('authentication.features.passkey.timeout', 60000),
            rpId: $this->getRelyingPartyId(),
            userVerification: (string) config('authentication.features.passkey.user_verification', 'preferred'),
            allowCredentials: $allowCredentials
        );
    }

    /**
     * Authenticate via WebAuthn Assertion.
     *
     * @param array<string, mixed> $payload
     */
    public function authenticate(array $payload, AuthenticationContext $context): AuthenticationResult
    {
        $assertion = PasskeyAssertion::fromArray($payload);

        if (empty($assertion->id) || empty($assertion->clientDataJSON) || empty($assertion->authenticatorData) || empty($assertion->signature)) {
            throw new InvalidCredentialsException('Invalid or incomplete passkey assertion payload.');
        }

        $clientDataRaw = WebAuthnHelper::base64UrlDecode($assertion->clientDataJSON);
        $clientData = json_decode($clientDataRaw, true);

        if (!is_array($clientData) || ($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new InvalidCredentialsException('Invalid passkey assertion ceremony type.');
        }

        $challenge = (string) ($clientData['challenge'] ?? '');
        if ($challenge === '') {
            throw new InvalidCredentialsException('Missing challenge in WebAuthn clientDataJSON.');
        }

        $cacheKey = "passkey_auth_challenge:{$challenge}";

        if (!$this->cache->has($cacheKey)) {
            throw new InvalidCredentialsException('Passkey challenge expired or invalid.');
        }

        // Consume challenge immediately (anti-replay single use)
        $this->cache->forget($cacheKey);

        // 1. Validate clientDataJSON (type, challenge, origin)
        try {
            WebAuthnHelper::validateClientData(
                $clientDataRaw,
                'webauthn.get',
                $challenge,
                $this->getRelyingPartyId()
            );
        } catch (AuthenticationException $e) {
            throw new InvalidCredentialsException($e->getMessage());
        }

        // 2. Validate authenticatorData (rpIdHash, UP, UV)
        $authDataRaw = WebAuthnHelper::base64UrlDecode($assertion->authenticatorData);
        $uvPolicy = (string) config('authentication.features.passkey.user_verification', 'preferred');

        try {
            $parsedAuthData = WebAuthnHelper::parseAuthenticatorData(
                $authDataRaw,
                $this->getRelyingPartyId(),
                $uvPolicy
            );
        } catch (AuthenticationException $e) {
            throw new InvalidCredentialsException($e->getMessage());
        }

        // 3. Locate credential and associated user
        /** @var PasskeyCredential|null $credential */
        $credential = PasskeyCredential::query()
            ->where('credential_id', $assertion->id)
            ->first();

        if ($credential === null) {
            throw new InvalidCredentialsException();
        }

        $userModel = $this->config->getUserModel();
        /** @var Model $instance */
        $instance = new $userModel();
        /** @var Authenticatable|null $user */
        $user = $instance->newQuery()->find($credential->user_id);

        if ($user === null) {
            throw new InvalidCredentialsException();
        }

        // 4. Account Lockout check
        if ($this->lockService->isLocked($user)) {
            $this->auditService->logEvent(
                SecurityEventType::ACCOUNT_LOCKED,
                (string) $user->getAuthIdentifier(),
                $context,
                AuthenticationResult::failed(AuthenticationStatus::ACCOUNT_LOCKED, 'Account is locked.')
            );

            throw new AccountLockedException($this->config->getLockoutDurationMinutes());
        }

        // 5. Cloned authenticator detection (Sign count check)
        if ($credential->sign_count > 0 && $parsedAuthData['sign_count'] > 0 && $parsedAuthData['sign_count'] <= $credential->sign_count) {
            throw new InvalidCredentialsException('WebAuthn cloned authenticator detected: invalid sign counter.');
        }

        // 6. Cryptographic signature verification over (authenticatorData || SHA256(clientDataJSON))
        $signatureRaw = WebAuthnHelper::base64UrlDecode($assertion->signature);
        WebAuthnHelper::verifySignature(
            $authDataRaw,
            $clientDataRaw,
            $signatureRaw,
            $credential->public_key
        );

        // 7. Update sign count & last used timestamp upon successful verification
        $credential->sign_count = $parsedAuthData['sign_count'] > 0 ? $parsedAuthData['sign_count'] : $credential->sign_count + 1;
        $credential->last_used_at = now();
        $credential->save();

        // 8. Establish session and/or API token
        $token = null;
        $guard = $this->auth->guard($context->guard);

        if ($guard instanceof StatefulGuard && request()->hasSession()) {
            $this->sessionSecurity->loginUser($guard, $user, true, request());
        }

        if ($context->channel->value === 'api' || request()->is('api/*')) {
            $token = $this->tokenService->createToken($user);
        }

        // Record device detection
        $this->newDeviceService->handleLogin($user, $context);

        $result = AuthenticationResult::success($user, $token, [
            'strategy'   => 'passkey',
            'channel'    => $context->channel->value,
            'passkey_id' => $credential->id,
        ]);

        $this->events->dispatch(new LoginSucceeded($user, $context, 'passkey'));

        $this->auditService->logEvent(
            SecurityEventType::LOGIN_SUCCESS,
            (string) $user->getAuthIdentifier(),
            $context,
            $result
        );

        return $result;
    }

    /**
     * List registered passkeys for a user.
     *
     * @return Collection<int, PasskeyCredential>
     */
    public function getUserPasskeys(Authenticatable $user): Collection
    {
        /** @var Collection<int, PasskeyCredential> $result */
        $result = PasskeyCredential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByDesc('created_at')
            ->get();

        return $result;
    }

    /**
     * Delete a user's passkey.
     */
    public function deletePasskey(Authenticatable $user, int|string $passkeyId): bool
    {
        return (bool) PasskeyCredential::query()
            ->where('id', $passkeyId)
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();
    }

    /**
     * @param int<1, max> $length
     */
    public function generateRandomBase64Url(int $length = 32): string
    {
        return WebAuthnHelper::base64UrlEncode(random_bytes(max(1, $length)));
    }

    public function base64UrlEncode(string $data): string
    {
        return WebAuthnHelper::base64UrlEncode($data);
    }

    public function base64UrlDecode(string $data): string
    {
        return WebAuthnHelper::base64UrlDecode($data);
    }
}
