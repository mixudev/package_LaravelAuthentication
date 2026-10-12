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
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
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

    public function updatePassword(
        Authenticatable $user,
        #[SensitiveParameter] string $newPlainPassword,
        ?AuthenticationContext $context = null
    ): void {
        if ($this->config->isPasswordHistoryEnabled()) {
            $rememberCount = $this->config->getPasswordHistoryCount();
            if ($this->historyRepo->isPreviouslyUsed($user, $newPlainPassword, $rememberCount)) {
                throw new AuthenticationException((string) __('authentication::messages.password_history', ['count' => $rememberCount]));
            }
        }

        // PR-04: password history is opt-in, so without it a reset token holder could
        // "reset" to the password they already know and nothing would change. Reuse of
        // the CURRENT password is rejected unconditionally.
        if ($user instanceof Model) {
            $passwordColumn = $this->config->getIdentifierColumn('password');
            $currentHash = (string) ($user->{$passwordColumn} ?? '');

            if ($currentHash !== '' && $this->hasher->check($newPlainPassword, $currentHash)) {
                throw new AuthenticationException((string) __('authentication::messages.password_must_differ'));
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

        $request = request();
        $context ??= ($request instanceof \Illuminate\Http\Request
            ? AuthenticationContext::fromRequest($request)
            : new AuthenticationContext('cli', 'cli', AuthenticationChannel::CLI, 'web'));

        $this->events->dispatch(new PasswordChanged($user, $context));

        // Audit trail (context opsional — aman dipanggil dari CLI/queue tanpa request).
        $this->auditService->logEvent(
            SecurityEventType::PASSWORD_CHANGED,
            (string) $user->getAuthIdentifier(),
            $context
        );
    }
}
