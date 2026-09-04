<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Models;

use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Stores previous password hashes for users to prevent password reuse.
 *
 * @property int $id
 * @property int|string $user_id
 * @property string $password_hash
 * @property \Illuminate\Support\Carbon $created_at
 */
class PasswordHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'password_hash',
        'created_at',
    ];

    public function getTable(): string
    {
        return AuthenticationConfig::tableName('password_histories', 'authentication_password_histories');
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
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
}
