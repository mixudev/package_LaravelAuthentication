<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Models;

use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Stores recognized devices/fingerprints for user accounts.
 *
 * @property int $id
 * @property int|string $user_id
 * @property string $device_fingerprint
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string|null $device_name
 * @property string|null $platform
 * @property string|null $browser
 * @property string|null $location
 * @property bool $is_trusted
 * @property string|null $trust_token_hash
 * @property \Illuminate\Support\Carbon|null $trusted_until
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AuthenticationDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_fingerprint',
        'ip_address',
        'user_agent',
        'device_name',
        'platform',
        'browser',
        'location',
        'is_trusted',
        'trust_token_hash',
        'trusted_until',
        'last_seen_at',
    ];

    public function getTable(): string
    {
        return AuthenticationConfig::tableName('devices', 'authentication_devices');
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_trusted'    => 'boolean',
        'trusted_until' => 'datetime',
        'last_seen_at'  => 'datetime',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return $this->casts;
    }

    public function isCurrentlyTrusted(): bool
    {
        if (!$this->is_trusted) {
            return false;
        }

        if ($this->trusted_until === null) {
            return true;
        }

        return $this->trusted_until->isFuture();
    }
}
