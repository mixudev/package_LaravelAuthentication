<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialResolverInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialValidatorInterface;
use Vendor\LaravelAuthentication\Contracts\LoginAttemptManagerInterface;
use Vendor\LaravelAuthentication\Contracts\OtpServiceInterface;
use Vendor\LaravelAuthentication\Contracts\PasswordHistoryRepositoryInterface;
use Vendor\LaravelAuthentication\Contracts\RegistrationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\SocialAuthServiceInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\Http\Middleware\CheckAuthenticationSetup;
use Vendor\LaravelAuthentication\Repositories\PasswordHistoryRepository;
use Vendor\LaravelAuthentication\Services\AuthenticationAuditService;
use Vendor\LaravelAuthentication\Services\AuthenticationService;
use Vendor\LaravelAuthentication\Services\CredentialResolver;
use Vendor\LaravelAuthentication\Services\CredentialValidator;
use Vendor\LaravelAuthentication\Services\LoginAttemptManager;
use Vendor\LaravelAuthentication\Services\OtpService;
use Vendor\LaravelAuthentication\Services\RegistrationService;
use Vendor\LaravelAuthentication\Services\SocialAuthService;
use Vendor\LaravelAuthentication\Services\TokenService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\AuthenticationStrategyRegistry;
use Vendor\LaravelAuthentication\Support\SetupHealthChecker;

/**
 * Main package Service Provider responsible for DI registrations,
 * config publishing, migration registration, and strategy bootstrapping.
 */
class AuthenticationServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        // 1. Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/authentication.php',
            'authentication'
        );

        // 2. Register Singleton Config Helper
        $this->app->singleton(AuthenticationConfig::class, function ($app) {
            return new AuthenticationConfig($app['config']);
        });

        // 3. Register Strategy Registry & Default Strategies
        $this->app->singleton(AuthenticationStrategyRegistry::class, function ($app) {
            $registry = new AuthenticationStrategyRegistry($app);
            $strategies = $app['config']->get('authentication.login.strategies', []);

            foreach ($strategies as $name => $strategyClass) {
                $registry->register($name, $strategyClass);
            }

            return $registry;
        });

        // 4. Bind Interfaces to Concrete Implementations
        $this->app->bind(CredentialResolverInterface::class, CredentialResolver::class);
        $this->app->bind(CredentialValidatorInterface::class, CredentialValidator::class);
        $this->app->bind(LoginAttemptManagerInterface::class, LoginAttemptManager::class);
        $this->app->bind(TokenManagerInterface::class, TokenService::class);
        $this->app->bind(AuditLoggerInterface::class, AuthenticationAuditService::class);
        $this->app->bind(PasswordHistoryRepositoryInterface::class, PasswordHistoryRepository::class);
        $this->app->bind(RegistrationServiceInterface::class, RegistrationService::class);
        $this->app->bind(OtpServiceInterface::class, OtpService::class);
        $this->app->bind(SocialAuthServiceInterface::class, SocialAuthService::class);
        $this->app->bind(AuthenticationServiceInterface::class, AuthenticationService::class);

        // Alias main package entrypoint
        $this->app->alias(AuthenticationServiceInterface::class, 'laravel-authentication');

        // 5. Register Setup Health Checker as a singleton (cached per request)
        $this->app->singleton(SetupHealthChecker::class, function ($app) {
            return new SetupHealthChecker($app, $app['config']);
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // 1. Publish Configuration
            $this->publishes([
                __DIR__ . '/../../config/authentication.php' => config_path('authentication.php'),
            ], 'authentication-config');

            // 2. Publish Migrations (enumerate files explicitly — publishes() does not support directory-to-directory)
            $this->publishes(
                $this->buildPublishMap(
                    __DIR__ . '/../../database/migrations',
                    database_path('migrations')
                ),
                'authentication-migrations'
            );

            // 3. Publish Views (enumerate files explicitly, including sub-directories)
            $this->publishes(
                $this->buildPublishMap(
                    __DIR__ . '/../../resources/views',
                    resource_path('views/vendor/authentication')
                ),
                'authentication-views'
            );

            // 4. Publish Translations
            if (is_dir(__DIR__ . '/../../resources/lang')) {
                $this->publishes(
                    $this->buildPublishMap(
                        __DIR__ . '/../../resources/lang',
                        $this->app->langPath('vendor/authentication')
                    ),
                    'authentication-lang'
                );
            }

            // 5. Publish Full Unified Module in one directory
            //    The `authentication:install-module` Artisan command is the recommended method.
            //    This tag provides a standard `vendor:publish` alternative.
            $modulePublishMap = [
                __DIR__ . '/../../config/authentication.php' => base_path('modules/Authentication/Config/authentication.php'),
                __DIR__ . '/../../routes/web.php'            => base_path('modules/Authentication/Routes/web.php'),
                __DIR__ . '/../../routes/api.php'            => base_path('modules/Authentication/Routes/api.php'),
            ];
            $modulePublishMap = array_merge(
                $modulePublishMap,
                $this->buildPublishMap(
                    __DIR__ . '/../../database/migrations',
                    base_path('modules/Authentication/Database/Migrations')
                ),
                $this->buildPublishMap(
                    __DIR__ . '/../../resources/views',
                    base_path('modules/Authentication/Resources/Views')
                )
            );
            $this->publishes($modulePublishMap, 'authentication-module');

            // Register CLI Commands
            $this->commands([
                \Vendor\LaravelAuthentication\Console\InstallModuleCommand::class,
            ]);
        }

        // Load Migrations automatically if in testing or auto-load enabled
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Load Views & Translations namespace
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'authentication');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'authentication');

        // Register the setup-check middleware alias so routes can reference it by name
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('auth.setup.check', CheckAuthenticationSetup::class);

        // Register package routes
        $this->registerRoutes();
    }

    /**
     * Register package routes based on configuration.
     */
    protected function registerRoutes(): void
    {
        if (config('authentication.routes.web.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        }

        if (config('authentication.routes.api.enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        }
    }

    /**
     * Recursively enumerate all files in a source directory and map them
     * to corresponding paths in the destination directory.
     *
     * Laravel's publishes() method does not support directory-to-directory mapping;
     * each source file must be listed individually.
     *
     * Normalizes paths using realpath() to handle cross-platform separator differences
     * (e.g., Windows backslash vs Unix forward slash).
     *
     * @return array<string, string>
     */
    protected function buildPublishMap(string $sourceDir, string $destDir): array
    {
        $map = [];

        $resolvedSource = realpath($sourceDir);

        if ($resolvedSource === false || !is_dir($resolvedSource)) {
            return $map;
        }

        /** @var \SplFileInfo $file */
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($resolvedSource, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $absoluteSource = $file->getPathname();

            // Strip the base source directory, normalize separators to forward slashes
            $relativePath = ltrim(
                str_replace(
                    [DIRECTORY_SEPARATOR, '\\'],
                    '/',
                    substr($absoluteSource, strlen($resolvedSource))
                ),
                '/'
            );

            // Build destination path using OS-native separator
            $absoluteDest = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $destDir), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            $map[$absoluteSource] = $absoluteDest;
        }

        return $map;
    }
}
