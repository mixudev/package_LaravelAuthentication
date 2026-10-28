<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Vendor\LaravelAuthentication\Support\SecurityDefenseDetector;

/**
 * Artisan command to automatically install and configure the Laravel Authentication package,
 * publish configuration and migrations, and automatically inject Tailwind CSS and dark mode sources into the host application.
 */
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'authentication:install
                            {--force : Overwrite existing configuration and published files}
                            {--migrate : Automatically run database migrations after installation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically install and configure Laravel Authentication, publish assets, and setup Tailwind CSS';

    public function handle(Filesystem $filesystem): int
    {
        $this->info('🚀 Installing mixudev/laravel-authentication...');
        $this->newLine();

        $force = (bool) $this->option('force');

        // 1. Publish Configuration
        $this->line('📄 Publishing configuration...');
        if ($this->call('vendor:publish', [
            '--tag'   => 'authentication-config',
            '--force' => $force,
        ]) !== self::SUCCESS) {
            $this->error('Failed to publish authentication configuration.');

            return self::FAILURE;
        }
        $this->line('  <info>✓</info> Configuration published to <comment>config/authentication.php</comment>');

        // 2. Publish Migrations
        $this->line('🗄️  Publishing migrations...');
        if ($this->call('vendor:publish', [
            '--tag'   => 'authentication-migrations',
            '--force' => $force,
        ]) !== self::SUCCESS) {
            $this->error('Failed to publish authentication migrations.');

            return self::FAILURE;
        }
        $this->line('  <info>✓</info> Database migrations published to <comment>database/migrations/</comment>');

        // 3. Configure Tailwind CSS & Vite in Host Application
        $this->configureTailwind($filesystem);

        // 4. Inject Alpine.js into Host Application's app.js (if Vite is present)
        $this->injectAlpine($filesystem);

        // 5. Run Migrations (if requested or confirmed)
        if ($this->option('migrate') || ($this->input->isInteractive() && $this->confirm('Would you like to run database migrations now?', true))) {
            $this->line('⚡ Running database migrations...');
            if ($this->call('migrate') !== self::SUCCESS) {
                $this->error('Database migrations failed. Authentication tables may be missing.');

                return self::FAILURE;
            }
            $this->line('  <info>✓</info> Migrations executed successfully.');
        }

        $this->newLine();
        $this->info('✨ mixudev/laravel-authentication installed and configured successfully!');
        $this->newLine();
        $this->line('👉 <comment>Key Features Ready to Use:</comment>');
        $this->line('  • Login / Register / Password Reset: <info>/login</info>, <info>/register</info>, <info>/forgot-password</info>');
        $this->line('  • 2FA & Active Sessions Dashboard:   <info>/auth/sessions</info>');
        $this->line('  • 2FA TOTP Setup & QR Code:          <info>/auth/two-factor/setup</info>');
        $this->newLine();
        $this->line('💡 <comment>Views NOT published:</comment> Package views load from <info>vendor/</info> automatically.');
        $this->line('   To customize views: <info>php artisan vendor:publish --tag=authentication-views</info>');
        $this->newLine();

        // Show security-defense integration hint if package detected
        if ((bool) config('authentication.security_defense.auto_detect', true)) {
            $hint = SecurityDefenseDetector::getStatusMessage();
            if ($hint !== null) {
                $this->line($hint);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Automatically configure Tailwind CSS sources in the host application.
     */
    protected function configureTailwind(Filesystem $filesystem): void
    {
        $this->line('🎨 Configuring Tailwind CSS & Styles...');

        $appCssPath = resource_path('css/app.css');
        $tailwindConfigJsPath = base_path('tailwind.config.js');
        $tailwindConfigTsPath = base_path('tailwind.config.ts');

        $configured = false;

        // Tailwind CSS v4 / Modern app.css integration
        if ($filesystem->exists($appCssPath)) {
            $content = $filesystem->get($appCssPath);
            $modified = false;

            $sourceDirective = '@source "../../vendor/mixudev/laravel-authentication/resources/views";';
            $darkVariantDirective = '@custom-variant dark (&:where(.dark, .dark *));';

            if (!str_contains($content, 'mixudev/laravel-authentication')) {
                // If contains @import 'tailwindcss'; or @import "tailwindcss";
                if (preg_match('/@import\s+[\'"]tailwindcss[\'"];?/', $content, $matches)) {
                    $replacement = $matches[0] . "\n\n" . $sourceDirective;
                    $content = str_replace($matches[0], $replacement, $content);
                } else {
                    $content = $sourceDirective . "\n" . $content;
                }
                $modified = true;
                $this->line("  <info>✓</info> Injected @source directive into <comment>resources/css/app.css</comment>");
            }

            if (!str_contains($content, '@custom-variant dark')) {
                if (str_contains($content, 'tailwindcss')) {
                    $content .= "\n" . $darkVariantDirective . "\n";
                    $modified = true;
                    $this->line("  <info>✓</info> Injected @custom-variant dark into <comment>resources/css/app.css</comment>");
                }
            }

            if ($modified) {
                $filesystem->put($appCssPath, $content);
                $configured = true;
            } else {
                $this->line("  <info>✓</info> <comment>resources/css/app.css</comment> already contains package Tailwind sources.");
                $configured = true;
            }
        }

        // Tailwind CSS v3 (tailwind.config.js / ts)
        $tailwindConfigPath = $filesystem->exists($tailwindConfigJsPath) ? $tailwindConfigJsPath : ($filesystem->exists($tailwindConfigTsPath) ? $tailwindConfigTsPath : null);

        if ($tailwindConfigPath) {
            $content = $filesystem->get($tailwindConfigPath);
            $packageViewPattern = './vendor/mixudev/laravel-authentication/resources/views/**/*.blade.php';

            if (!str_contains($content, 'mixudev/laravel-authentication')) {
                if (preg_match('/content:\s*\[([^\]]*)\]/s', $content, $matches)) {
                    $currentContent = $matches[1];
                    $newEntry = "\n        '{$packageViewPattern}',";
                    $replacement = "content: [" . rtrim($currentContent, " \t\n\r,") . "," . $newEntry . "\n    ]";
                    $content = str_replace($matches[0], $replacement, $content);
                    $filesystem->put($tailwindConfigPath, $content);
                    $this->line("  <info>✓</info> Added package Blade views to <comment>" . basename($tailwindConfigPath) . "</comment> content list.");
                    $configured = true;
                }
            } else {
                $this->line("  <info>✓</info> <comment>" . basename($tailwindConfigPath) . "</comment> already includes package Blade views.");
                $configured = true;
            }
        }

        if (!$configured) {
            $this->line('  <comment>ℹ Note:</comment> Custom CSS file not detected automatically. Make sure Tailwind scans package views:');
            $this->line("    <info>./vendor/mixudev/laravel-authentication/resources/views/**/*.blade.php</info>");
        }
    }

    /**
     * Inject Alpine.js into the host application's Vite bundle (app.js).
     *
     * Strategy:
     *   1. Detect if Vite is used (vite.config.js / package.json present).
     *   2. Check package.json — skip if Alpine already installed.
     *   3. If not installed: run `npm install alpinejs --save-dev`.
     *   4. Check app.js — skip if Alpine already imported.
     *   5. If not imported: prepend Alpine bootstrap code to app.js.
     *
     * Idempotent: safe to run multiple times.
     */
    protected function injectAlpine(Filesystem $filesystem): void
    {
        $this->line('🏔️  Configuring Alpine.js...');

        $packageJsonPath = base_path('package.json');
        $appJsPath       = resource_path('js/app.js');

        // Only proceed if the host app uses Vite (package.json must exist)
        if (!$filesystem->exists($packageJsonPath)) {
            $this->line('  <comment>ℹ</comment> No package.json found — skipping Alpine.js injection (non-Vite app).');
            $this->line('    Package views include a pinned CDN fallback for Alpine 3.x.');

            return;
        }

        // ── Step 1: Check if Alpine is already in package.json ──────────────
        $packageJson = json_decode($filesystem->get($packageJsonPath), true);
        $allDeps = array_merge(
            (array) ($packageJson['dependencies'] ?? []),
            (array) ($packageJson['devDependencies'] ?? []),
            (array) ($packageJson['optionalDependencies'] ?? []),
        );

        $alpineInstalled = isset($allDeps['alpinejs']);

        if ($alpineInstalled) {
            $this->line('  <info>✓</info> Alpine.js already in <comment>package.json</comment> — skipping npm install.');
        } else {
            // ── Step 2: Install Alpine.js via npm ────────────────────────────
            $this->line('  📦 Installing <comment>alpinejs</comment> via npm...');
            $npmBin  = PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';
            $result  = 0;
            $output  = [];
            $command = escapeshellarg($npmBin) . ' install alpinejs --save-dev --silent 2>&1';
            exec($command, $output, $result);

            if ($result !== 0) {
                $this->warn('  ⚠ npm install failed. Install manually: <comment>npm install alpinejs --save-dev</comment>');
            } else {
                $this->line('  <info>✓</info> Alpine.js installed successfully.');
            }
        }

        // ── Step 3: Check / patch app.js ────────────────────────────────────
        if (!$filesystem->exists($appJsPath)) {
            $this->line('  <comment>ℹ</comment> <comment>resources/js/app.js</comment> not found — skipping app.js injection.');
            $this->line("    Add this manually to your JS entry file:\n");
            $this->line("    <info>import Alpine from 'alpinejs';\nwindow.Alpine = Alpine;\nAlpine.start();</info>");

            return;
        }

        $jsContent     = $filesystem->get($appJsPath);
        $alpineImport  = "import Alpine from 'alpinejs';";

        // Skip if Alpine already imported / started
        if (str_contains($jsContent, 'alpinejs') || str_contains($jsContent, 'Alpine.start')) {
            $this->line('  <info>✓</info> Alpine.js already imported in <comment>resources/js/app.js</comment> — skipping injection.');

            return;
        }

        // ── Step 4: Inject Alpine bootstrap at the TOP of app.js ────────────
        $alpineBootstrap = <<<'JS'
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

JS;

        // Prepend after any leading comment blocks (preserve existing comments)
        if (preg_match('/^(\s*(\/\/[^\n]*\n|\/\*[\s\S]*?\*\/\s*)*)(.*)$/s', $jsContent, $m)) {
            $leadingComments = $m[1];
            $rest            = $m[3];
            $newContent      = $leadingComments . $alpineBootstrap . ($rest !== '' ? "\n" . ltrim($rest) : '');
        } else {
            $newContent = $alpineBootstrap . $jsContent;
        }

        $filesystem->put($appJsPath, $newContent);
        $this->line('  <info>✓</info> Alpine.js injected into <comment>resources/js/app.js</comment>.');
        $this->line('  <comment>→ Run <info>npm run build</info> or <info>npm run dev</info> to bundle.</comment>');
    }
}
