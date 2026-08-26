<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Models;

use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Stores user TOTP secret and encrypted recovery codes for Two-Factor Authentication.
 *
 * @property int $id
 * @property int|string $user_id
 * @property string $secret
 * @property array<string>|null $recovery_codes
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TwoFactorAuthentication extends Model
{
    protected $fillable = [
        'user_id',
        'secret',
        'recovery_codes',
        'confirmed_at',
    ];

    public function getTable(): string
    {
        return AuthenticationConfig::tableName('two_factor', 'authentication_two_factors');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret'         => 'encrypted',
            'recovery_codes' => 'encrypted:array',
            'confirmed_at'   => 'datetime',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
