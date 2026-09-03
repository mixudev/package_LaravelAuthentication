<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Session;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class DeviceTrustService
{
    public function __construct(
        private readonly DeviceDetector $detector,
        private readonly AuthenticationConfig $config
    ) {}

    public function isTrusted(Authenticatable $user, Request $request): bool
    {
        if (!$this->config->isDeviceTrustEnabled()) {
            return false;
        }

        $cookieName = $this->config->getDeviceTrustCookieName();
        $token = (string) $request->cookie($cookieName);

        if (empty($token)) {
            return false;
        }

        $userId = $user->getAuthIdentifier();
        $detection = $this->detector->detect($request->userAgent(), (string) $request->ip(), $userId);

        /** @var AuthenticationDevice|null $device */
        $device = AuthenticationDevice::where('user_id', $userId)
            ->where('device_fingerprint', $detection['fingerprint'])
            ->first();

        if (!$device || !$device->isCurrentlyTrusted()) {
            return false;
        }

        // SEC-04 FIX: Prefer the server-side random trust token (per-device, revocable).
        $tokenHash = hash('sha256', $token);
        if (!empty($device->trust_token_hash)) {
            return hash_equals($device->trust_token_hash, $tokenHash);
        }

        // Legacy fallback (cookie issued before migration): valid but non-revocable HMAC.
        $expectedHash = hash_hmac('sha256', "{$userId}|{$detection['fingerprint']}", config('app.key'));

        return hash_equals($expectedHash, $token);
    }

    public function createTrustCookie(Authenticatable $user, Request $request): SymfonyCookie
    {
        $userId = $user->getAuthIdentifier();
        $detection = $this->detector->detect($request->userAgent(), (string) $request->ip(), $userId);
        $durationDays = $this->config->getDeviceTrustDurationDays();
        $expiresAt = now()->addDays($durationDays);

        /** @var AuthenticationDevice $device */
        $device = AuthenticationDevice::firstOrCreate(
            ['user_id' => $userId, 'device_fingerprint' => $detection['fingerprint']],
            [
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'device_name'  => $detection['device_name'],
                'platform'     => $detection['platform'],
                'browser'      => $detection['browser'],
                'location'     => $detection['location'],
            ]
        );

        // SEC-04 FIX: Issue a fresh random token each time a trust cookie is (re)created.
        // Store only its SHA-256 hash server-side so a stolen cookie can be revoked by clearing it.
        $token = Str::random(64);
        $device->update([
            'is_trusted'       => true,
            'trusted_until'    => $expiresAt,
            'trust_token_hash' => hash('sha256', $token),
            'last_seen_at'     => now(),
        ]);

        $cookieName = $this->config->getDeviceTrustCookieName();

        return Cookie::make(
            $cookieName,
            $token,
            $durationDays * 1440,
            '/',
            null,
            $request->isSecure(),
            true, // HttpOnly
            false,
            'strict'
        );
    }

    /**
     * SEC-04 FIX: Server-side revocation of a user's trust tokens (e.g. on logout or
     * "revoke all devices"). Leaves the device records but clears the trust markers so
     * any previously issued trust cookies immediately become invalid.
     */
    public function revokeUserTrust(Authenticatable $user): void
    {
        $userId = $user->getAuthIdentifier();

        AuthenticationDevice::where('user_id', $userId)
            ->where('is_trusted', true)
            ->update([
                'is_trusted'       => false,
                'trusted_until'    => null,
                'trust_token_hash' => null,
            ]);
    }

    public function forgetTrustCookie(): SymfonyCookie
    {
        return Cookie::forget($this->config->getDeviceTrustCookieName());
    }
}
