<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Vendor\LaravelAuthentication\Contracts\PasswordHistoryRepositoryInterface;
use Vendor\LaravelAuthentication\Contracts\RegistrationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\RegisterData;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Events\UserRegistered;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Service managing user registration, data persistence, hashing, and event dispatching.
 */
class RegistrationService implements RegistrationServiceInterface
{
    public function __construct(
        private readonly Hasher $hasher,
        private readonly Dispatcher $events,
        private readonly AuthenticationConfig $config,
        private readonly AuthenticationAuditService $auditService,
        private readonly PasswordHistoryRepositoryInterface $passwordHistoryRepo
    ) {}

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->isRegistrationEnabled();
    }

    public function register(RegisterData $data, AuthenticationContext $context): Authenticatable
    {
        if (!$this->isEnabled()) {
            throw new AuthenticationException('Registration is currently disabled.');
        }

        $userModelClass = $this->config->getUserModel();

        /** @var Model&Authenticatable $user */
        $user = new $userModelClass();

        $emailColumn = $this->config->getIdentifierColumn('email');
        $passwordColumn = $this->config->getIdentifierColumn('password');

        $user->forceFill([
            'name'           => $data->name,
            $emailColumn     => $data->email,
            $passwordColumn  => $this->hasher->make($data->password),
        ]);

        $user->save();

        if ($this->config->isPasswordHistoryEnabled()) {
            $this->passwordHistoryRepo->recordPassword($user, $user->getAuthPassword());
        }

        $this->events->dispatch(new UserRegistered($user, $context));

        $this->auditService->logEvent(
            SecurityEventType::LOGIN_SUCCESS,
            $data->email,
            $context,
            null,
            ['action' => 'user_registered', 'user_id' => $user->getAuthIdentifier()]
        );

        return $user;
    }
}
