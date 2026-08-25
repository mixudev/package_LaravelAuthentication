<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Providers;

use Illuminate\Support\ServiceProvider;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialResolverInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialValidatorInterface;
use Vendor\LaravelAuthentication\Contracts\LoginAttemptManagerInterface;
use Vendor\LaravelAuthentication\Contracts\PasswordHistoryRepositoryInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\Repositories\PasswordHistoryRepository;
use Vendor\LaravelAuthentication\Services\AuthenticationAuditService;
use Vendor\LaravelAuthentication\Services\AuthenticationService;
use Vendor\LaravelAuthentication\Services\CredentialResolver;
use Vendor\LaravelAuthentication\Services\CredentialValidator;
use Vendor\LaravelAuthentication\Services\LoginAttemptManager;
use Vendor\LaravelAuthentication\Services\TokenService;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\AuthenticationStrategyRegistry;

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
        $this->app->bind(AuthenticationServiceInterface::class, AuthenticationService::class);

        // Alias main package entrypoint
        $this->app->alias(AuthenticationServiceInterface::class, 'laravel-authentication');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        // 1. Publish Configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/authentication.php' => config_path('authentication.php'),
            ], 'authentication-config');

            // 2. Publish Migrations
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'authentication-migrations');

            // 3. Publish Views (optional UI customization)
            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/authentication'),
            ], 'authentication-views');

            // 4. Publish Translations
            $this->publishes([
                __DIR__ . '/../../resources/lang' => $this->app->langPath('vendor/authentication'),
            ], 'authentication-lang');
        }

        // Load Migrations automatically if in testing or auto-load enabled
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Load Views & Translations namespace
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'authentication');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'authentication');

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
}
