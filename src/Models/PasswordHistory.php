<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores previous password hashes for users to prevent password reuse.
 *
 * @property int $id
 * @property int $user_id
 * @property string $password_hash
 * @property \Illuminate\Support\Carbon $created_at
 */
class PasswordHistory extends Model
{
    public $timestamps = false;

    protected $table = 'password_histories';

    protected $fillable = [
        'user_id',
        'password_hash',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
