<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Models\TwoFactorAuthentication;
use Vendor\LaravelAuthentication\Services\TwoFactorService;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

class RecoveryCodeSecurityTest extends TestCase
{
    private User $user;
    private TwoFactorService $twoFactorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Charlie 2FA',
            'username' => 'charlie',
            'email'    => 'charlie@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $this->twoFactorService = app(TwoFactorService::class);
    }

    public function test_recovery_codes_are_stored_hashed_in_database(): void
    {
        $setup = $this->twoFactorService->setup($this->user);
        $plainCodes = $setup['recovery_codes'];

        $this->assertNotEmpty($plainCodes);
        $this->assertCount(8, $plainCodes);

        // Fetch direct database record
        $record = TwoFactorAuthentication::where('user_id', $this->user->id)->first();
        $this->assertNotNull($record);

        $storedCodes = $record->recovery_codes;
        $this->assertIsArray($storedCodes);
        $this->assertCount(8, $storedCodes);

        // Verify stored codes are hashed and NOT plaintext
        foreach ($storedCodes as $storedCode) {
            $this->assertIsString($storedCode);
            // Hashes start with $2y$ (Bcrypt) or $argon2id$
            $isHashed = str_starts_with($storedCode, '$2y$') || str_starts_with($storedCode, '$argon2id$');
            $this->assertTrue($isHashed, "Stored code [{$storedCode}] must be hashed in DB.");
        }

        // Confirm 2FA setup with valid TOTP code
        $validTotp = app(\Vendor\LaravelAuthentication\Services\TotpService::class)->calculateCode($setup['secret']);
        $confirmed = $this->twoFactorService->confirm($this->user, $validTotp);
        $this->assertTrue($confirmed);

        // Verify challenge using plain recovery code
        $testCode = $plainCodes[0];
        $verified = $this->twoFactorService->verifyChallenge($this->user, $testCode);
        $this->assertTrue($verified);

        // Code must be consumed (single use)
        $recordAfter = TwoFactorAuthentication::where('user_id', $this->user->id)->first();
        $this->assertNotNull($recordAfter);
        $this->assertCount(7, $recordAfter->recovery_codes);

        // Reusing same code must fail
        $reused = $this->twoFactorService->verifyChallenge($this->user, $testCode);
        $this->assertFalse($reused);
    }

    public function test_backward_compatibility_with_legacy_plaintext_codes(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        // Mock legacy database record with plaintext recovery codes
        $record = TwoFactorAuthentication::create([
            'user_id'        => $this->user->id,
            'secret'         => $secret,
            'recovery_codes' => ['LEGACY-1234', 'LEGACY-5678'],
            'confirmed_at'   => now(),
        ]);

        // Legacy code should match and verify
        $verified = $this->twoFactorService->verifyChallenge($this->user, 'LEGACY-1234');
        $this->assertTrue($verified);

        // Consumed after use
        $record->refresh();
        $this->assertCount(1, $record->recovery_codes);
        $this->assertEquals('LEGACY-5678', $record->recovery_codes[0]);
    }
}
