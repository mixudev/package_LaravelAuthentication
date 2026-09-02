<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;
use Vendor\LaravelAuthentication\Services\SessionManagerService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class SessionOwnershipTest extends TestCase
{
    private User $userA;
    private User $userB;
    private SessionManagerService $sessionManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::create([
            'name'     => 'User A',
            'username' => 'usera',
            'email'    => 'usera@example.com',
            'password' => Hash::make('PasswordA123!'),
        ]);

        $this->userB = User::create([
            'name'     => 'User B',
            'username' => 'userb',
            'email'    => 'userb@example.com',
            'password' => Hash::make('PasswordB123!'),
        ]);

        $this->sessionManager = app(SessionManagerService::class);
    }

    public function test_user_a_cannot_revoke_user_b_device_or_session(): void
    {
        // 1. Create active device for User B
        $deviceB = AuthenticationDevice::create([
            'user_id'            => $this->userB->id,
            'device_fingerprint' => 'fingerprint_user_b',
            'ip_address'         => '10.0.0.2',
            'user_agent'         => 'Firefox/UserB',
            'is_trusted'         => true,
            'last_seen_at'       => now(),
        ]);

        $this->assertDatabaseHas('authentication_devices', [
            'id'      => $deviceB->id,
            'user_id' => $this->userB->id,
        ]);

        // 2. User A attempts to delete User B's device/session via service
        $revoked = $this->sessionManager->revokeSession($this->userA, (string) $deviceB->id);
        $this->assertFalse($revoked);

        // User B's device must still exist in DB
        $this->assertDatabaseHas('authentication_devices', [
            'id'      => $deviceB->id,
            'user_id' => $this->userB->id,
        ]);

        // 3. User A attempts to delete User B's device via controller API route
        $response = $this->actingAs($this->userA)->deleteJson("/auth/sessions/{$deviceB->id}");
        $this->assertTrue(in_array($response->status(), [200, 404], true));

        // Confirm User B's record is intact
        $this->assertDatabaseHas('authentication_devices', [
            'id'      => $deviceB->id,
            'user_id' => $this->userB->id,
        ]);
    }

    public function test_user_can_successfully_revoke_own_session(): void
    {
        $deviceA = AuthenticationDevice::create([
            'user_id'            => $this->userA->id,
            'device_fingerprint' => 'fingerprint_user_a',
            'ip_address'         => '10.0.0.1',
            'user_agent'         => 'Chrome/UserA',
            'is_trusted'         => true,
            'last_seen_at'       => now(),
        ]);

        $response = $this->actingAs($this->userA)->deleteJson("/auth/sessions/{$deviceA->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('authentication_devices', [
            'id'      => $deviceA->id,
            'user_id' => $this->userA->id,
        ]);
    }
}
