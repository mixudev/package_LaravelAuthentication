<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\TwoFactor;

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
     *
     * @param int $length Number of Base32 characters (default 32 = 160 bits entropy)
     *                    RFC 6238 recommends minimum 128 bits for SHA-1 HMAC.
     *                    Base32 encoding: 5 bits per character.
     *                    - 20 chars = 100 bits (absolute minimum)
     *                    - 26 chars = 130 bits (secure)
     *                    - 32 chars = 160 bits (recommended)
     */
    public function generateSecret(int $length = 32): string
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
    public function calculateCode(#[SensitiveParameter] string $secret, ?int $timestamp = null, int $digits = 6, int $period = 30): string
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
    public function verify(#[SensitiveParameter] string $secret, string $code, int $window = 1, int $digits = 6, int $period = 30): bool
    {
        return $this->verifyCounter($secret, $code, $window, $digits, $period) !== null;
    }

    /**
     * Verify a code and return the accepted timestep for replay prevention.
     * The caller must persist the returned counter atomically with its user record.
     */
    public function verifyCounter(#[SensitiveParameter] string $secret, string $code, int $window = 1, int $digits = 6, int $period = 30): ?int
    {
        $code = trim($code);

        if ($period <= 0 || strlen($code) !== $digits || !ctype_digit($code)) {
            return null;
        }

        $currentCounter = intdiv(time(), $period);

        for ($drift = -$window; $drift <= $window; $drift++) {
            $counter = $currentCounter + $drift;
            $expectedCode = $this->calculateCode($secret, $counter * $period, $digits, $period);

            if (hash_equals($expectedCode, $code)) {
                return $counter;
            }
        }

        return null;
    }

    /**
     * Generate the standard otpauth:// URI.
     */
    public function getOtpAuthUrl(string $issuer, string $accountName, #[SensitiveParameter] string $secret, int $digits = 6, int $period = 30): string
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
    protected function base32Decode(#[SensitiveParameter] string $base32): string
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
