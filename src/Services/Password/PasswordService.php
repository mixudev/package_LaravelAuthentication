<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Password;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\Contracts\PasswordHistoryRepositoryInterface;
use Vendor\LaravelAuthentication\Events\PasswordChanged;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Handles password hashing, history checks, and secure updating.
 */
class PasswordService
{
    public function __construct(
        private readonly Hasher $hasher,
        private readonly PasswordHistoryRepositoryInterface $historyRepo,
        private readonly AuthenticationConfig $config,
        private readonly Dispatcher $events,
        private readonly AuditLoggerInterface $auditService
    ) {}

    public function hashPassword(#[SensitiveParameter] string $plainPassword): string
    {
        return $this->hasher->make($plainPassword);
    }

    public function updatePassword(Authenticatable $user, #[SensitiveParameter] string $newPlainPassword): void
    {
        if ($this->config->isPasswordHistoryEnabled()) {
            $rememberCount = $this->config->getPasswordHistoryCount();
            if ($this->historyRepo->isPreviouslyUsed($user, $newPlainPassword, $rememberCount)) {
                throw new AuthenticationException("You cannot reuse any of your last {$rememberCount} passwords.");
            }
        }

        $hash = $this->hashPassword($newPlainPassword);

        if ($user instanceof Model) {
            $column = $this->config->getIdentifierColumn('password');
            $user->{$column} = $hash;
            $user->save();
        }

        if ($this->config->isPasswordHistoryEnabled()) {
            $this->historyRepo->recordPassword($user, $hash);
        }

        $this->events->dispatch(new PasswordChanged($user));

        // Audit trail (context opsional — aman dipanggil dari CLI/queue tanpa request).
        $request = request();
        $this->auditService->logEvent(
            \Vendor\LaravelAuthentication\Enums\SecurityEventType::PASSWORD_CHANGED,
            (string) $user->getAuthIdentifier(),
            $request instanceof \Illuminate\Http\Request
                ? \Vendor\LaravelAuthentication\DTO\AuthenticationContext::fromRequest($request)
                : new \Vendor\LaravelAuthentication\DTO\AuthenticationContext('cli', 'cli', \Vendor\LaravelAuthentication\Enums\AuthenticationChannel::CLI, 'web')
        );
    }
}
