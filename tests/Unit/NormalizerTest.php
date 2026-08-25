<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Vendor\LaravelAuthentication\Support\Normalizers\EmailNormalizer;
use Vendor\LaravelAuthentication\Support\Normalizers\IdentifierNormalizer;
use Vendor\LaravelAuthentication\Support\Normalizers\UsernameNormalizer;

class NormalizerTest extends BaseTestCase
{
    public function test_email_normalizer_trims_and_lowercases(): void
    {
        $input = "  User.Name@Example.COM  \n";
        $normalized = EmailNormalizer::normalize($input);

        $this->assertEquals('user.name@example.com', $normalized);
    }

    public function test_username_normalizer_trims_whitespace(): void
    {
        $input = "  Admin_User_01   ";
        $normalized = UsernameNormalizer::normalize($input, lowercase: false);

        $this->assertEquals('Admin_User_01', $normalized);
    }

    public function test_identifier_normalizer_identifies_email_correctly(): void
    {
        $identity = IdentifierNormalizer::resolve('  JOHN.DOE@GMAIL.COM  ');

        $this->assertTrue($identity->isEmail());
        $this->assertFalse($identity->isUsername());
        $this->assertEquals('john.doe@gmail.com', $identity->normalized);
    }

    public function test_identifier_normalizer_identifies_username_correctly(): void
    {
        $identity = IdentifierNormalizer::resolve('  john_doe_99  ');

        $this->assertTrue($identity->isUsername());
        $this->assertFalse($identity->isEmail());
        $this->assertEquals('john_doe_99', $identity->normalized);
    }
}
