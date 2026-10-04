<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Support\Facades\Route;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Locks the Blade view contract.
 *
 * Page views were reorganised from a flat tree into `pages/<domain>/<page>.blade.php`
 * so the source stops reading as one flat list. Host applications may have
 * published and edited the old flat paths, so each old path still exists as a
 * thin `@include` wrapper. Both halves are asserted here:
 *
 *  1. the new configured targets resolve, and
 *  2. every legacy flat alias still resolves (so no host app breaks).
 *
 * A rename that forgets to leave a wrapper fails this test instead of failing
 * in production with "View [authentication::login] not found".
 */
class BladeViewContractTest extends TestCase
{
    /**
     * Config key => view alias the package must be able to render.
     *
     * @return array<string, array{0: string}>
     */
    public static function configuredViews(): array
    {
        return [
            'login'                => ['authentication::pages.auth.login'],
            'register'             => ['authentication::pages.auth.register'],
            'confirm_password'     => ['authentication::pages.auth.confirm-password'],
            'forgot_password'      => ['authentication::pages.password.forgot-password'],
            'reset_password'       => ['authentication::pages.password.reset-password'],
            'otp_request'          => ['authentication::pages.otp.request'],
            'otp_verify'           => ['authentication::pages.otp.verify'],
            'two_factor_challenge' => ['authentication::pages.two-factor.challenge'],
            'two_factor_setup'     => ['authentication::pages.two-factor.setup'],
            'sessions'             => ['authentication::pages.sessions.index'],
            'otp_email'            => ['authentication::emails.otp'],
            'new_device_email'     => ['authentication::emails.new-device'],
        ];
    }

    /**
     * Legacy flat aliases that must keep resolving for published host apps.
     *
     * @return array<string, array{0: string}>
     */
    public static function legacyFlatAliases(): array
    {
        return [
            'login'                => ['authentication::login'],
            'register'             => ['authentication::register'],
            'confirm-password'     => ['authentication::confirm-password'],
            'forgot-password'      => ['authentication::forgot-password'],
            'reset-password'       => ['authentication::reset-password'],
            'otp-request'          => ['authentication::otp-request'],
            'otp-verify'           => ['authentication::otp-verify'],
            'two-factor-challenge' => ['authentication::two-factor-challenge'],
            'two-factor-setup'     => ['authentication::two-factor-setup'],
            'sessions'             => ['authentication::sessions'],
        ];
    }

    /**
     * @param array<string, array{0: string}> $cases
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('configuredViews')]
    public function test_configured_view_targets_resolve(string $alias): void
    {
        $this->assertTrue(
            view()->exists($alias),
            "Configured view [{$alias}] does not resolve."
        );
    }

    /**
     * @param array<string, array{0: string}> $cases
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('legacyFlatAliases')]
    public function test_legacy_flat_alias_still_resolves(string $alias): void
    {
        $this->assertTrue(
            view()->exists($alias),
            "Legacy view [{$alias}] no longer resolves — a host app that published it will break."
        );
    }

    /**
     * `config('authentication.views.*')` still names the flat aliases on
     * purpose: that is the path a host app may already have published and
     * edited. Each flat file must therefore still delegate to the canonical
     * page, otherwise a reorganization would silently stop reaching a host's
     * overrides.
     *
     * @param array<string, array{0: string}> $cases
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('legacyFlatAliases')]
    public function test_flat_alias_delegates_to_the_canonical_page(string $alias): void
    {
        $file = $this->bladeFileFor($alias);

        $this->assertStringContainsString(
            '@include(',
            $file,
            "Flat alias [{$alias}] must delegate to the pages/ implementation."
        );
    }

    /**
     * Public anonymous-component alias => canonical implementation view path.
     *
     * `<x-authentication::alert />` must keep working for host apps that
     * already use it, even though the implementation moved.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function componentAliases(): array
    {
        return [
            'alert'                => ['alert', 'components/feedback/alert'],
            'countdown-alert'      => ['countdown-alert', 'components/feedback/countdown-alert'],
            'input'                => ['input', 'components/forms/input'],
            'button'               => ['button', 'components/forms/button'],
            'checkbox'             => ['checkbox', 'components/forms/checkbox'],
            'otp-input'            => ['otp-input', 'components/forms/otp-input'],
            'segmented-code-input' => ['segmented-code-input', 'components/forms/segmented-code-input'],
            'active-sessions'      => ['active-sessions', 'components/security/active-sessions'],
            'passkey-button'       => ['passkey-button', 'components/security/passkey-button'],
        ];
    }

    /**
     * @param array<string, array{0: string, 1: string}> $cases
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('componentAliases')]
    public function test_component_alias_resolves_to_its_canonical_implementation(string $alias, string $canonical): void
    {
        $canonicalView = "authentication::{$canonical}";
        $aliasView = "authentication::components.{$alias}";
        $aliasFile = dirname(__DIR__, 2) . "/resources/views/components/{$alias}.blade.php";

        $this->assertTrue(
            view()->exists($canonicalView),
            "Canonical component view [{$canonicalView}] does not resolve."
        );

        $this->assertTrue(
            view()->exists($aliasView),
            "Component <x-authentication::{$alias}> no longer resolves."
        );

        $this->assertFileExists($aliasFile, "Alias file for [{$alias}] is missing.");

        $contents = (string) file_get_contents($aliasFile);
        $expected = "@include('authentication::" . str_replace('/', '.', $canonical) . "', get_defined_vars())";

        $this->assertStringContainsString(
            $expected,
            $contents,
            "components/{$alias}.blade.php must forward the component scope with get_defined_vars()."
        );

        // A bare `@include('authentication::…')` compiles fine and renders fine,
        // but silently drops `$attributes` and `$slot`, so an unwrapped
        // component loses every attribute, class merge, and slot. Assert the
        // forwarding explicitly — "it rendered" is not enough.
        $this->assertDoesNotMatchRegularExpression(
            "/@include\('authentication::[^']+'\)\s*$/m",
            $contents,
            "components/{$alias}.blade.php uses a bare @include; attributes and slot would be dropped."
        );
    }

    private function bladeFileFor(string $alias): string
    {
        $relative = str_replace('authentication::', '', $alias);
        $path = dirname(__DIR__, 2) . '/resources/views/' . str_replace('.', '/', $relative) . '.blade.php';

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_layout_aliases_resolve(): void
    {
        foreach (['authentication::layouts.card', 'authentication::layouts.split'] as $alias) {
            $this->assertTrue(view()->exists($alias), "Layout [{$alias}] does not resolve.");
        }
    }

    /**
     * Every package page picks its layout through
     * `<x-dynamic-component :component="$activeLayout">` where `$activeLayout` is
     * `authentication::layouts.card` or `authentication::layouts.split`.
     *
     * Rendering such a page must produce exactly ONE document. The `layouts/`
     * files are `@include` wrappers, so two failure modes are possible and both
     * are silent: a wrapper that included itself recurses, and a wrapper that
     * included a page nests two `<html>` tags. Assert the rendered structure,
     * not merely that nothing threw.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('layoutNames')]
    public function test_page_renders_exactly_one_document_per_layout(string $layout): void
    {
        config(['authentication.ui.layout' => $layout]);

        foreach (self::pageViews() as $pageView) {
            // Pages read the shared error bag and the auth session.
            view()->share('errors', new \Illuminate\Support\ViewErrorBag());

            $html = (string) view($pageView)->render();

            $this->assertSame(
                1,
                substr_count($html, '<!DOCTYPE html>'),
                "Page [{$pageView}] rendered more than one document — nested layout or recursive wrapper."
            );

            $this->assertSame(
                1,
                substr_count($html, '</html>'),
                "Page [{$pageView}] rendered an unbalanced html element."
            );

            $this->assertStringContainsString(
                '<html lang=',
                $html,
                "Page [{$pageView}] did not render a base layout at all."
            );

            // The base layout must be the one carrying the restored styles.
            $this->assertStringContainsString(
                'auth-btn-social',
                $html,
                "Page [{$pageView}] lost the social/passkey stylesheet."
            );
        }
    }

    /**
     * Both layout names, as the pages spell them.
     *
     * @return array<string, array{0: string}>
     */
    public static function layoutNames(): array
    {
        return [
            'card'  => ['card'],
            'split' => ['split'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function pageViews(): array
    {
        // Login is the common page path and requires no page-specific secret,
        // user, token, or OTP fixture. Other pages already have dedicated
        // feature tests with their complete domain fixtures.
        return ['authentication::pages.auth.login'];
    }

    /**
     * `layouts/` used to ship byte-identical copies of `components/layouts/`
     * that had silently drifted — the live copy was missing the social/passkey
     * button CSS and the `[x-cloak]` FOUC guard. The `layouts/` files are now
     * wrappers that delegate to the component, so assert both the delegation
     * and that the surviving file still carries the styles.
     */
    public function test_layout_trees_are_not_duplicated_and_keep_their_styles(): void
    {
        $views = dirname(__DIR__, 2) . '/resources/views';

        foreach (['card', 'split'] as $layout) {
            $wrapper = (string) file_get_contents("{$views}/layouts/{$layout}.blade.php");

            $this->assertStringContainsString(
                "<x-authentication::layouts.{$layout}",
                $wrapper,
                "layouts/{$layout}.blade.php must delegate to components/layouts/{$layout}.blade.php."
            );
        }

        $auth = (string) file_get_contents("{$views}/components/layouts/auth.blade.php");

        foreach ([
            '.auth-btn-social',
            '.auth-btn-passkey',
            '[x-cloak]',
            'auth-btn-social-github:hover',
        ] as $selector) {
            $this->assertStringContainsString(
                $selector,
                $auth,
                "components/layouts/auth.blade.php lost the [{$selector}] rules."
            );
        }
    }

    /**
     * Filenames are the public artifact a host app republishes. Keep them
     * kebab-case so no route, include, or doc reference has to guess.
     */
    public function test_view_filenames_are_kebab_case(): void
    {
        $root = dirname(__DIR__, 2) . '/resources/views';

        /** @var \SplFileInfo $file */
        foreach ($this->bladeFiles($root) as $file) {
            $name = $file->getFilename();

            $this->assertMatchesRegularExpression(
                '/^[a-z0-9]+(-[a-z0-9]+)*\.blade\.php$/',
                $name,
                "View file [{$name}] is not kebab-case."
            );
        }
    }

    /**
     * @return \Generator<int, \SplFileInfo>
     */
    private function bladeFiles(string $root): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file;
            }
        }
    }

    /**
     * `countdown-alert` used to hardcode Indonesian copy in JS. Rendering it in
     * English must produce English, so a host on `app.locale=en` is not shown
     * a half-translated throttle countdown.
     */
    public function test_countdown_alert_renders_the_active_locale(): void
    {
        $this->app->setLocale('en');
        app('translator')->setLocale('en');

        $html = (string) $this->blade('<x-authentication::countdown-alert :retryAfter="90" />');

        $this->assertStringContainsString('Too many attempts', $html);
        $this->assertStringNotContainsString('Terlalu banyak percobaan', $html);
        $this->assertStringContainsString("retryMinutes:", $html);
        $this->assertStringContainsString("retryMinutesSeconds:", $html);
        $this->assertStringContainsString("retrySecondsOnly:", $html);

        $this->app->setLocale('id');
        app('translator')->setLocale('id');

        $html = (string) $this->blade('<x-authentication::countdown-alert :retryAfter="90" />');

        $this->assertStringContainsString('Terlalu banyak percobaan', $html);
        $this->assertStringNotContainsString('Too many attempts', $html);
    }
}