<?php
require __DIR__ . '/../vendor/autoload.php';
use Vendor\LaravelAuthentication\Support\QrCodeGenerator;

$base32 = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'; // 32-char base32
$issuer = 'MyApplication';
$account = 'alice@example.com';
$url = sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
    rawurlencode($issuer), rawurlencode($account), $base32, rawurlencode($issuer));
echo "URL=$url\n";
$png = QrCodeGenerator::pngDataUri($url, 220, 4);
$raw = base64_decode(substr($png, strlen('data:image/png;base64,')));
$tempOut = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '_qrlong.png';
file_put_contents($tempOut, $raw);
echo "WROTE " . strlen($raw) . " bytes to $tempOut\n";
