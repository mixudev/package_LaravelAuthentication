<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Performance;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Services\CredentialResolver;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class MassiveScaleQueryOptimizationTest extends TestCase
{
    private CredentialResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(CredentialResolver::class);
    }

    public function test_resolves_user_via_email_fast_path(): void
    {
        $user = User::create([
            'name'     => 'Speedy User',
            'username' => 'speedy',
            'email'    => 'speedy@highscale.com',
            'password' => Hash::make('ScalePass123!'),
        ]);

        $resolved = $this->resolver->resolveByColumns(['email', 'username'], 'speedy@highscale.com');

        $this->assertNotNull($resolved);
        $this->assertEquals($user->id, $resolved->getAuthIdentifier());
    }

    public function test_resolves_user_via_username_fast_path(): void
    {
        $user = User::create([
            'name'     => 'Speedy Username',
            'username' => 'speedy_username',
            'email'    => 'user_opt@highscale.com',
            'password' => Hash::make('ScalePass123!'),
        ]);

        $resolved = $this->resolver->resolveByColumns(['email', 'username'], 'speedy_username');

        $this->assertNotNull($resolved);
        $this->assertEquals($user->id, $resolved->getAuthIdentifier());
    }
}
