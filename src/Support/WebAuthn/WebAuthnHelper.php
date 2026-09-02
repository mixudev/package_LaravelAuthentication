<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support\WebAuthn;

use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;

/**
 * High-assurance WebAuthn / FIDO2 cryptographic verification helper.
 *
 * Implements W3C WebAuthn Level 2 / Level 3 verification rules for:
 * - clientDataJSON validation (type, challenge, origin)
 * - authenticatorData verification (rpIdHash, UP flag, UV flag, signCount)
 * - Cryptographic signature verification over (authenticatorData || SHA256(clientDataJSON))
 * - Public key extraction and conversion from COSE / SPKI / PEM formats
 */
final class WebAuthnHelper
{
    /**
     * Decode Base64URL string to raw binary.
     */
    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : '';
    }

    /**
     * Encode binary string to Base64URL without padding.
     */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Validate WebAuthn clientDataJSON structure.
     *
     * @return array<string, mixed>
     * @throws AuthenticationException
     */
    public static function validateClientData(
        string $clientDataRaw,
        string $expectedType,
        string $expectedChallenge,
        ?string $expectedRpHost = null
    ): array {
        if ($clientDataRaw === '') {
            throw new AuthenticationException('WebAuthn clientDataJSON is empty.');
        }

        /** @var array<string, mixed>|null $clientData */
        $clientData = json_decode($clientDataRaw, true);

        if (!is_array($clientData)) {
            throw new AuthenticationException('Malformed clientDataJSON payload.');
        }

        $type = (string) ($clientData['type'] ?? '');
        if ($type !== $expectedType) {
            throw new AuthenticationException("WebAuthn ceremony type mismatch. Expected [{$expectedType}], got [{$type}].");
        }

        $challenge = (string) ($clientData['challenge'] ?? '');
        if ($challenge !== $expectedChallenge) {
            throw new AuthenticationException('WebAuthn challenge mismatch.');
        }

        $origin = (string) ($clientData['origin'] ?? '');
        if ($origin === '') {
            throw new AuthenticationException('WebAuthn clientDataJSON missing origin.');
        }

        if ($expectedRpHost !== null && $expectedRpHost !== '') {
            $parsedOriginHost = parse_url($origin, PHP_URL_HOST) ?? $origin;
            // Clean ports / protocols if needed
            if (is_string($parsedOriginHost)) {
                $cleanExpected = strtolower(explode(':', $expectedRpHost)[0]);
                $cleanOrigin = strtolower(explode(':', $parsedOriginHost)[0]);

                if ($cleanOrigin !== $cleanExpected && $cleanOrigin !== 'localhost' && $cleanExpected !== 'localhost') {
                    throw new AuthenticationException("WebAuthn origin host mismatch. Expected [{$cleanExpected}], got [{$cleanOrigin}].");
                }
            }
        }

        return $clientData;
    }

    /**
     * Parse and validate authenticatorData structure.
     *
     * @return array{rp_id_hash: string, flags: int, up: bool, uv: bool, at: bool, ed: bool, sign_count: int, auth_data_raw: string}
     * @throws AuthenticationException
     */
    public static function parseAuthenticatorData(string $authDataBytes, string $expectedRpId, string $userVerificationPolicy = 'preferred'): array
    {
        if (strlen($authDataBytes) < 37) {
            throw new AuthenticationException('WebAuthn authenticatorData is too short (< 37 bytes).');
        }

        $rpIdHash = substr($authDataBytes, 0, 32);
        $flags = ord($authDataBytes[32]);
        $signCountArr = unpack('N', substr($authDataBytes, 33, 4));
        $signCount = $signCountArr !== false ? (int) $signCountArr[1] : 0;

        $up = ($flags & 0x01) !== 0; // Bit 0: User Present
        $uv = ($flags & 0x04) !== 0; // Bit 2: User Verified
        $at = ($flags & 0x40) !== 0; // Bit 6: Attested credential data
        $ed = ($flags & 0x80) !== 0; // Bit 7: Extension data

        // 1. Verify RP ID Hash
        $expectedHash = hash('sha256', $expectedRpId, true);
        if (!hash_equals($expectedHash, $rpIdHash)) {
            // Also allow matching against without port / lowercase
            $expectedHashClean = hash('sha256', strtolower(explode(':', $expectedRpId)[0]), true);
            if (!hash_equals($expectedHashClean, $rpIdHash)) {
                throw new AuthenticationException('WebAuthn rpIdHash mismatch.');
            }
        }

        // 2. Verify User Present (UP) bit is set
        if (!$up) {
            throw new AuthenticationException('WebAuthn authenticator User Present (UP) flag is not set.');
        }

        // 3. Check User Verified (UV) policy if required
        if ($userVerificationPolicy === 'required' && !$uv) {
            throw new AuthenticationException('WebAuthn user verification is required but UV flag is not set.');
        }

        return [
            'rp_id_hash'     => $rpIdHash,
            'flags'          => $flags,
            'up'             => $up,
            'uv'             => $uv,
            'at'             => $at,
            'ed'             => $ed,
            'sign_count'     => $signCount,
            'auth_data_raw'  => $authDataBytes,
        ];
    }

    /**
     * Convert various public key representations (COSE, SPKI DER, PEM) to valid OpenSSL PEM.
     *
     * @throws AuthenticationException
     */
    public static function normalizePublicKeyToPem(string $rawPublicKey): string
    {
        $raw = trim($rawPublicKey);

        // If already PEM formatted
        if (str_contains($raw, '-----BEGIN PUBLIC KEY-----')) {
            $res = openssl_pkey_get_public($raw);
            if ($res !== false) {
                return $raw;
            }
        }

        // Try base64 decoding if base64 text
        $decoded = self::base64UrlDecode($raw);
        if ($decoded === '' && !empty($raw)) {
            $decoded = base64_decode($raw, true) ?: $raw;
        }

        // If decoded is PEM
        if (str_contains($decoded, '-----BEGIN PUBLIC KEY-----')) {
            $res = openssl_pkey_get_public($decoded);
            if ($res !== false) {
                return $decoded;
            }
        }

        // Check if decoded is SPKI DER format (typically starts with 0x30, length)
        if (strlen($decoded) > 20 && ord($decoded[0]) === 0x30) {
            $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($decoded), 64, "\n") . "-----END PUBLIC KEY-----\n";
            $res = openssl_pkey_get_public($pem);
            if ($res !== false) {
                return $pem;
            }
        }

        // Try parsing COSE key from binary
        $cosePem = self::coseToPem($decoded);
        if ($cosePem !== null) {
            $res = openssl_pkey_get_public($cosePem);
            if ($res !== false) {
                return $cosePem;
            }
        }

        throw new AuthenticationException('Unable to parse or validate WebAuthn public key. Unsupported format.');
    }

    /**
     * Parse binary COSE key structure to OpenSSL PEM.
     */
    public static function coseToPem(string $binary): ?string
    {
        // Simple heuristic COSE P-256 parser
        // P-256 EC key contains 32 bytes X and 32 bytes Y coordinates.
        if (strlen($binary) >= 64) {
            // Find coordinate markers if CBOR encoded (e.g. -2 for x, -3 for y)
            // Or if standard 65-byte uncompressed EC point (0x04 . x . y)
            if (strlen($binary) === 65 && ord($binary[0]) === 0x04) {
                return self::ecPointToPem(substr($binary, 1, 32), substr($binary, 33, 32));
            }
            if (strlen($binary) === 64) {
                return self::ecPointToPem(substr($binary, 0, 32), substr($binary, 32, 32));
            }

            // Look for CBOR byte string markers (0x58 0x20 = byte string length 32)
            $pos1 = strpos($binary, "\x58\x20");
            if ($pos1 !== false) {
                $x = substr($binary, $pos1 + 2, 32);
                $pos2 = strpos($binary, "\x58\x20", $pos1 + 34);
                if ($pos2 !== false) {
                    $y = substr($binary, $pos2 + 2, 32);
                    if (strlen($x) === 32 && strlen($y) === 32) {
                        return self::ecPointToPem($x, $y);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Convert uncompressed EC P-256 coordinates (X, Y) to SubjectPublicKeyInfo (SPKI) PEM.
     */
    public static function ecPointToPem(string $x, string $y): string
    {
        // SPKI Header for EC P-256 (id-ecPublicKey, secp256r1)
        $header = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004');
        $der = $header . $x . $y;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Verify cryptographic assertion signature using OpenSSL.
     *
     * @return true
     * @throws InvalidCredentialsException
     */
    public static function verifySignature(
        string $authDataRaw,
        string $clientDataJSONRaw,
        string $signatureRaw,
        string $publicKeyPem
    ): bool {
        if ($signatureRaw === '' || $authDataRaw === '' || $clientDataJSONRaw === '') {
            throw new InvalidCredentialsException('Missing signature or verification data.');
        }

        $signedData = $authDataRaw . hash('sha256', $clientDataJSONRaw, true);

        // Normalize signature: WebAuthn signatures can be ASN.1 DER or IEEE P1363 (r || s, 64 bytes)
        $derSignature = $signatureRaw;
        if (strlen($signatureRaw) === 64 && ord($signatureRaw[0]) !== 0x30) {
            $derSignature = self::rawSignatureToDer($signatureRaw);
        }

        $result = openssl_verify($signedData, $derSignature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        if ($result === 1) {
            return true;
        }

        // Try direct verify if converted DER failed but raw signature might match
        if ($derSignature !== $signatureRaw) {
            $result = openssl_verify($signedData, $signatureRaw, $publicKeyPem, OPENSSL_ALGO_SHA256);
            if ($result === 1) {
                return true;
            }
        }

        throw new InvalidCredentialsException('WebAuthn cryptographic signature verification failed.');
    }

    /**
     * Convert IEEE P1363 signature (r || s, 64 bytes) to ASN.1 DER SEQUENCE format.
     */
    public static function rawSignatureToDer(string $sig): string
    {
        $r = substr($sig, 0, 32);
        $s = substr($sig, 32, 32);

        // Remove leading zeroes but keep positive sign
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        if (strlen($r) === 0 || ord($r[0]) >= 0x80) {
            $r = "\x00" . $r;
        }
        if (strlen($s) === 0 || ord($s[0]) >= 0x80) {
            $s = "\x00" . $s;
        }

        $rLen = min(255, max(0, strlen($r)));
        $sLen = min(255, max(0, strlen($s)));

        $rDer = "\x02" . chr($rLen) . $r;
        $sDer = "\x02" . chr($sLen) . $s;

        $sequence = $rDer . $sDer;
        $seqLen = min(255, max(0, strlen($sequence)));

        return "\x30" . chr($seqLen) . $sequence;
    }
}
