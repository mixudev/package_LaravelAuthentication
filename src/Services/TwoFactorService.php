<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;
use SensitiveParameter;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Models\TwoFactorAuthentication;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class TwoFactorService
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly AuthenticationConfig $config,
        private readonly Hasher $hasher
    ) {}

    public function isEnabledFor(Authenticatable $user): bool
    {
        if (!$this->config->isTwoFactorEnabled()) {
            return false;
        }

        $userId = $user->getAuthIdentifier();
        /** @var TwoFactorAuthentication|null $twoFactor */
        $twoFactor = TwoFactorAuthentication::where('user_id', $userId)->first();

        return $twoFactor !== null && $twoFactor->isConfirmed();
    }

    /**
     * Start the 2FA setup process for a user.
     *
     * @return array{secret: string, otpauth_url: string, qr_code_url: string, qr_code_svg: string, recovery_codes: array<string>}
     */
    public function setup(Authenticatable $user): array
    {
        $userId = $user->getAuthIdentifier();
        $accountName = (string) ($user->email ?? $user->username ?? $userId);
        $issuer = $this->config->getTwoFactorIssuer();

        /** @var TwoFactorAuthentication|null $record */
        $record = TwoFactorAuthentication::where('user_id', $userId)->first();

        if ($record === null) {
            $secret = $this->totp->generateSecret(16);
            $recoveryCodes = $this->generateRecoveryCodes($this->config->getTwoFactorBackupCodesCount());

            TwoFactorAuthentication::create([
                'user_id'        => $userId,
                'secret'         => $secret,
                'recovery_codes' => $recoveryCodes,
                'confirmed_at'   => null,
            ]);
        } elseif (!$record->isConfirmed()) {
            $secret = $record->secret ?: $this->totp->generateSecret(16);
            $recoveryCodes = !empty($record->recovery_codes) ? $record->recovery_codes : $this->generateRecoveryCodes($this->config->getTwoFactorBackupCodesCount());

            $record->update([
                'secret'         => $secret,
                'recovery_codes' => $recoveryCodes,
            ]);
        } else {
            $secret = $record->secret;
            $recoveryCodes = $record->recovery_codes ?? [];
        }

        $otpAuthUrl = $this->totp->getOtpAuthUrl(
            $issuer,
            $accountName,
            $secret,
            $this->config->getTwoFactorDigits(),
            $this->config->getTwoFactorPeriod()
        );

        $qrCodeUrl = $this->totp->getQrCodeUrl($otpAuthUrl, 220);
        $qrCodeSvg = $this->totp->getQrCodeSvg($otpAuthUrl, 220);

        return [
            'secret'         => $secret,
            'otpauth_url'    => $otpAuthUrl,
            'qr_code_url'    => $qrCodeUrl,
            'qr_code_svg'    => $qrCodeSvg,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Confirm initial TOTP setup with code.
     */
    public function confirm(Authenticatable $user, string $code): bool
    {
        $userId = $user->getAuthIdentifier();
        /** @var TwoFactorAuthentication|null $record */
        $record = TwoFactorAuthentication::where('user_id', $userId)->first();

        if (!$record) {
            return false;
        }

        $isValid = $this->totp->verify(
            $record->secret,
            $code,
            $this->config->getTwoFactorWindow(),
            $this->config->getTwoFactorDigits(),
            $this->config->getTwoFactorPeriod()
        );

        if ($isValid) {
            $record->update(['confirmed_at' => now()]);
            return true;
        }

        return false;
    }

    /**
     * Disable 2FA after checking password.
     */
    public function disable(Authenticatable $user, #[SensitiveParameter] string $password): bool
    {
        $passwordColumn = $this->config->getIdentifierColumn('password');
        $userHash = (string) ($user->{$passwordColumn} ?? '');

        if (!$this->hasher->check($password, $userHash)) {
            throw new InvalidCredentialsException(__('authentication::messages.invalid_password'));
        }

        $userId = $user->getAuthIdentifier();
        return (bool) TwoFactorAuthentication::where('user_id', $userId)->delete();
    }

    /**
     * Verify challenge code during login (either TOTP or one-time recovery code).
     */
    public function verifyChallenge(Authenticatable $user, string $code): bool
    {
        $userId = $user->getAuthIdentifier();
        /** @var TwoFactorAuthentication|null $record */
        $record = TwoFactorAuthentication::where('user_id', $userId)->first();

        if (!$record || !$record->isConfirmed()) {
            return false;
        }

        // 1. Try TOTP code first
        if ($this->totp->verify(
            $record->secret,
            $code,
            $this->config->getTwoFactorWindow(),
            $this->config->getTwoFactorDigits(),
            $this->config->getTwoFactorPeriod()
        )) {
            return true;
        }

        // 2. Try Recovery Code
        $cleanCode = str_replace('-', '', trim($code));
        $recoveryCodes = $record->recovery_codes ?? [];

        foreach ($recoveryCodes as $index => $storedCode) {
            $cleanStored = str_replace('-', '', trim($storedCode));
            if (hash_equals($cleanStored, $cleanCode)) {
                // Consume recovery code (single use)
                unset($recoveryCodes[$index]);
                $record->update(['recovery_codes' => array_values($recoveryCodes)]);
                return true;
            }
        }

        return false;
    }

    /**
     * Generate list of formatted recovery codes (e.g. "ABCD-1234").
     *
     * @return array<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $part1 = strtoupper(Str::random(4));
            $part2 = strtoupper(Str::random(4));
            $codes[] = "{$part1}-{$part2}";
        }

        return $codes;
    }
}
