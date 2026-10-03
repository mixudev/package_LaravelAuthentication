<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class SessionDestroyRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'name'     => 'Session User',
            'username' => 'sessionuser',
            'email'    => 'session-user@example.com',
            'password' => Hash::make('SecretPass123!'),
        ]);
    }

    public function test_destroy_session_is_rate_limited_to_prevent_enumeration(): void
    {
        $user = User::where('email', 'session-user@example.com')->first();

        $this->actingAs($user);

        $throttled = false;
        $throttledStatus = null;

        for ($i = 0; $i < 40; $i++) {
            $response = $this->withHeaders(['Accept' => 'application/json'])
                ->deleteJson('/api/v1/auth/sessions/999999999');

            $status = $response->status();
            if ($status === 429) {
                $throttled = true;
                $throttledStatus = $status;
                break;
            }
        }

        $this->assertTrue($throttled, 'SessionController::destroy not rate limited — session ID enumeration / brute deletion.');
    }
}
