<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use Vendor\LaravelAuthentication\Support\QrCodeGenerator;
use Vendor\LaravelAuthentication\Tests\TestCase;

class QrGeneratorEnvTest extends TestCase
{
    public function test_png_data_uri_is_valid_png(): void
    {
        $uri = QrCodeGenerator::dataUri('otpauth://totp/Laravel:user@example.com?secret=NE7U3LRFJNSFMFJD', 220, 4);

        // Prefer PNG when GD is available
        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $png = base64_decode(substr($uri, strlen('data:image/png;base64,')));
        // PNG signature
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));

        // Write to workspace for external decode verification
        file_put_contents('D:/WEBSITE/PACKAGE/LaravelAuthentication/tests/_qrout.png', $png);
        fwrite(STDERR, "\nQR_PNG_WRITTEN\n");
    }

    public function test_svg_fallback_still_valid(): void
    {
        $svg = QrCodeGenerator::svg('otpauth://totp/Laravel:user@example.com?secret=NE7U3LRFJNSFMFJD', 220, 4);
        $this->assertStringContainsString('<svg', $svg);
    }
}
