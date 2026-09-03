<?php
require 'D:/WEBSITE/PACKAGE/LaravelAuthentication/vendor/autoload.php';
use Vendor\LaravelAuthentication\Support\QrCodeGenerator;

$base32 = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'; // 32-char base32
$issuer = 'MyApplication';
$account = 'alice@example.com';
$url = sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
    rawurlencode($issuer), rawurlencode($account), $base32, rawurlencode($issuer));
echo "URL=$url\n";
$png = QrCodeGenerator::pngDataUri($url, 220, 4);
$raw = base64_decode(substr($png, strlen('data:image/png;base64,')));
file_put_contents('D:/WEBSITE/PACKAGE/LaravelAuthentication/tests/_qrlong.png', $raw);
echo "WROTE " . strlen($raw) . " bytes\n";
