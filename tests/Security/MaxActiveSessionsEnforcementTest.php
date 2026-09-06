<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Vendor\LaravelAuthentication\Services\Session\SessionManagerService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Red-team wiring test (SA-28): max_active_sessions was configured + exposed
 * via AuthenticationConfig but NEVER enforced anywhere — the feature was
 * silently disconnected. This proves enforcement now prunes the oldest
 * sessions on login.
 */
class MaxActiveSessionsEnforcementTest extends TestCase
{
    private User $user;
    private SessionManagerService $manager;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'authentication.features.session_management.enabled' => true,
            'authentication.features.session_management.max_active_sessions' => 2,
            'session.driver' => 'database',
            'session.table'  => 'sessions',
        ]);

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function ($table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        $this->user = User::create([
            'name'     => 'Session Cap',
            'username' => 'sesscap',
            'email'    => 'sesscap@example.com',
            'password' => Hash::make('SuperSecret123!'),
        ]);

        $this->manager = app(SessionManagerService::class);
    }

    private function insertSession(string $id, int $lastActivity): void
    {
        DB::table('sessions')->insert([
            'id'            => $id,
            'user_id'       => $this->user->id,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'PHPUnit',
            'payload'       => 'test',
            'last_activity' => $lastActivity,
        ]);
    }

    public function test_over_limit_prunes_oldest_sessions_keeping_current(): void
    {
        $now = time();

        // 3 existing sessions, oldest first
        $this->insertSession('sess_very_old', $now - 3000);
        $this->insertSession('sess_old', $now - 2000);
        $this->insertSession('sess_new', $now - 1000);

        $revoked = $this->manager->enforceMaxActiveSessions($this->user, 'current_new_session');

        // max=2, total=3 → exactly 1 revoked (the oldest)
        $this->assertSame(1, $revoked);

        $remaining = DB::table('sessions')->where('user_id', $this->user->id)->pluck('id')->all();
        $this->assertNotContains('sess_very_old', $remaining);
        $this->assertContains('sess_old', $remaining);
        $this->assertContains('sess_new', $remaining);
    }

    public function test_at_or_under_limit_revokes_nothing(): void
    {
        $now = time();

        $this->insertSession('sess_a', $now - 1000);
        $this->insertSession('sess_b', $now - 500);

        $revoked = $this->manager->enforceMaxActiveSessions($this->user, 'sess_b');

        $this->assertSame(0, $revoked);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $this->user->id)->count());
    }

    public function test_current_session_is_never_revoked(): void
    {
        $now = time();

        // 3 sessions, current is the OLDEST — must still be preserved
        $this->insertSession('current_oldest', $now - 5000);
        $this->insertSession('other_a', $now - 2000);
        $this->insertSession('other_b', $now - 1000);

        $revoked = $this->manager->enforceMaxActiveSessions($this->user, 'current_oldest');

        // excess = 3 - 2 = 1 → only the oldest OTHER session is pruned
        $this->assertSame(1, $revoked);

        $remaining = DB::table('sessions')->where('user_id', $this->user->id)->pluck('id')->all();
        $this->assertContains('current_oldest', $remaining);
        $this->assertContains('other_b', $remaining);
        $this->assertNotContains('other_a', $remaining);
    }

    public function test_disabled_feature_is_noop(): void
    {
        config(['authentication.features.session_management.enabled' => false]);

        $now = time();
        $this->insertSession('sess_x', $now - 1000);
        $this->insertSession('sess_y', $now - 500);
        $this->insertSession('sess_z', $now - 100);

        $revoked = $this->manager->enforceMaxActiveSessions($this->user, 'sess_z');

        $this->assertSame(0, $revoked);
        $this->assertSame(3, DB::table('sessions')->where('user_id', $this->user->id)->count());
    }
}