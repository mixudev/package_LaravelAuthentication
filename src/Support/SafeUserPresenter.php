<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * SafeUserPresenter
 *
 * Membangun representasi user yang AMAN untuk response JSON API.
 *
 * Mengapa penting (SEC-03 hardening):
 * - Response JSON yang mengembalikan Eloquent model mentah akan menserialize
 *   SELURUH kolom termasuk password hash, remember_token, dan kolom internal lain.
 * - Presenter ini hanya memilih whitelist atribut: id, name, email, username.
 *
 * Gunakan di semua controller API yang mengembalikan user ke client.
 */
final class SafeUserPresenter
{
    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function present(?Authenticatable $user, array $extra = []): array
    {
        if ($user === null) {
            return $extra;
        }

        $safe = [
            'id' => $user->getAuthIdentifier(),
        ];

        foreach (['name', 'email', 'username'] as $field) {
            $value = $user->{$field} ?? null;

            if ($value !== null && (is_string($value) || is_numeric($value))) {
                $safe[$field] = $value;
            }
        }

        return array_merge($safe, $extra);
    }
}