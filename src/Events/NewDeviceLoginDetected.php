<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;

/**
 * Event dispatched when a login occurs from an unrecognized device/IP fingerprint.
 */
class NewDeviceLoginDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly AuthenticationDevice $device,
        public readonly AuthenticationContext $context
    ) {}
}
