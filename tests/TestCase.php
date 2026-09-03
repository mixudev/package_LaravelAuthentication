<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Vendor\LaravelAuthentication\Providers\AuthenticationServiceProvider;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AuthenticationServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('authentication.user_model', User::class);
        $app['config']->set('authentication.login.default_strategy', 'username_or_email');
        $app['config']->set('authentication.security.rate_limit.enabled', true);
        $app['config']->set('authentication.security.rate_limit.max_attempts', 5);
        $app['config']->set('authentication.security.rate_limit.decay_minutes', 1);
        $app['config']->set('authentication.security.account_lockout.enabled', true);
        $app['config']->set('authentication.security.account_lockout.max_failed_attempts', 5);
        $app['config']->set('authentication.security.account_lockout.lockout_duration_mins', 15);
        $app['config']->set('authentication.password.history.enabled', true);
        $app['config']->set('authentication.audit.enabled', true);
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.cipher', 'AES-256-CBC');
        $app['config']->set('authentication.routes.api.enabled', true);
        $app['config']->set('authentication.routes.api.auth_middleware', ['auth']);
        $app['config']->set('mail.mailer', 'array');
        $app['config']->set('mail.default', 'array');
    }

    protected function setUpDatabase(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->unique()->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('employee_id')->unique()->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }
}
