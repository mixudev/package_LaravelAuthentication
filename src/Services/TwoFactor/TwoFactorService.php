<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services\TwoFactor;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;
use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\Enums\SecurityEventType;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Models\TwoFactorAuthentication;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

class TwoFactorService
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly AuthenticationConfig $config,
        private readonly Hasher $hasher,
        private readonly AuditLoggerInterface $auditService
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
            $plainRecoveryCodes = $this->generateRecoveryCodes($this->config->getTwoFactorBackupCodesCount());
            $hashedRecoveryCodes = array_map(fn(string $code) => $this->hasher->make(str_replace(['-', ' '], '', trim($code))), $plainRecoveryCodes);

            TwoFactorAuthentication::create([
                'user_id'        => $userId,
                'secret'         => $secret,
                'recovery_codes' => $hashedRecoveryCodes,
                'confirmed_at'   => null,
            ]);
        } elseif (!$record->isConfirmed()) {
            $secret = $record->secret ?: $this->totp->generateSecret(16);
            $plainRecoveryCodes = $this->generateRecoveryCodes($this->config->getTwoFactorBackupCodesCount());
            $hashedRecoveryCodes = array_map(fn(string $code) => $this->hasher->make(str_replace(['-', ' '], '', trim($code))), $plainRecoveryCodes);

            $record->update([
                'secret'         => $secret,
                'recovery_codes' => $hashedRecoveryCodes,
            ]);
        } else {
            $secret = $record->secret;
            $plainRecoveryCodes = [];
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
            'recovery_codes' => $plainRecoveryCodes,
        ];
    }

    /**
     * Confirm initial TOTP setup with code.
     */
    public function confirm(Authenticatable $user, #[SensitiveParameter] string $code): bool
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
                    $record->update(['confirmed_at' => now(), 'last_used_timestep' => null]);
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
            $msg = __('authentication::messages.invalid_password');
            throw new InvalidCredentialsException(is_string($msg) ? $msg : 'Invalid password.');
        }

        $userId = $user->getAuthIdentifier();
        $deleted = (bool) TwoFactorAuthentication::where('user_id', $userId)->delete();

        if ($deleted) {
            $this->auditService->logEvent(
                SecurityEventType::TWO_FACTOR_DISABLED,
                (string) $userId,
                $this->resolveContext(),
                null,
                ['action' => 'two_factor_disabled']
            );
        }

        return $deleted;
    }

    /**
     * Build an audit context — null-safe for CLI/queue. 2FA is usually
     * toggled from a web request, but a queue/CLI handle must not fatal.
     */
    protected function resolveContext(): \Vendor\LaravelAuthentication\DTO\AuthenticationContext
    {
        $request = request();
        if ($request instanceof \Illuminate\Http\Request) {
            return \Vendor\LaravelAuthentication\DTO\AuthenticationContext::fromRequest($request);
        }

        return new \Vendor\LaravelAuthentication\DTO\AuthenticationContext(
            'cli',
            'cli',
            \Vendor\LaravelAuthentication\Enums\AuthenticationChannel::CLI,
            'web'
        );
    }

    /**
     * Verify challenge code during login (either TOTP or one-time hashed recovery code).
     */
    public function verifyChallenge(Authenticatable $user, #[SensitiveParameter] string $code): bool
    {
        $userId = $user->getAuthIdentifier();

        $cleanInput = str_replace(['-', ' '], '', trim($code));

                // TOTP is a replayable credential: the same code stays valid for the whole
                // drift window. Claim the timestep under a row lock so a code observed once
                // (phishing, malware, shoulder surfing) cannot be redeemed again.
                return DB::transaction(function () use ($userId, $code, $cleanInput): bool {
                    /** @var TwoFactorAuthentication|null $lockedRecord */
                    $lockedRecord = TwoFactorAuthentication::query()
                        ->where('user_id', $userId)
                        ->lockForUpdate()
                        ->first();

                    if (!$lockedRecord || !$lockedRecord->isConfirmed()) {
                        return false;
                    }

                    $timestep = $this->totp->verifyCounter(
                        $lockedRecord->secret,
                        $code,
                        $this->config->getTwoFactorWindow(),
                        $this->config->getTwoFactorDigits(),
                        $this->config->getTwoFactorPeriod()
                    );

                    if ($timestep !== null) {
                        if ((int) $lockedRecord->last_used_timestep >= $timestep) {
                            return false; // Replay of an already-redeemed (or older) timestep.
                        }

                        $lockedRecord->update(['last_used_timestep' => $timestep]);

                        return true;
                    }

                    // Recovery codes are one-time credentials. Lock the row inside a
                    // transaction so two concurrent requests cannot consume the same code.
                    $recoveryCodes = (array) ($lockedRecord->recovery_codes ?? []);

                    foreach ($recoveryCodes as $index => $storedCode) {
                        if (!is_string($storedCode)) {
                            continue;
                        }

                        $isMatch = str_starts_with($storedCode, '$2y$')
                            || str_starts_with($storedCode, '$argon2id$')
                            || str_starts_with($storedCode, '$2a$')
                            ? $this->hasher->check($cleanInput, $storedCode)
                            : hash_equals(str_replace(['-', ' '], '', trim($storedCode)), $cleanInput);

                        if ($isMatch) {
                            unset($recoveryCodes[$index]);
                            $lockedRecord->update(['recovery_codes' => array_values($recoveryCodes)]);
                            return true;
                        }
                    }

                    return false;
                });
            }

    /**
     * Generate list of high-entropy formatted recovery codes (e.g. "ABCDE-12345").
     *
     * @return array<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $part1 = strtoupper(Str::random(5));
            $part2 = strtoupper(Str::random(5));
            $codes[] = "{$part1}-{$part2}";
        }

        return $codes;
    }
}
