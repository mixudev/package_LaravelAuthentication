<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use PHPUnit\Framework\Attributes\Test;
use Vendor\LaravelAuthentication\Tests\TestCase;

final class AuthenticationConfigSecurityPostureTest extends TestCase
{
    #[Test]
    public function the_single_package_config_has_hardened_defaults(): void
    {
        /** @var array<string, mixed> $config */
        $config = require __DIR__ . '/../../config/authentication.php';
        $rules = $config['password']['validation_rules'];

        $this->assertSame(12, $rules['min_length']);
        $this->assertTrue($rules['require_uppercase']);
        $this->assertTrue($rules['require_lowercase']);
        $this->assertTrue($rules['require_numbers']);
        $this->assertTrue($rules['require_symbols']);
        $this->assertTrue($config['security']['account_lockout']['enabled']);
        $this->assertTrue($config['security']['captcha']['enabled']);
        $this->assertFalse($config['features']['registration']['auto_login_on_register']);
        $this->assertTrue($config['features']['registration']['require_email_verify']);
        $this->assertFalse($config['features']['social']['auto_register']);
    }
}
