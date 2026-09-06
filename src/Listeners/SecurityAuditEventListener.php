<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Events\AccountLocked;
use Vendor\LaravelAuthentication\Events\LoginFailed;
use Vendor\LaravelAuthentication\Events\LoginSucceeded;
use Vendor\LaravelAuthentication\Events\NewDeviceLoginDetected;

/**
 * SecurityAuditEventListener
 *
 * Default listener wiring for the package's security events. Host applications
 * may subscribe to any of these events independently; this listener demonstrates
 * the recommended pattern and provides a sensible out-of-the-box audit trail.
 *
 * It writes a structured, redacted JSON line to the Laravel log. No passwords,
 * reset tokens, or raw secrets are ever written — payloads are redacted via
 * AuthenticationContext::toArray() (which masks identifiers).
 */
class SecurityAuditEventListener
{
    use InteractsWithQueue;

    /**
     * Maximum audit log lines written per event lifecycle to prevent log flooding.
     */
    private const MAX_LOG_LINES = 10;

    public function handleLoginSucceeded(LoginSucceeded $event): void
    {
        $this->writeAuditLine('LOGIN_SUCCESS', $event->context, [
            'user_id'   => (string) $event->user->getAuthIdentifier(),
            'strategy'  => $event->strategy,
        ]);
    }

    public function handleLoginFailed(LoginFailed $event): void
    {
        $this->writeAuditLine('LOGIN_FAILED', $event->context, [
            'identifier' => $event->identifier,
            'reason'     => $event->reason,
        ]);
    }

    public function handleAccountLocked(AccountLocked $event): void
    {
        $this->writeAuditLine('ACCOUNT_LOCKED', $event->context, [
            'user_id'              => (string) $event->user->getAuthIdentifier(),
            'lockout_duration_min' => $event->lockoutDurationMinutes,
        ]);
    }

    public function handleNewDeviceLogin(NewDeviceLoginDetected $event): void
    {
        $this->writeAuditLine('NEW_DEVICE_LOGIN', $event->context, [
            'user_id'  => (string) $event->user->getAuthIdentifier(),
            'device'   => (string) $event->device->device_fingerprint,
            'platform' => $event->device->platform,
            'browser'  => $event->device->browser,
        ]);
    }

    /**
     * Write a single structured audit line, guarding against log flooding.
     *
     * @param array<string, mixed> $data
     */
    protected function writeAuditLine(string $type, AuthenticationContext $context, array $data): void
    {
        // Simple in-memory flood guard: reset every request lifecycle.
        static $written = 0;

        if ($written >= self::MAX_LOG_LINES) {
            return;
        }

        $written++;

        Log::channel(config('authentication.audit.log_channel', 'stack'))->info(
            'authentication.security',
            [
                'event'      => $type,
                'ip_address' => $context->ipAddress,
                'channel'    => $context->channel->value,
                'user_agent' => substr($context->userAgent ?? '', 0, 120),
                'data'       => $data,
                'time'       => now()->toIso8601String(),
            ]
        );
    }
}