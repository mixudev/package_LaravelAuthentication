<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class StrategyProbingTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Dan Probe',
            'username' => 'danprobe',
            'email'    => 'dan@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
    }

    public function test_rejects_unregistered_or_invalid_strategy_without_internal_leakage(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'dan@example.com',
            'password'   => 'Secret123!',
            'strategy'   => 'non_existent_exploit_strategy',
        ]);

        // Validation error (422) or invalid credentials (401)
        $this->assertTrue(in_array($response->status(), [422, 401], true));
        $content = (string) $response->getContent();

        // Must not leak internal namespace or class paths
        $this->assertStringNotContainsString('InvalidStrategyException', $content);
        $this->assertStringNotContainsString('Vendor\\LaravelAuthentication', $content);
    }
}
