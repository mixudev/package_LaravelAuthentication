<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use Vendor\LaravelAuthentication\Support\AuthenticationView;
use Vendor\LaravelAuthentication\Tests\TestCase;

class AuthenticationViewTest extends TestCase
{
    public function test_stale_published_view_config_falls_back_to_canonical_view(): void
    {
        config(['authentication.views.login' => 'authentication::login']);

        $this->assertSame(
            'authentication::pages.auth.login',
            AuthenticationView::resolve('login', 'authentication::pages.auth.login')
        );
    }

    public function test_existing_custom_view_config_remains_authoritative(): void
    {
        config(['authentication.views.login' => 'authentication::pages.auth.login']);

        $this->assertSame(
            'authentication::pages.auth.login',
            AuthenticationView::resolve('login', 'authentication::pages.auth.login')
        );
    }
}
