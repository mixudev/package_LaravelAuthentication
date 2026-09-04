<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Models;

use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Persistent account lockout state (SEC-07).
 *
 * @property int $id
 * @property string $user_identifier
 * @property int $failed_attempts
 * @property \Illuminate\Support\Carbon|null $locked_until
 * @property \Illuminate\Support\Carbon|null $last_failure_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AccountLockout extends Model
{
    protected $fillable = [
        'user_identifier',
        'failed_attempts',
        'locked_until',
        'last_failure_at',
    ];

    public function getTable(): string
    {
        return AuthenticationConfig::tableName('lockouts', 'authentication_account_lockouts');
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'failed_attempts' => 'integer',
        'locked_until'    => 'datetime',
        'last_failure_at' => 'datetime',
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

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
