<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature\Console;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vendor\LaravelAuthentication\Models\AuthenticationAttempt;
use Vendor\LaravelAuthentication\Models\LoginHistory;
use Vendor\LaravelAuthentication\Models\PasswordHistory;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Proves authentication:prune actually consumes audit.retention_days
 * (previously a dead config key) and only removes records OLDER than the
 * cutoff, across all three audit tables.
 */
class PruneAuditLogsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'authentication_attempts',
            'authentication_login_histories',
            'authentication_password_histories',
        ] as $table) {
            if (!Schema::hasTable($table)) {
                Schema::create($table, function ($t) {
                    $t->id();
                    $t->string('identifier', 255)->nullable();
                    $t->string('ip_address', 45)->nullable();
                    $t->string('status', 32)->nullable();
                    $t->string('user_agent')->nullable();
                    $t->string('password_hash')->nullable();
                    $t->string('failure_reason', 64)->nullable();
                    $t->string('strategy', 64)->nullable();
                    $t->string('channel', 32)->nullable();
                    $t->timestamp('attempted_at')->nullable();
                    $t->timestamp('login_at')->nullable();
                    $t->timestamp('created_at')->nullable();
                });
            }
        }
    }

    public function test_prune_deletes_old_records_and_keeps_recent_ones(): void
    {
        config(['authentication.audit.retention_days' => 30]);

        // Old (beyond retention)
        AuthenticationAttempt::create([
            'identifier'   => 'old@example.com',
            'ip_address'   => '127.0.0.1',
            'status'       => 'FAILED',
            'attempted_at' => Carbon::now()->subDays(60),
        ]);
        LoginHistory::create([
            'user_id'    => 1,
            'identifier' => 'old@example.com',
            'ip_address' => '127.0.0.1',
            'login_at'   => Carbon::now()->subDays(60),
        ]);
        PasswordHistory::create([
            'user_id'    => 1,
            'password_hash' => 'x',
            'created_at' => Carbon::now()->subDays(60),
        ]);

        // Recent (within retention)
        AuthenticationAttempt::create([
            'identifier'   => 'new@example.com',
            'ip_address'   => '127.0.0.1',
            'status'       => 'SUCCESS',
            'attempted_at' => Carbon::now()->subDays(2),
        ]);
        LoginHistory::create([
            'user_id'    => 2,
            'identifier' => 'new@example.com',
            'ip_address' => '127.0.0.1',
            'login_at'   => Carbon::now()->subDays(2),
        ]);
        PasswordHistory::create([
            'user_id'    => 2,
            'password_hash' => 'y',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $this->artisan('authentication:prune')
            ->expectsOutputToContain('Retensi: 30 hari')
            ->assertExitCode(0);

        $this->assertSame(1, AuthenticationAttempt::count());
        $this->assertSame(1, LoginHistory::count());
        $this->assertSame(1, PasswordHistory::count());

        $this->assertSame('new@example.com', AuthenticationAttempt::first()->identifier);
                $this->assertSame(2, LoginHistory::first()->user_id);
    }

    public function test_dry_run_does_not_delete(): void
    {
        config(['authentication.audit.retention_days' => 30]);

        AuthenticationAttempt::create([
            'identifier'   => 'old@example.com',
            'ip_address'   => '127.0.0.1',
            'status'       => 'FAILED',
            'attempted_at' => Carbon::now()->subDays(60),
        ]);

        $this->artisan('authentication:prune', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);

        $this->assertSame(1, AuthenticationAttempt::count());
    }

    public function test_days_override_wins_over_config(): void
    {
        config(['authentication.audit.retention_days' => 1]);

        AuthenticationAttempt::create([
            'identifier'   => 'old@example.com',
            'ip_address'   => '127.0.0.1',
            'status'       => 'FAILED',
            'attempted_at' => Carbon::now()->subDays(10),
        ]);

        // --days=90 → 10-day-old record stays within window
        $this->artisan('authentication:prune', ['--days' => 90])
            ->expectsOutputToContain('Retensi: 90 hari')
            ->assertExitCode(0);

        $this->assertSame(1, AuthenticationAttempt::count());

        // --days=5 → 10-day-old record is beyond window → pruned
        $this->artisan('authentication:prune', ['--days' => 5])
            ->expectsOutputToContain('Retensi: 5 hari')
            ->assertExitCode(0);

        $this->assertSame(0, AuthenticationAttempt::count());
    }
}