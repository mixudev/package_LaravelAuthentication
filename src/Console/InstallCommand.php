<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

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
                            {--views : Also publish Blade view templates to resources/views/vendor/authentication}
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
        $this->call('vendor:publish', [
            '--tag'   => 'authentication-config',
            '--force' => $force,
        ]);
        $this->line('  <info>✓</info> Configuration published to <comment>config/authentication.php</comment>');

        // 2. Publish Migrations
        $this->line('🗄️  Publishing migrations...');
        $this->call('vendor:publish', [
            '--tag'   => 'authentication-migrations',
            '--force' => $force,
        ]);
        $this->line('  <info>✓</info> Database migrations published to <comment>database/migrations/</comment>');

        // 3. Publish Views (Optional)
        if ($this->option('views')) {
            $this->line('🎨 Publishing Blade views...');
            $this->call('vendor:publish', [
                '--tag'   => 'authentication-views',
                '--force' => $force,
            ]);
            $this->line('  <info>✓</info> Blade views published to <comment>resources/views/vendor/authentication/</comment>');
        }

        // 4. Configure Tailwind CSS & Vite in Host Application
        $this->configureTailwind($filesystem);

        // 5. Run Migrations (if requested or confirmed)
        if ($this->option('migrate') || ($this->input->isInteractive() && $this->confirm('Would you like to run database migrations now?', true))) {
            $this->line('⚡ Running database migrations...');
            $this->call('migrate');
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
}
