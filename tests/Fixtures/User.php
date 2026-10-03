<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Fixtures;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class User extends Model implements Authenticatable, CanResetPasswordContract, MustVerifyEmail
{
    use AuthenticatableTrait, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'username',
        'email',
        'employee_id',
        'password',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function markEmailAsUnverified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => null,
        ])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        // Mock notification send
    }

    public function getEmailForVerification(): string
    {
        return (string) $this->email;
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->email;
    }

    /**
     * Forward to the Notifiable trait so the reset notification is actually dispatched
     * and can be asserted with Notification::fake(). A no-op here would silently make
     * every forgot-password test vacuous.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \Illuminate\Auth\Notifications\ResetPassword($token));
    }
}
