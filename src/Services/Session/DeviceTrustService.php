<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Session;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
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

        $device->update([
            'is_trusted'    => true,
            'trusted_until' => $expiresAt,
            'last_seen_at'  => now(),
        ]);

        $cookieName = $this->config->getDeviceTrustCookieName();
        $token = hash_hmac('sha256', "{$userId}|{$detection['fingerprint']}", config('app.key'));

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

    public function forgetTrustCookie(): SymfonyCookie
    {
        return Cookie::forget($this->config->getDeviceTrustCookieName());
    }
}
