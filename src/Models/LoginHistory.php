<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Models;

use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Stores successful login events with associated sessions/channels.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $login_method
 * @property string $channel
 * @property \Illuminate\Support\Carbon $login_at
 * @property \Illuminate\Support\Carbon|null $logout_at
 */
class LoginHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'login_method',
        'channel',
        'login_at',
        'logout_at',
    ];

    public function getTable(): string
    {
        return AuthenticationConfig::tableName('login_histories', 'authentication_login_histories');
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'login_at'  => 'datetime',
        'logout_at' => 'datetime',
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
