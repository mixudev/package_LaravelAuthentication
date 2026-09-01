<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class SessionManagerService
{
    public function __construct(
        private readonly DeviceDetector $detector,
        private readonly AuthenticationConfig $config,
        private readonly Hasher $hasher
    ) {}

    /**
     * Get all active sessions for a user.
     *
     * @return array<int, array{id: string, ip_address: string, user_agent: string, platform: string, browser: string, device_name: string, location: ?string, last_activity: Carbon, is_current_device: bool}>
     */
    public function getActiveSessions(Authenticatable $user, ?string $currentSessionId = null): array
    {
        $sessionDriver = config('session.driver');
        $userId = $user->getAuthIdentifier();
        $sessions = [];

        if ($sessionDriver === 'database') {
            $tableName = config('session.table', 'sessions');

            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                $records = DB::table($tableName)
                    ->where('user_id', $userId)
                    ->orderBy('last_activity', 'desc')
                    ->get();

                foreach ($records as $record) {
                    $agent = $record->user_agent ?? 'Unknown';
                    $ip = $record->ip_address ?? '127.0.0.1';
                    $detection = $this->detector->detect($agent, $ip, $userId);

                    $sessions[] = [
                        'id'                => (string) $record->id,
                        'ip_address'        => $ip,
                        'user_agent'        => $agent,
                        'platform'          => $detection['platform'],
                        'browser'           => $detection['browser'],
                        'device_name'       => $detection['device_name'],
                        'location'          => $detection['location'],
                        'last_activity'     => Carbon::createFromTimestamp($record->last_activity),
                        'is_current_device' => $currentSessionId !== null && (string) $record->id === $currentSessionId,
                    ];
                }

                return $sessions;
            }
        }

        // Fallback: Query from AuthenticationDevice table
        $devices = \Vendor\LaravelAuthentication\Models\AuthenticationDevice::where('user_id', $userId)
            ->orderBy('last_seen_at', 'desc')
            ->get();

        foreach ($devices as $device) {
            $sessions[] = [
                'id'                => (string) $device->id,
                'ip_address'        => $device->ip_address,
                'user_agent'        => $device->user_agent ?? '',
                'platform'          => $device->platform ?? 'Unknown OS',
                'browser'           => $device->browser ?? 'Unknown Browser',
                'device_name'       => $device->device_name ?? 'Unknown Device',
                'location'          => $device->location,
                'last_activity'     => $device->last_seen_at,
                'is_current_device' => $device->ip_address === request()->ip(),
            ];
        }

        return $sessions;
    }

    /**
     * Revoke a specific session by its session ID.
     */
    public function revokeSession(Authenticatable $user, string $sessionId): bool
    {
        $sessionDriver = config('session.driver');
        $userId = $user->getAuthIdentifier();

        if ($sessionDriver === 'database') {
            $tableName = config('session.table', 'sessions');
            return DB::table($tableName)
                ->where('user_id', $userId)
                ->where('id', $sessionId)
                ->delete() > 0;
        }

        return (bool) \Vendor\LaravelAuthentication\Models\AuthenticationDevice::where('user_id', $userId)
            ->where('id', $sessionId)
            ->delete();
    }

    /**
     * Revoke all other active sessions for the user after validating current password.
     */
    public function revokeOtherSessions(Authenticatable $user, #[SensitiveParameter] string $password, ?string $currentSessionId = null): bool
    {
        $passwordColumn = $this->config->getIdentifierColumn('password');
        $userHash = (string) ($user->{$passwordColumn} ?? '');

        if (!$this->hasher->check($password, $userHash)) {
            throw new InvalidCredentialsException(__('authentication::messages.invalid_password'));
        }

        // Use Laravel's built-in logoutOtherDevices if available
        if (method_exists(Auth::guard($this->config->getGuard()), 'logoutOtherDevices')) {
            Auth::guard($this->config->getGuard())->logoutOtherDevices($password);
        }

        $sessionDriver = config('session.driver');
        $userId = $user->getAuthIdentifier();

        if ($sessionDriver === 'database' && $currentSessionId !== null) {
            $tableName = config('session.table', 'sessions');
            DB::table($tableName)
                ->where('user_id', $userId)
                ->where('id', '!=', $currentSessionId)
                ->delete();
        }

        return true;
    }

    /**
     * Get summary metrics for user's active devices and session health.
     *
     * @return array{total_sessions: int, current_device: ?array{platform: string, browser: string, ip_address: string, location: ?string}, other_sessions_count: int}
     */
    public function getSummary(Authenticatable $user, ?string $currentSessionId = null): array
    {
        $sessions = $this->getActiveSessions($user, $currentSessionId);
        $currentDevice = null;
        $otherCount = 0;

        foreach ($sessions as $session) {
            if ($session['is_current_device']) {
                $currentDevice = [
                    'platform'   => $session['platform'],
                    'browser'    => $session['browser'],
                    'ip_address' => $session['ip_address'],
                    'location'   => $session['location'],
                ];
            } else {
                $otherCount++;
            }
        }

        return [
            'total_sessions'       => count($sessions),
            'current_device'       => $currentDevice,
            'other_sessions_count' => $otherCount,
        ];
    }
}
