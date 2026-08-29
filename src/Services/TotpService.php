<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use SensitiveParameter;

/**
 * Pure PHP implementation of RFC 6238 TOTP (Time-Based One-Time Password Algorithm)
 * and RFC 4648 Base32 encoding without external dependencies.
 */
class TotpService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure random Base32 secret.
     */
    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        $safeLength = max(1, $length);
        $randomBytes = random_bytes($safeLength);

        for ($i = 0; $i < $safeLength; $i++) {
            $secret .= self::BASE32_CHARS[ord($randomBytes[$i]) & 31];
        }

        return $secret;
    }

    /**
     * Calculate current TOTP code for a given secret.
     */
    public function calculateCode(#[\SensitiveParameter] string $secret, ?int $timestamp = null, int $digits = 6, int $period = 30): string
    {
        $time = $timestamp ?? time();
        $timeCounter = (int) floor($time / $period);

        $binaryCounter = pack('N*', 0) . pack('N*', $timeCounter);
        $binaryKey = $this->base32Decode($secret);

        $hash = hash_hmac('sha1', $binaryCounter, $binaryKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $unpacked = unpack('N', substr($hash, $offset, 4));
        $value = $unpacked ? ($unpacked[1] & 0x7FFFFFFF) : 0;

        $modulo = 10 ** $digits;
        $code = (string) ($value % $modulo);

        return str_pad($code, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a submitted TOTP code with time drift window tolerance.
     */
    public function verify(#[\SensitiveParameter] string $secret, string $code, int $window = 1, int $digits = 6, int $period = 30): bool
    {
        $code = trim($code);

        if (strlen($code) !== $digits || !ctype_digit($code)) {
            return false;
        }

        $currentTime = time();

        for ($drift = -$window; $drift <= $window; $drift++) {
            $timestamp = $currentTime + ($drift * $period);
            $expectedCode = $this->calculateCode($secret, $timestamp, $digits, $period);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate the standard otpauth:// URI.
     */
    public function getOtpAuthUrl(string $issuer, string $accountName, #[\SensitiveParameter] string $secret, int $digits = 6, int $period = 30): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $encodedIssuer,
            $encodedAccount,
            $secret,
            $encodedIssuer,
            $digits,
            $period
        );
    }

    /**
     * Generate a reliable scannable QR Code image URL (inline SVG Data URI) locally without external network calls.
     */
    public function getQrCodeUrl(string $otpAuthUrl, int $size = 220): string
    {
        return \Vendor\LaravelAuthentication\Support\QrCodeGenerator::dataUri($otpAuthUrl, $size);
    }

    /**
     * Generate raw SVG string for inline embedding.
     */
    public function getQrCodeSvg(string $otpAuthUrl, int $size = 220): string
    {
        return \Vendor\LaravelAuthentication\Support\QrCodeGenerator::svg($otpAuthUrl, $size);
    }

    /**
     * Base32 decoding helper compliant with RFC 4648.
     */
    protected function base32Decode(#[\SensitiveParameter] string $base32): string
    {
        $base32 = strtoupper(trim($base32));
        $buffer = 0;
        $bufferBits = 0;
        $result = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $position = strpos(self::BASE32_CHARS, $char);

            if ($position === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $position;
            $bufferBits += 5;

            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $result .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }

        return $result;
    }
}
