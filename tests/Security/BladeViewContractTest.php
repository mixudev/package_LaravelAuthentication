<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Locks the canonical Blade view tree.
 *
 * Pages live under pages/<domain>, components under components/<group>, and
 * emails under emails/. There are deliberately no legacy wrappers: callers name
 * the real file directly, so a renamed view cannot hide behind a stale
 * compatibility layer that nobody edits any more.
 */
class BladeViewContractTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function configuredViews(): array
    {
        return [
            'login' => ['authentication::pages.auth.login'],
            'register' => ['authentication::pages.auth.register'],
            'confirm_password' => ['authentication::pages.auth.confirm-password'],
            'forgot_password' => ['authentication::pages.password.forgot-password'],
            'reset_password' => ['authentication::pages.password.reset-password'],
            'otp_request' => ['authentication::pages.otp.request'],
            'otp_verify' => ['authentication::pages.otp.verify'],
            'two_factor_challenge' => ['authentication::pages.two-factor.challenge'],
            'two_factor_setup' => ['authentication::pages.two-factor.setup'],
            'sessions' => ['authentication::pages.sessions.index'],
            'verify_email' => ['authentication::pages.auth.verify-email'],
            'otp_email' => ['authentication::emails.otp'],
            'new_device_email' => ['authentication::emails.new-device'],
        ];
    }

    /** @param array<string, array{0: string}> $cases */
    #[\PHPUnit\Framework\Attributes\DataProvider('configuredViews')]
    public function test_configured_view_targets_resolve(string $alias): void
    {
        $this->assertTrue(view()->exists($alias), "Configured view [{$alias}] does not resolve.");
    }

    public function test_config_matches_the_canonical_view_tree(): void
    {
        foreach (self::configuredViews() as $key => [$expected]) {
            if (in_array($key, ['otp_email', 'new_device_email'], true)) {
                continue;
            }

            $this->assertSame($expected, config("authentication.views.{$key}"));
        }
    }

    /**
     * Blade does not search nested directories, so every moved component must
     * use its canonical grouped tag directly.
     *
     * @return array<string, array{0: string}>
     */
    public static function componentTags(): array
    {
        return [
            'layouts.auth' => ['layouts.auth'],
            'layouts.card' => ['layouts.card'],
            'layouts.split' => ['layouts.split'],
            'forms.input' => ['forms.input'],
            'forms.button' => ['forms.button'],
            'forms.checkbox' => ['forms.checkbox'],
            'forms.otp-input' => ['forms.otp-input'],
            'forms.segmented-code-input' => ['forms.segmented-code-input'],
            'feedback.alert' => ['feedback.alert'],
            'feedback.countdown-alert' => ['feedback.countdown-alert'],
            'security.active-sessions' => ['security.active-sessions'],
            'security.passkey-button' => ['security.passkey-button'],
            'header' => ['header'],
            'divider' => ['divider'],
            'social-buttons' => ['social-buttons'],
            'brand-panel' => ['brand-panel'],
        ];
    }

    /** @param array<string, array{0: string}> $cases */
    #[\PHPUnit\Framework\Attributes\DataProvider('componentTags')]
    public function test_component_tag_resolves(string $name): void
    {
        $this->assertTrue(view()->exists("authentication::components.{$name}"));
    }

    public function test_every_component_tag_used_by_the_package_resolves(): void
    {
        $root = dirname(__DIR__, 2) . '/resources/views';
        $missing = [];
        $seen = [];

        foreach ($this->bladeFiles($root) as $file) {
            preg_match_all('/<x-authentication::([a-z0-9.\-]+)/', (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $component) {
                $seen[$component] = true;

                if (! view()->exists("authentication::components.{$component}")) {
                    $missing[] = $component . ' (' . basename($file) . ')';
                }
            }
        }

        $this->assertGreaterThan(0, count($seen), 'The component scan found no tags.');
        $this->assertSame([], $missing, 'Component tags do not resolve.');
    }

    /**
     * An unclosed style tag makes the browser parse the rest of the document as
     * CSS: HTTP 200 and a correct response body, but an empty document.body. A
     * status assertion therefore does not prove the page renders.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('layoutNames')]
    public function test_page_renders_exactly_one_document_per_layout(string $layout): void
    {
        config(['authentication.ui.layout' => $layout]);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $html = (string) view('authentication::pages.auth.login')->render();

        $this->assertSame(1, substr_count($html, '<!DOCTYPE html>'));
        $this->assertSame(1, substr_count($html, '</html>'));
        $this->assertSame(1, substr_count($html, '<body'));
        $this->assertSame(1, substr_count($html, '</head>'));
        $this->assertSame(substr_count($html, '<style'), substr_count($html, '</style>'));
        $this->assertSame(substr_count($html, '<script'), substr_count($html, '</script>'));
        $this->assertStringContainsString('</style>', $html);
        $this->assertStringContainsString('auth-btn-social', $html);
    }

    /** @return array<string, array{0: string}> */
    public static function layoutNames(): array
    {
        return ['card' => ['card'], 'split' => ['split']];
    }

    /**
     * The countdown strings used to be hardcoded Indonesian inside the Alpine
     * expression, so an English host saw a half-translated throttle message.
     */
    public function test_countdown_alert_renders_the_active_locale(): void
    {
        $this->app->setLocale('en');
        app('translator')->setLocale('en');

        $html = (string) $this->blade('<x-authentication::feedback.countdown-alert :retryAfter="90" />');

        $this->assertStringContainsString('Too many attempts', $html);
        $this->assertStringNotContainsString('Terlalu banyak percobaan', $html);

        $this->app->setLocale('id');
        app('translator')->setLocale('id');
        $html = (string) $this->blade('<x-authentication::feedback.countdown-alert :retryAfter="90" />');

        $this->assertStringContainsString('Terlalu banyak percobaan', $html);
        $this->assertStringNotContainsString('Too many attempts', $html);
    }

    /** @return list<string> */
    private function bladeFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}