<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * Health check command for Kubernetes readiness/liveness probes.
 *
 * ENTERPRISE: Production deployments need health checks for load balancers,
 * container orchestration (Kubernetes, Docker Swarm, ECS), and monitoring.
 *
 * Exit codes:
 *   0 = healthy (all checks passed)
 *   1 = unhealthy (one or more checks failed)
 *
 * Usage in Kubernetes:
 *   livenessProbe:
 *     exec:
 *       command: ["php", "artisan", "authentication:health"]
 *     initialDelaySeconds: 10
 *     periodSeconds: 30
 *
 * Usage in monitoring:
 *   php artisan authentication:health || alert "Auth service unhealthy"
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'authentication:health
        {--detailed : Show detailed check results}
        {--silent : Production mode - exit code only, no output}';

    protected $description = 'Verifikasi kesehatan komponen autentikasi (database, cache, config)';

    /** @var array<string, callable(): void> */
    protected array $checks = [];
    protected int $failures = 0;

    public function handle(AuthenticationConfig $config): int
    {
        $detailed = (bool) $this->option('detailed');
        $silent = (bool) $this->option('silent');

        $this->checks = [
            'Package Enabled'      => fn() => $this->checkEnabled($config),
            'Database Connection'  => fn() => $this->checkDatabase(),
            'Cache Connection'     => fn() => $this->checkCache(),
            'Required Tables'      => fn() => $this->checkTables(),
            'User Model Loadable'  => fn() => $this->checkUserModel($config),
            'Strategy Registry'    => fn() => $this->checkStrategies($config),
        ];

        foreach ($this->checks as $name => $check) {
            try {
                $check();
                if ($detailed && !$silent) {
                    $this->line("<fg=green>✓</> {$name}");
                }
            } catch (Throwable $e) {
                $this->failures++;
                // SECURITY FIX: Sanitize error messages in production (no table names, class paths)
                if (!$silent) {
                    $message = $detailed ? $e->getMessage() : $this->sanitizeErrorMessage($name);
                    $this->error("✗ {$name}: " . $message);
                }
            }
        }

        $total = count($this->checks);
        $passed = $total - $this->failures;

        if (!$silent) {
            $this->newLine();

            if ($this->failures === 0) {
                $this->info("✓ Healthy: {$passed}/{$total} checks passed");
            } else {
                $this->error("✗ Unhealthy: {$this->failures}/{$total} checks failed");
            }
        }

        return $this->failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Check if package is enabled via config.
     */
    protected function checkEnabled(AuthenticationConfig $config): void
    {
        if (!$config->isEnabled()) {
            throw new \RuntimeException('Package disabled in config (authentication.enabled = false)');
        }
    }

    /**
     * Check database connectivity.
     */
    protected function checkDatabase(): void
    {
        DB::connection()->getPdo();
        DB::connection()->select('SELECT 1');
    }

    /**
     * Check cache connectivity (rate limiter depends on cache).
     */
    protected function checkCache(): void
    {
        $key = 'auth_health_check_' . time();
        Cache::put($key, 'ok', 5);

        if (Cache::get($key) !== 'ok') {
            throw new \RuntimeException('Cache write/read verification failed');
        }

        Cache::forget($key);
    }

    /**
     * Check required tables exist.
     */
    protected function checkTables(): void
    {
        $requiredTables = [
            'authentication_attempts',
            'authentication_login_histories',
            'authentication_account_lockouts',
        ];

        foreach ($requiredTables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                throw new \RuntimeException("Required table '{$table}' not found. Run migrations.");
            }
        }
    }

    /**
     * Check if configured user model is loadable.
     */
    protected function checkUserModel(AuthenticationConfig $config): void
    {
        $userModel = $config->getUserModel();

        if (!class_exists($userModel)) {
            throw new \RuntimeException("User model '{$userModel}' not found");
        }

        // Verify model is Eloquent + Authenticatable
        $instance = new $userModel();

        if (!$instance instanceof \Illuminate\Database\Eloquent\Model) {
            throw new \RuntimeException("User model must extend Eloquent Model");
        }

        if (!$instance instanceof \Illuminate\Contracts\Auth\Authenticatable) {
            throw new \RuntimeException("User model must implement Authenticatable");
        }
    }

    /**
     * Check authentication strategies are registered.
     */
    protected function checkStrategies(AuthenticationConfig $config): void
    {
        $defaultStrategy = $config->getDefaultStrategy();
        $strategies = (array) config('authentication.login.strategies', []);

        if (empty($strategies)) {
            throw new \RuntimeException('No authentication strategies configured');
        }

        if (!isset($strategies[$defaultStrategy])) {
            throw new \RuntimeException("Default strategy '{$defaultStrategy}' not registered");
        }

        // Verify default strategy class exists
        $strategyClass = $strategies[$defaultStrategy];
        if (!class_exists($strategyClass)) {
            throw new \RuntimeException("Strategy class '{$strategyClass}' not found");
        }
    }

    /**
     * SECURITY FIX: Sanitize error messages for production health probes.
     * Prevents architecture disclosure in Kubernetes logs.
     */
    protected function sanitizeErrorMessage(string $checkName): string
    {
        return match ($checkName) {
            'Package Enabled' => 'Package configuration error',
            'Database Connection' => 'Database connectivity failed',
            'Cache Connection' => 'Cache connectivity failed',
            'Required Tables' => 'Database schema incomplete',
            'User Model Loadable' => 'User model configuration error',
            'Strategy Registry' => 'Strategy configuration error',
            default => 'Check failed',
        };
    }
}
