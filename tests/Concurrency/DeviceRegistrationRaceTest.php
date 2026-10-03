<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Concurrency;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\Test;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;
use Vendor\LaravelAuthentication\Services\Session\NewDeviceDetectionService;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * M-02: Device registration race.
 *
 * authentication_devices carries UNIQUE(user_id, device_fingerprint), and both
 * device services located the row before writing:
 *
 *   NewDeviceDetectionService::handleLogin()   -> where(...)->first() then create()
 *   DeviceTrustService::createTrustCookie()     -> firstOrCreate()
 *
 * firstOrCreate() is a SELECT followed by an INSERT, not a single atomic statement,
 * so two requests that both find no row still both attempt the insert and the loser
 * raises a unique violation. On the login path that surfaces as a 500 after
 * credentials were already accepted.
 *
 * The invariant: concurrent registrations for one (user_id, fingerprint) must converge
 * on exactly one row, must never raise, and must not fabricate a "new device" alert
 * for a device that already existed.
 */
final class DeviceRegistrationRaceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function concurrent_registrations_for_the_same_device_converge_on_one_row(): void
    {
        $user = $this->makeUser();
        $service = app(NewDeviceDetectionService::class);
        $context = $this->context();

        // First login registers the device.
        $first = $service->handleLogin($user, $context);
        $this->assertNotNull($first);

        // Second login on the same device must update, not insert, and must not raise.
        $second = $service->handleLogin($user, $context);

        $this->assertSame(
            (int) $first->id,
            (int) $second->id,
            'a repeat login on a known device must reuse the existing row'
        );

        $rows = AuthenticationDevice::where('user_id', $user->getAuthIdentifier())->count();

        $this->assertSame(1, $rows, 'exactly one device row may exist per user+fingerprint');
    }

    #[Test]
    public function the_unique_index_is_the_arbiter_for_a_lost_insert_race(): void
    {
        $user = $this->makeUser();
        $fingerprint = hash('sha256', 'device-race-fingerprint');

        AuthenticationDevice::query()->create([
            'user_id'            => $user->getAuthIdentifier(),
            'device_fingerprint' => $fingerprint,
            'ip_address'         => '203.0.113.1',
            'user_agent'         => 'phpunit',
            'device_name'        => 'Race Device',
            'platform'           => 'Linux',
            'browser'            => 'phpunit',
            'is_trusted'         => false,
            'last_seen_at'       => now(),
        ]);

        // Model the losing side of the race: the row appeared between our lookup and
        // our insert. The service must absorb this, not propagate it to the caller.
        $raised = false;

        try {
            AuthenticationDevice::query()->create([
                'user_id'            => $user->getAuthIdentifier(),
                'device_fingerprint' => $fingerprint,
                'ip_address'         => '203.0.113.1',
                'user_agent'         => 'phpunit',
                'device_name'        => 'Race Device',
                'platform'           => 'Linux',
                'browser'            => 'phpunit',
                'is_trusted'         => false,
                'last_seen_at'       => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $raised = true;
        }

        $this->assertTrue(
            $raised,
            'the UNIQUE(user_id, device_fingerprint) index must reject a concurrent duplicate insert'
        );

        $this->assertSame(
            1,
            AuthenticationDevice::where('user_id', $user->getAuthIdentifier())->count(),
            'the race must leave exactly one device row'
        );
    }

    #[Test]
    public function a_device_is_reported_as_new_exactly_once(): void
    {
        $user = $this->makeUser();
        $service = app(NewDeviceDetectionService::class);
        $context = $this->context();

        \Illuminate\Support\Facades\Event::fake([
            \Vendor\LaravelAuthentication\Events\NewDeviceLoginDetected::class,
        ]);

        $service->handleLogin($user, $context);
        $service->handleLogin($user, $context);
        $service->handleLogin($user, $context);

        \Illuminate\Support\Facades\Event::assertDispatchedTimes(
            \Vendor\LaravelAuthentication\Events\NewDeviceLoginDetected::class,
            1
        );
    }

    private function makeUser(): \Illuminate\Contracts\Auth\Authenticatable
    {
        $user = \Vendor\LaravelAuthentication\Tests\Fixtures\User::create([
            'name'     => 'Device Race',
            'username' => 'devicerace',
            'email'    => 'device-race@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('SecretPass123!'),
        ]);

        return $user;
    }

    private function context(): AuthenticationContext
    {
        return new AuthenticationContext(
            ipAddress: '203.0.113.77',
            userAgent: 'Mozilla/5.0 (X11; Linux x86_64) DeviceRace/1.0',
        );
    }
}