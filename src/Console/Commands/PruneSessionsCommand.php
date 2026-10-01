<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;

/**
 * Prune expired sessions and stale device records.
 *
 * ENTERPRISE: For high-traffic applications, session/device tables grow unbounded.
 * This command removes expired sessions (inactive >30 days by default) and
 * untrusted devices not seen in 90+ days to prevent table bloat.
 *
 * Usage:
 *   php artisan authentication:prune-sessions
 *   php artisan authentication:prune-sessions --session-days=7 --device-days=30
 *   php artisan authentication:prune-sessions --dry-run
 *
 * Schedule in production (daily at 2 AM):
 *   $schedule->command('authentication:prune-sessions')->dailyAt('02:00');
 */
class PruneSessionsCommand extends Command
{
    protected $signature = 'authentication:prune-sessions
        {--session-days=30 : Remove sessions inactive for N days}
        {--device-days=90 : Remove untrusted devices not seen for N days}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Hapus session kadaluarsa dan device record yang tidak aktif';

    public function handle(): int
    {
        $sessionDays = (int) $this->option('session-days');
        $deviceDays = (int) $this->option('device-days');
        $dryRun = (bool) $this->option('dry-run');

        if ($sessionDays < 1 || $deviceDays < 1) {
            $this->error('Nilai --session-days dan --device-days harus >= 1');
            return self::FAILURE;
        }

        $sessionCutoff = Carbon::now()->subDays($sessionDays);
        $deviceCutoff = Carbon::now()->subDays($deviceDays);

        $this->info('Mode: ' . ($dryRun ? 'DRY-RUN (simulasi)' : 'EKSEKUSI'));
        $this->line('');

        $total = 0;

        // 1. Prune database sessions (if driver = database)
        if (config('session.driver') === 'database') {
            $total += $this->pruneDatabaseSessions($sessionCutoff, $dryRun);
        } else {
            $this->line('  Session driver: ' . config('session.driver') . ' (skip database cleanup)');
        }

        // 2. Prune untrusted stale devices
        $total += $this->pruneStaleDevices($deviceCutoff, $dryRun);

        $this->newLine();
        $this->info($dryRun
            ? "Siap dihapus: {$total} record. Jalankan tanpa --dry-run untuk eksekusi."
            : "Selesai: {$total} record dihapus.");

        return self::SUCCESS;
    }

    /**
     * Prune expired sessions from Laravel's sessions table.
     */
    protected function pruneDatabaseSessions(Carbon $cutoff, bool $dryRun): int
    {
        $tableName = config('session.table', 'sessions');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            $this->line("  Sessions table '{$tableName}': not found (skip)");
            return 0;
        }

        $query = DB::table($tableName)->where('last_activity', '<', $cutoff->timestamp);
        $count = $query->count();

        if ($count > 0 && !$dryRun) {
            $query->delete();
        }

        $this->line(sprintf(
            '  %-30s: %d record%s (cutoff: %s)',
            'Expired sessions',
            $count,
            $count === 1 ? '' : 's',
            $cutoff->toDateString()
        ));

        return $count;
    }

    /**
     * Prune untrusted devices not seen for N days.
     *
     * Trusted devices are kept indefinitely (or until trust expires).
     * Only stale untrusted devices are removed to prevent table bloat.
     */
    protected function pruneStaleDevices(Carbon $cutoff, bool $dryRun): int
    {
        $query = AuthenticationDevice::where('is_trusted', false)
            ->where('last_seen_at', '<', $cutoff);

        $count = $query->count();

        if ($count > 0 && !$dryRun) {
            $query->delete();
        }

        $this->line(sprintf(
            '  %-30s: %d record%s (cutoff: %s)',
            'Stale untrusted devices',
            $count,
            $count === 1 ? '' : 's',
            $cutoff->toDateString()
        ));

        return $count;
    }
}
