<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class TwoFactorEnrollmentPasswordConfirmationTest extends TestCase
{
    public function test_setup_requires_recent_password_confirmation(): void
    {
        $user = User::create([
            'name' => 'Two Factor User',
            'username' => 'twofactoruser',
            'email' => 'twofactor@example.com',
            'password' => Hash::make('CorrectPass123!'),
        ]);

        $this->actingAs($user)
            ->getJson('/auth/two-factor/setup')
            ->assertStatus(423)
            ->assertJsonPath('password_confirmation_required', true);
    }

    public function test_setup_allows_recently_confirmed_web_session(): void
    {
        $user = User::create([
            'name' => 'Confirmed Two Factor User',
            'username' => 'confirmedtwofactor',
            'email' => 'confirmedtwofactor@example.com',
            'password' => Hash::make('CorrectPass123!'),
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/auth/two-factor/setup')
            ->assertOk();
    }
}
