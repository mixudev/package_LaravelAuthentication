<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Models;

use Illuminate\Database\Eloquent\Model;

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

    protected $table = 'authentication_attempts';

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

    protected $casts = [
        'attempted_at' => 'datetime',
    ];
}
