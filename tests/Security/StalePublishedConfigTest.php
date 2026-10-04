<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * A host that published `config/authentication.php` before the view tree was
 * grouped still names the removed flat aliases. Every GET page used to answer
 * with `{"message":"Please authenticate via POST."}` because the controller
 * fell back to JSON when `view()->exists()` failed.
 */
class StalePublishedConfigTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function stalePages(): array
    {
        return [
            'login' => ['/login', 'authentication::login'],
            'register' => ['/register', 'authentication::register'],
            'forgot-password' => ['/forgot-password', 'authentication::forgot-password'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stalePages')]
    public function test_get_page_renders_html_even_when_published_config_is_stale(string $uri, string $staleView): void
    {
        config(['authentication.views.login' => 'authentication::login']);
        config(['authentication.views.register' => 'authentication::register']);
        config(['authentication.views.forgot_password' => 'authentication::forgot-password']);
        config(['authentication.views.reset_password' => 'authentication::reset-password']);
        config(['authentication.views.otp_request' => 'authentication::otp-request']);
        config(['authentication.views.otp_verify' => 'authentication::otp-verify']);

        $response = $this->get($uri);

        $response->assertOk();

        $this->assertStringContainsString(
            '<!DOCTYPE html>',
            $response->getContent(),
            "GET {$uri} did not render HTML with the stale config value [{$staleView}]."
        );

        $this->assertStringNotContainsString(
            'Please authenticate via POST',
            $response->getContent(),
            "GET {$uri} fell back to the API JSON message."
        );
    }
}