<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Vendor\LaravelAuthentication\Models\AuthenticationAttempt;
use Vendor\LaravelAuthentication\Models\LoginHistory;
use Vendor\LaravelAuthentication\Models\PasswordHistory;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Prune old audit/trace records (attempts, login histories, password
 * histories) beyond the configured retention window.
 *
 * The `audit.retention_days` config (default 90) previously existed but was
 * never consumed anywhere (marked @deprecated in SA-27). This command is the
 * consumer that makes retention actually enforceable.
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'authentication:prune
        {--days= : Override audit.retention_days for this run}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Hapus data audit autentikasi yang lebih tua dari masa retensi (attempts, login histories, password histories)';

    public function handle(AuthenticationConfig $config): int
    {
        $days = $this->option('days');
        if ($days !== null && !is_numeric($days)) {
            $this->error('Opsi --days harus berupa angka.');
            return self::FAILURE;
        }

        $retentionDays = $days !== null ? (int) $days : $config->getAuditRetentionDays();
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays(max(1, $retentionDays));

        $targets = [
            'AuthenticationAttempt' => [AuthenticationAttempt::query(), 'attempted_at'],
            'LoginHistory'          => [LoginHistory::query(), 'login_at'],
            'PasswordHistory'       => [PasswordHistory::query(), 'created_at'],
        ];

        $this->info("Retensi: {$retentionDays} hari (cutoff {$cutoff->toDateTimeString()})");
        $this->info('Mode: ' . ($dryRun ? 'DRY-RUN (tidak ada data dihapus)' : 'EKSEKUSI'));

        $total = 0;

        foreach ($targets as $label => [$query, $column]) {
            $query->where($column, '<', $cutoff);

            $count = (clone $query)->count();

            if ($count > 0 && !$dryRun) {
                $query->delete();
            }

            $total += $count;
            $this->line(sprintf('  %-22s: %d record%s', $label, $count, $count === 1 ? '' : 's'));
        }

        $this->info($dryRun
            ? "Siap dihapus: {$total} record. Jalankan tanpa --dry-run untuk eksekusi."
            : "Selesai: {$total} record dihapus.");

        return self::SUCCESS;
    }
}