<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\Session;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class SessionManagerService
{
    public function __construct(
        private readonly DeviceDetector $detector,
        private readonly AuthenticationConfig $config,
        private readonly Hasher $hasher
    ) {}

    /**
     * Get active sessions for a user with pagination.
     *
     * PERF-03 FIX: Added pagination + hard cap to prevent OOM when user has 1000+ sessions
     * (malicious activity, leaked tokens, or session hijacking). Without limit, loading
     * all sessions into memory can exhaust PHP memory_limit.
     *
     * @param int $limit Maximum sessions to return (capped at 100)
     * @param int $offset Pagination offset
     * @return array<int, array{id: string, ip_address: string, user_agent: string, platform: string, browser: string, device_name: string, location: ?string, last_activity: Carbon, is_current_device: bool}>
     */
    public function getActiveSessions(Authenticatable $user, ?string $currentSessionId = null, int $limit = 50, int $offset = 0): array
    {
        $sessionDriver = config('session.driver');
        $userId = $user->getAuthIdentifier();
        $sessions = [];

        // Cap limit at 100 to prevent abuse
        $limit = min($limit, 100);

        if ($sessionDriver === 'database') {
            $tableName = config('session.table', 'sessions');

            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                $records = DB::table($tableName)
                    ->where('user_id', $userId)
                    ->orderBy('last_activity', 'desc')
                    ->limit($limit)
                    ->offset($offset)
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

        // Fallback: Query from AuthenticationDevice table (also paginated)
        $devices = \Vendor\LaravelAuthentication\Models\AuthenticationDevice::where('user_id', $userId)
            ->orderBy('last_seen_at', 'desc')
            ->limit($limit)
            ->offset($offset)
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
     * Terminate every session and device credential that the previous password granted.
     *
     * PR-01: a password reset is the victim's response to a suspected compromise, so any
     * session cookie or remember-me token captured beforehand must stop working. Without
     * this the attacker keeps access while the victim believes the account is recovered.
     *
     * @return int number of revoked sessions/devices
     */
    public function revokeAllAfterCredentialChange(Authenticatable $user): int
    {
        $userId = $user->getAuthIdentifier();
        $revoked = 0;

        if ($user instanceof Model) {
            // Rotate the remember-me token so captured "remember me" cookies die.
            // Persist immediately: updatePassword() already ran and saved, so mutating
            // the in-memory attribute alone would never reach the database.
            $user->forceFill(['remember_token' => Str::random(60)])->save();
        }

        if (config('session.driver') === 'database') {
            $tableName = (string) config('session.table', 'sessions');

            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                $revoked += DB::table($tableName)->where('user_id', $userId)->delete();
            }
        }

        // Drop trusted-device markers too: a device trusted under the old credential must
        // not keep bypassing the second factor after a reset.
        $revoked += AuthenticationDevice::where('user_id', $userId)
            ->where(function ($query): void {
                $query->where('is_trusted', true)->orWhereNotNull('trust_token_hash');
            })
            ->update([
                'is_trusted'       => false,
                'trusted_until'    => null,
                'trust_token_hash' => null,
            ]);

        return $revoked;
    }

    /**
     * Revoke all other active sessions for the user after validating current password.
     */
    public function revokeOtherSessions(Authenticatable $user, #[SensitiveParameter] string $password, ?string $currentSessionId = null): bool
    {
        $passwordColumn = $this->config->getIdentifierColumn('password');
        $userHash = (string) ($user->{$passwordColumn} ?? '');

        if (!$this->hasher->check($password, $userHash)) {
            $msg = __('authentication::messages.invalid_password');
            throw new InvalidCredentialsException(is_string($msg) ? $msg : 'Invalid password.');
        }

        // Use Laravel's built-in logoutOtherDevices if available
        if (method_exists(Auth::guard($this->config->getGuard()), 'logoutOtherDevices')) {
            Auth::guard($this->config->getGuard())->logoutOtherDevices($password);
        }

        $userId = $user->getAuthIdentifier();

        // SEC-04 FIX: Revoking other sessions should also invalidate all server-side 2FA
        // trust tokens — a device no longer trusted elsewhere should not keep a working
        // trust cookie for this account.
        \Vendor\LaravelAuthentication\Models\AuthenticationDevice::where('user_id', $userId)
            ->where('is_trusted', true)
            ->update([
                'is_trusted'       => false,
                'trusted_until'    => null,
                'trust_token_hash' => null,
            ]);

        $sessionDriver = config('session.driver');

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

    /**
     * Enforce the configured maximum number of active sessions.
     *
     * When a user already has more active sessions than
     * `features.session_management.max_active_sessions` allows, the OLDEST
     * sessions (by last_activity) are revoked — never the current one.
     *
     * Previously (SA-28): config + getter existed but nothing enforced the
     * limit — the "max active sessions" feature was silently disconnected.
     *
     * @return int number of revoked (pruned) sessions
     */
    public function enforceMaxActiveSessions(Authenticatable $user, ?string $currentSessionId = null): int
    {
        if (!$this->config->isSessionManagementEnabled()) {
            return 0;
        }

        $maxSessions = $this->config->getMaxActiveSessions();

        if ($maxSessions < 1) {
            return 0;
        }

        $sessionDriver = config('session.driver');

        if ($sessionDriver === 'database') {
            $tableName = config('session.table', 'sessions');
            $userId = $user->getAuthIdentifier();

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return 0;
            }

            $total = DB::table($tableName)
                ->where('user_id', $userId)
                ->count();

            $excess = $total - $maxSessions;

            if ($excess <= 0) {
                return 0;
            }

            // Revoke oldest sessions first, preserving the current one.
            return DB::table($tableName)
                ->where('user_id', $userId)
                ->when($currentSessionId !== null, fn ($q) => $q->where('id', '!=', $currentSessionId))
                ->orderBy('last_activity', 'asc')
                ->limit($excess)
                ->delete();
        }

        // Non-database session driver: nothing to prune (devices are not
        // 1:1 with active sessions). Feature is a no-op there.
        return 0;
    }
}
