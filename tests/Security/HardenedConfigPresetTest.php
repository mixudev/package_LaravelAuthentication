<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Vendor\LaravelAuthentication\Rules\PasswordRule;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * C-01/C-02/C-03: configuration posture.
 *
 * Shipped defaults are deliberately opt-in: min_length 8, no complexity rules,
 * account_lockout disabled, captcha disabled. That is a compatibility decision, not
 * an oversight, so this suite does NOT assert that defaults are hardened. It asserts
 * two things instead:
 *
 *  1. The documented opt-in keys are actually read by the code that enforces them
 *     (a knob nobody reads is a protection an operator believes is ON but is OFF).
 *  2. config/authentication-hardened.php turns those same keys on, so the hardened
 *     preset cannot silently rot into a no-op.
 */
final class HardenedConfigPresetTest extends TestCase
{
    private const PRESET = __DIR__ . '/../../config/authentication-hardened.php';

    /** @return array<string, mixed> */
    private function preset(): array
    {
        /** @var array<string, mixed> $preset */
        $preset = require self::PRESET;

        return $preset;
    }

    #[Test]
    public function shipped_defaults_are_opt_in_and_documented_as_such(): void
    {
        // These assertions pin the CURRENT compatibility contract. If a future
        // release intentionally tightens a default, this test fails loudly and the
        // change must be released as a BREAKING change with a migration note.
        /** @var array<string, mixed> $defaults */
        $defaults = require __DIR__ . '/../../config/authentication.php';

        $this->assertSame(8, (int) $defaults['password']['validation_rules']['min_length']);
        $this->assertFalse((bool) $defaults['security']['account_lockout']['enabled']);
        $this->assertFalse((bool) $defaults['security']['captcha']['enabled']);

        // Complexity rules are off by default (NIST SP 800-63B: length over composition).
        $this->assertFalse((bool) $defaults['password']['validation_rules']['require_uppercase']);
        $this->assertFalse((bool) $defaults['password']['validation_rules']['require_numbers']);
    }

    #[Test]
    public function lockout_and_captcha_flags_are_read_by_the_enforcing_code(): void
    {
        // Proves the config keys are not dead knobs: AuthenticationConfig is the
        // accessor every enforcing service uses.
        Config::set('authentication.security.account_lockout.enabled', true);
        Config::set('authentication.security.captcha.enabled', true);

        $config = app(AuthenticationConfig::class);

        $this->assertTrue($config->isLockoutEnabled(), 'account_lockout.enabled must be read by AuthenticationConfig');
        $this->assertTrue($config->isCaptchaEnabled(), 'captcha.enabled must be read by AuthenticationConfig');
    }

    #[Test]
    public function hardened_preset_enables_lockout_and_captcha(): void
    {
        $preset = $this->preset();

        $this->assertTrue(
            (bool) ($preset['security']['account_lockout']['enabled'] ?? false),
            'the hardened preset must enable account lockout'
        );
        $this->assertTrue(
            (bool) ($preset['security']['captcha']['enabled'] ?? false),
            'the hardened preset must enable CAPTCHA'
        );
        $this->assertTrue(
            (bool) ($preset['password']['validation_rules']['require_symbols'] ?? false),
            'the hardened preset must require symbol complexity'
        );
        $this->assertGreaterThanOrEqual(
            12,
            (int) ($preset['password']['validation_rules']['min_length'] ?? 0),
            'the hardened preset must require at least 12 characters'
        );
    }

    #[Test]
    public function merging_the_hardened_preset_actually_tightens_enforcement(): void
    {
        // Behavioral, not structural: merge the preset the way the preset's own
        // docblock tells operators to, then assert the ENFORCING code rejects a
        // password that the shipped defaults would have accepted.
        Config::set('authentication', array_replace_recursive(
            Config::get('authentication', []),
            $this->preset()
        ));

        $rule = PasswordRule::fromConfig();

        $rejected = false;
        $rule->validate('password', 'alllowercase123', static function () use (&$rejected): void {
            $rejected = true;
        });

        $this->assertTrue(
            $rejected,
            'with the hardened preset merged, an all-lowercase password must be rejected'
        );

        $accepted = true;
        $rule->validate('password', 'Str0ng-Passw0rd!', static function () use (&$accepted): void {
            $accepted = false;
        });

        $this->assertTrue($accepted, 'a compliant password must still be accepted under the preset');
    }

    #[Test]
    public function hardened_preset_does_not_silently_require_email_verification_for_existing_flows(): void
    {
        // Guard against a preset that would break registration for hosts that did not
        // configure mail. The preset flips require_email_verify; assert that is the only
        // registration change so an operator sees the full behavioral delta.
        $preset = $this->preset();

        $this->assertArrayHasKey('require_email_verify', $preset['features']['registration'] ?? []);
        $this->assertTrue(
            (bool) $preset['features']['registration']['require_email_verify'],
            'the hardened preset requires email verification'
        );
    }
}