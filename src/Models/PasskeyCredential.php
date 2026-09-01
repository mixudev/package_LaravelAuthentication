<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Models;

use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Stores FIDO2 / WebAuthn passkey public key credentials for passwordless authentication.
 *
 * @property int $id
 * @property int|string $user_id
 * @property string $name
 * @property string $credential_id
 * @property string $public_key
 * @property string $attestation_type
 * @property string|null $aaguid
 * @property int $sign_count
 * @property array<string>|null $transports
 * @property \Illuminate\Support\Carbon|\Carbon\CarbonInterface|null $last_used_at
 * @property \Illuminate\Support\Carbon|\Carbon\CarbonInterface|null $created_at
 * @property \Illuminate\Support\Carbon|\Carbon\CarbonInterface|null $updated_at
 */
class PasskeyCredential extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'credential_id',
        'public_key',
        'attestation_type',
        'aaguid',
        'sign_count',
        'transports',
        'last_used_at',
    ];

    public function getTable(): string
    {
        return AuthenticationConfig::tableName('passkeys', 'authentication_passkeys');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sign_count'   => 'integer',
            'transports'   => 'array',
            'last_used_at' => 'datetime',
        ];
    }
}
