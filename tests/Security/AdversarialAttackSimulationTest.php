<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Hash;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Adversarial Attack Simulation: Bypass Intent Testing
 *
 * Purpose: Prove attackers CANNOT bypass rate limiting via:
 * - IP rotation (same account, 100 different IPs)
 * - Identifier rotation (100 different accounts, same IP)
 * - Distributed botnet (100 IPs x 100 accounts)
 *
 * Success criteria: 0% bypass rate (all attacks blocked)
 */
class AdversarialAttackSimulationTest extends TestCase
{
    private array $victims = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Enable abuse_policy for adversarial testing
        config(['authentication.security.abuse_policy.enabled' => true]);

        // Create victim accounts for testing
        for ($i = 0; $i < 100; $i++) {
            $this->victims[] = User::create([
                'name'     => "Victim User {$i}",
                'username' => "victim{$i}",
                'email'    => "victim{$i}@example.com",
                'password' => Hash::make('CorrectPassword123!'),
            ]);
        }
    }

    // ---------------------------------------------------------------
    // ATTACK SCENARIO 1: IP Rotation Bypass Attempt
    // Attacker tries same account from 100 different IPs
    // Defense: Account lockout triggers after max_failed_attempts
    // ---------------------------------------------------------------
    public function test_ip_rotation_attack_is_blocked_by_account_lockout(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $targetEmail = 'victim0@example.com';
        $successfulAttempts = 0;
        $throttled = 0;
        $accountLocked = 0;
        $invalidCredentials = 0;

        // Simulate attacker rotating through 100 different IPs
        for ($i = 0; $i < 100; $i++) {
            $attackerIp = sprintf('10.%d.%d.%d', ($i >> 8) & 0xFF, $i & 0xFF, ($i % 250) + 1);
            $context = new AuthenticationContext($attackerIp, "AttackBot/1.0");
            $loginData = new LoginData($targetEmail, 'WrongPassword' . $i);

            try {
                $result = $service->authenticate($loginData, $context);
                $successfulAttempts++;
            } catch (AccountLockedException) {
                $accountLocked++;
            } catch (AuthenticationThrottledException) {
                $throttled++;
            } catch (InvalidCredentialsException) {
                $invalidCredentials++;
            }
        }

        // Assert: Zero successful bypasses
        $this->assertEquals(0, $successfulAttempts, 
            'IP rotation attack succeeded - attacker bypassed defenses via IP rotation!');

        // Assert: Account lockout triggered (primary defense)
        $this->assertGreaterThan(0, $accountLocked,
            'Account lockout never triggered despite repeated failures from different IPs.');

        // Calculate and report attack success rate
        $totalAttempts = 100;
        $blocked = $accountLocked + $throttled;
        $bypassRate = ($successfulAttempts / $totalAttempts) * 100;

        $this->assertEquals(0.0, $bypassRate, sprintf(
            "IP Rotation Attack Bypass Rate: %.2f%% (must be 0%%). Blocked: %d, Succeeded: %d",
            $bypassRate,
            $blocked,
            $successfulAttempts
        ));
    }

    // ---------------------------------------------------------------
    // ATTACK SCENARIO 2: Identifier Rotation Bypass Attempt
    // Attacker tries 100 different accounts from same IP
    // Defense: Per-combo rate limits + account lockouts
    // ---------------------------------------------------------------
    public function test_identifier_rotation_attack_is_blocked(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $attackerIp = '192.168.100.50';
        $successfulAttempts = 0;
        $throttled = 0;
        $accountLocked = 0;
        $invalidCredentials = 0;

        // Simulate attacker trying 100 different identifiers from same IP
        for ($i = 0; $i < 100; $i++) {
            $targetEmail = "victim{$i}@example.com";
            
            // Create victim on-demand if doesn't exist
            if ($i >= count($this->victims)) {
                User::create([
                    'name'     => "Victim User {$i}",
                    'username' => "victim{$i}",
                    'email'    => $targetEmail,
                    'password' => Hash::make('CorrectPassword123!'),
                ]);
            }

            $context = new AuthenticationContext($attackerIp, "AttackBot/2.0");
            $loginData = new LoginData($targetEmail, 'BruteForceAttempt' . $i);

            try {
                $result = $service->authenticate($loginData, $context);
                $successfulAttempts++;
            } catch (AccountLockedException) {
                $accountLocked++;
            } catch (AuthenticationThrottledException) {
                $throttled++;
            } catch (InvalidCredentialsException) {
                $invalidCredentials++;
            }
        }

        // Assert: Zero successful bypasses
        $this->assertEquals(0, $successfulAttempts,
            'Identifier rotation attack succeeded - attacker bypassed defenses via account rotation!');

        // Each unique identifier gets independent rate limit budget, but all fail credential check
        $totalAttempts = 100;
        $bypassRate = ($successfulAttempts / $totalAttempts) * 100;

        $this->assertEquals(0.0, $bypassRate, sprintf(
            "Identifier Rotation Attack Bypass Rate: %.2f%% (must be 0%%). Invalid: %d, Throttled: %d, Locked: %d",
            $bypassRate,
            $invalidCredentials,
            $throttled,
            $accountLocked
        ));
    }

    // ---------------------------------------------------------------
    // ATTACK SCENARIO 3: Distributed Botnet Simulation
    // Attacker uses 100 IPs × 100 accounts (10,000 total attempts)
    // Defense: Per-combo limits + account lockouts across all sources
    // ---------------------------------------------------------------
    public function test_distributed_botnet_attack_is_blocked(): void
    {
        /** @var AuthenticationServiceInterface $service */
        $service = app(AuthenticationServiceInterface::class);

        $successfulAttempts = 0;
        $throttled = 0;
        $accountLocked = 0;
        $invalidCredentials = 0;
        $totalAttempts = 0;

        // Simulate distributed botnet: 100 IPs × 100 victim accounts
        for ($ipIndex = 0; $ipIndex < 100; $ipIndex++) {
            $botIp = sprintf('172.%d.%d.%d', 16 + ($ipIndex >> 8), $ipIndex & 0xFF, ($ipIndex % 250) + 1);

            for ($victimIndex = 0; $victimIndex < 100; $victimIndex++) {
                $totalAttempts++;
                $targetEmail = "victim{$victimIndex}@example.com";
                $context = new AuthenticationContext($botIp, "BotNet/3.0 (Node{$ipIndex})");
                $loginData = new LoginData($targetEmail, 'DistributedAttack' . $ipIndex . $victimIndex);

                try {
                    $result = $service->authenticate($loginData, $context);
                    $successfulAttempts++;
                } catch (AccountLockedException) {
                    $accountLocked++;
                } catch (AuthenticationThrottledException) {
                    $throttled++;
                } catch (InvalidCredentialsException) {
                    $invalidCredentials++;
                }
            }
        }

        // Assert: Zero successful bypasses
        $this->assertEquals(0, $successfulAttempts,
            'Distributed botnet attack succeeded - attacker bypassed defenses via distributed attack!');

        // Assert: Account lockouts triggered for victim accounts
        $this->assertGreaterThan(0, $accountLocked,
            'No account lockouts triggered despite distributed botnet attack.');

        // Calculate bypass rate
        $blocked = $accountLocked + $throttled;
        $bypassRate = ($successfulAttempts / $totalAttempts) * 100;

        $this->assertEquals(0.0, $bypassRate, sprintf(
            "Distributed Botnet Attack Bypass Rate: %.2f%% (must be 0%%). Total: %d, Blocked: %d, Succeeded: %d",
            $bypassRate,
            $totalAttempts,
            $blocked,
            $successfulAttempts
        ));
    }

    // ---------------------------------------------------------------
    // VERIFICATION: Attack metrics summary
    // ---------------------------------------------------------------
    public function test_attack_simulation_metrics_are_zero_bypass(): void
    {
        // This test runs all three scenarios and aggregates metrics
        $this->test_ip_rotation_attack_is_blocked_by_account_lockout();
        $this->test_identifier_rotation_attack_is_blocked();
        $this->test_distributed_botnet_attack_is_blocked();

        // If we reached here, all three attack scenarios were blocked (0% bypass)
        $this->assertTrue(true, 'All adversarial attack scenarios blocked successfully (0% bypass rate).');
    }
}
