<?php
/**
 * Test OTP Email Delivery Directly
 * 
 * Jalankan di aplikasi Laravel yang pakai package:
 * php test-otp-direct.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== OTP EMAIL DELIVERY TEST ===\n\n";

// 1. Check config
echo "1. CONFIG CHECK:\n";
echo "   - OTP enabled: " . var_export(config('authentication.features.otp.enabled'), true) . "\n";
echo "   - Send email: " . var_export(config('authentication.features.otp.send_email'), true) . "\n";
echo "   - Mail queue: " . var_export(config('authentication.mail.queue'), true) . "\n";
echo "   - Mail mailer: " . config('mail.default') . "\n\n";

// 2. Test dengan email yang terdaftar
echo "2. MASUKKAN EMAIL ANDA YANG TERDAFTAR:\n";
echo "   Email: ";
$email = trim(fgets(STDIN));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("   [ERROR] Email tidak valid!\n");
}

echo "\n3. LOOKUP USER:\n";
$userModel = config('authentication.user_model');
$emailColumn = config('authentication.login.identifiers.email_column', 'email');

$user = $userModel::where($emailColumn, $email)->first();
echo "   - User found: " . ($user ? 'YES (ID: '.$user->id.')' : 'NO') . "\n";
echo "   - Email column: {$emailColumn}\n";
echo "   - Normalized: " . \Vendor\LaravelAuthentication\Support\Normalizers\EmailNormalizer::normalize($email) . "\n\n";

// 3. Generate OTP dengan logging
echo "4. GENERATING OTP...\n";
$otpService = app(\Vendor\LaravelAuthentication\Contracts\OtpServiceInterface::class);
$context = new \Vendor\LaravelAuthentication\DTO\AuthenticationContext(
    ipAddress: '127.0.0.1',
    userAgent: 'OTP-Test-Script'
);

try {
    $code = $otpService->generate($email, $context);
    echo "   [SUCCESS] OTP generated: {$code}\n";
    echo "   [INFO] Check storage/logs/laravel.log for email dispatch status\n\n";
    
    echo "5. CHECK LOG NOW:\n";
    echo "   tail -30 storage/logs/laravel.log | grep -E 'OTP email|recipient'\n\n";
    
    echo "   Expected log entries:\n";
    echo "   - 'OTP email queued' OR 'OTP email sent synchronously'\n";
    echo "   - 'recipient' => '{$email}'\n\n";
    
    echo "6. CEK EMAIL INBOX ANDA (termasuk spam folder)\n";
    echo "   Subject: " . config('app.name') . " — Kode Verifikasi Masuk\n";
    
} catch (\Throwable $e) {
    echo "   [ERROR] " . $e->getMessage() . "\n";
    echo "   Class: " . get_class($e) . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}

echo "\n=== TEST SELESAI ===\n";
