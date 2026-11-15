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

        if (!function_exists('imagepng')) {
            $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
            return;
        }

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $png = base64_decode(substr($uri, strlen('data:image/png;base64,')));
        $this->assertSame("\x89PNG
\n\x1a\n", substr($png, 0, 8));

        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_test_' . uniqid('', true) . '.png';
        try {
            file_put_contents($tempFile, $png);
            $this->assertFileExists($tempFile);
            $this->assertGreaterThan(0, filesize($tempFile));
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public function test_svg_fallback_still_valid(): void
    {
        $svg = QrCodeGenerator::svg('otpauth://totp/Laravel:user@example.com?secret=NE7U3LRFJNSFMFJD', 220, 4);
        $this->assertStringContainsString('<svg', $svg);
    }
}
