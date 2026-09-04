<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Artisan command to export the entire authentication package into a clean,
 * self-contained, single-folder module directory (e.g. modules/Authentication or app/Modules/Authentication).
 */
class InstallModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'authentication:install-module
                            {--path=modules/Authentication : Target folder relative to application root}
                            {--force : Overwrite existing module files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all authentication assets into a unified, self-contained modular directory';

    public function handle(Filesystem $filesystem): int
    {
        $targetRelativePath = is_string($this->option('path')) ? $this->option('path') : 'modules/Authentication';
        $targetFullPath = base_path($targetRelativePath);
        $force = (bool) $this->option('force');

        $this->info("📦 Installing Laravel Authentication as a Unified Module...");
        $this->line("Target Directory: <comment>{$targetFullPath}</comment>");

        if ($filesystem->exists($targetFullPath) && !$force) {
            if (!$this->confirm("Directory [{$targetRelativePath}] already exists. Overwrite files?", false)) {
                $this->warn("Installation aborted.");
                return self::FAILURE;
            }
        }

        $packageRoot = dirname(__DIR__, 2);

        // 1. Create Module Directories
        $filesystem->ensureDirectoryExists("{$targetFullPath}/Config");
        $filesystem->ensureDirectoryExists("{$targetFullPath}/Database/Migrations");
        $filesystem->ensureDirectoryExists("{$targetFullPath}/Resources/Views");
        $filesystem->ensureDirectoryExists("{$targetFullPath}/Routes");

        // 2. Copy Config
        $filesystem->copy("{$packageRoot}/config/authentication.php", "{$targetFullPath}/Config/authentication.php");
        $this->line("  ✓ Config copied to <info>Config/authentication.php</info>");

        // 3. Copy Migrations
        $filesystem->copyDirectory("{$packageRoot}/database/migrations", "{$targetFullPath}/Database/Migrations");
        $this->line("  ✓ Migrations copied to <info>Database/Migrations/</info>");

        // 4. Copy Views
        $filesystem->copyDirectory("{$packageRoot}/resources/views", "{$targetFullPath}/Resources/Views");
        $this->line("  ✓ Blade Views copied to <info>Resources/Views/</info>");

        // 5. Copy Routes
        $filesystem->copy("{$packageRoot}/routes/web.php", "{$targetFullPath}/Routes/web.php");
        $filesystem->copy("{$packageRoot}/routes/api.php", "{$targetFullPath}/Routes/api.php");
        $this->line("  ✓ Routes copied to <info>Routes/web.php</info> & <info>Routes/api.php</info>");

        // 6. Generate Self-Contained Module Service Provider
        $providerContent = $this->generateModuleServiceProvider($targetRelativePath);
        $filesystem->put("{$targetFullPath}/AuthenticationModuleServiceProvider.php", $providerContent);
        $this->line("  ✓ Generated <info>AuthenticationModuleServiceProvider.php</info>");

        $this->newLine();
        $this->info("✨ Authentication Module installed successfully in [{$targetRelativePath}]!");
        $this->newLine();

        $this->comment("👉 Next Steps to Activate:");
        $this->line("1. Register the module Service Provider in <comment>bootstrap/providers.php</comment> (Laravel 11-13) or <comment>config/app.php</comment>:");
        $this->line("   <info>\\Modules\\Authentication\\AuthenticationModuleServiceProvider::class,</info>");
        $this->newLine();
        $this->line("2. Add PSR-4 autoloading in your <comment>composer.json</comment> if not already mapped:");
        $this->line('   <info>"autoload": { "psr-4": { "Modules\\\\": "modules/" } }</info>');
        $this->line("   Then run: <comment>composer dump-autoload</comment>");
        $this->newLine();
        $this->line("3. Run database migrations:");
        $this->line("   <comment>php artisan migrate</comment>");

        return self::SUCCESS;
    }

    protected function generateModuleServiceProvider(string $relativePath): string
    {
        $namespace = str_replace(['/', '\\'], '\\', trim($relativePath, '/\\'));
        $namespace = ucfirst($namespace);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\Support\ServiceProvider;

/**
 * Self-contained Service Provider for the Authentication Module.
 * Automatically bootstraps Config, Migrations, Views, and Routes from this module folder.
 */
class AuthenticationModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Load & Override Module Config
        \$moduleConfigFile = __DIR__ . '/Config/authentication.php';
        if (file_exists(\$moduleConfigFile)) {
            \$moduleConfig = require \$moduleConfigFile;
            \$currentConfig = \$this->app['config']->get('authentication', []);
            \$this->app['config']->set('authentication', array_replace_recursive(\$currentConfig, \$moduleConfig));
        }
    }

    public function boot(): void
    {
        // 1. Load Migrations from Module folder
        \$this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        // 2. Load Views from Module folder under namespace 'authentication'
        \$this->loadViewsFrom(__DIR__ . '/Resources/Views', 'authentication');

        // 3. Load Web Routes
        if (config('authentication.routes.web.enabled', true)) {
            \$this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
        }

        // 4. Load API Routes
        if (config('authentication.routes.api.enabled', true)) {
            \$this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
        }
    }
}
PHP;
    }
}
