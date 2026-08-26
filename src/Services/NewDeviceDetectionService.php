<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Mail;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Events\NewDeviceLoginDetected;
use Vendor\LaravelAuthentication\Mail\NewDeviceLoginMail;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class NewDeviceDetectionService
{
    public function __construct(
        private readonly DeviceDetector $detector,
        private readonly AuthenticationConfig $config
    ) {}

    /**
     * Inspect login context, register device fingerprint, and dispatch alerts if device is newly seen.
     */
    public function handleLogin(Authenticatable $user, AuthenticationContext $context): AuthenticationDevice
    {
        $userId = $user->getAuthIdentifier();
        $detection = $this->detector->detect($context->userAgent, $context->ipAddress, $userId);

        /** @var AuthenticationDevice|null $device */
        $device = AuthenticationDevice::where('user_id', $userId)
            ->where('device_fingerprint', $detection['fingerprint'])
            ->first();

        $isNew = $device === null;

        if ($isNew) {
            $device = AuthenticationDevice::create([
                'user_id'            => $userId,
                'device_fingerprint' => $detection['fingerprint'],
                'ip_address'         => $context->ipAddress,
                'user_agent'         => $context->userAgent,
                'device_name'        => $detection['device_name'],
                'platform'           => $detection['platform'],
                'browser'            => $detection['browser'],
                'location'           => $detection['location'],
                'is_trusted'         => false,
                'last_seen_at'       => now(),
            ]);

            // Dispatch event
            event(new NewDeviceLoginDetected($user, $device, $context));

            // Send email alert if enabled
            if ($this->config->isNewDeviceNotificationEnabled() && !empty($user->email)) {
                $mailable = new NewDeviceLoginMail($user, $device);

                if ($this->config->isMailQueueEnabled()) {
                    Mail::to($user->email)->queue($mailable);
                } else {
                    Mail::to($user->email)->send($mailable);
                }
            }
        } else {
            $device->update([
                'ip_address'   => $context->ipAddress,
                'user_agent'   => $context->userAgent,
                'location'     => $detection['location'] ?? $device->location,
                'last_seen_at' => now(),
            ]);
        }

        return $device;
    }
}
