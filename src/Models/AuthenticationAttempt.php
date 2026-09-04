<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Models;

use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Stores records of authentication attempts for forensics and rate-limit audits.
 *
 * @property int $id
 * @property string $identifier
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string $status
 * @property string|null $failure_reason
 * @property string|null $strategy
 * @property string $channel
 * @property \Illuminate\Support\Carbon $attempted_at
 */
class AuthenticationAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'identifier',
        'ip_address',
        'user_agent',
        'status',
        'failure_reason',
        'strategy',
        'channel',
        'attempted_at',
    ];

    public function getTable(): string
    {
        return AuthenticationConfig::tableName('attempts', 'authentication_attempts');
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attempted_at' => 'datetime',
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
