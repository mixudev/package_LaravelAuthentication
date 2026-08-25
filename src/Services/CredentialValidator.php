<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Contracts\CredentialValidatorInterface;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Validates password correctness and automatically updates hashes requiring algorithm updates.
 */
class CredentialValidator implements CredentialValidatorInterface
{
    public function __construct(
        private readonly Hasher $hasher,
        private readonly AuthenticationConfig $config
    ) {}

    public function validatePassword(Authenticatable $user, #[\SensitiveParameter] string $plainPassword): bool
    {
        $passwordHash = $user->getAuthPassword();

        if (empty($passwordHash)) {
            return false;
        }

        $isValid = $this->hasher->check($plainPassword, $passwordHash);

        if ($isValid && $this->config->isPasswordRehashEnabled() && $this->needsRehash($user)) {
            $this->rehashPassword($user, $plainPassword);
        }

        return $isValid;
    }

    public function needsRehash(Authenticatable $user): bool
    {
        return $this->hasher->needsRehash($user->getAuthPassword());
    }

    public function rehashPassword(Authenticatable $user, #[\SensitiveParameter] string $plainPassword): void
    {
        if ($user instanceof Model) {
            $column = $this->config->getIdentifierColumn('password');
            $user->{$column} = $this->hasher->make($plainPassword);
            $user->save();
        }
    }
}
