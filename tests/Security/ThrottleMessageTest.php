<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Vendor\LaravelAuthentication\Support\ThrottleMessage;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * The throttle message is built from one place.
 *
 * Before this class existed, twelve call sites translated
 * `authentication::messages.throttle_error` with no replacements, so users saw
 * the raw placeholder: "Too many attempts. Please try again in :seconds
 * seconds." Enforcement was never affected — only the message was wrong.
 *
 * This test locks the two properties that keep that from regressing:
 * the helper always interpolates a number, and it never emits a placeholder.
 */
final class ThrottleMessageTest extends TestCase
{
    /**
     * @return array<string, array{0: int|null, 1: bool}>
     */
    public static function retryWindows(): array
    {
        return [
            'one second'          => [1, true],
            'typical window'      => [37, true],
            'minutes as seconds'  => [900, true],
            'zero falls back'     => [0, false],
            'null falls back'     => [null, false],
            'negative clamps'     => [-5, false],
        ];
    }

    #[DataProvider('retryWindows')]
    public function test_it_never_emits_an_unreplaced_placeholder(int|null $seconds, bool $expectsNumber): void
    {
        $message = ThrottleMessage::forSeconds($seconds);

        self::assertStringNotContainsString(':seconds', $message);
        self::assertStringNotContainsString('%s', $message);
        self::assertNotSame('', trim($message));

        if ($expectsNumber) {
            self::assertStringContainsString((string) max(0, (int) $seconds), $message);
        }
    }

    public function test_english_message_interpolates_the_retry_window(): void
    {
        $this->app->setLocale('en');

        self::assertSame(
            'Too many attempts. Please try again in 37 seconds.',
            ThrottleMessage::forSeconds(37)
        );
    }

    public function test_indonesian_message_interpolates_the_retry_window(): void
    {
        $this->app->setLocale('id');

        self::assertSame(
            'Terlalu banyak percobaan. Silakan coba lagi dalam 37 detik.',
            ThrottleMessage::forSeconds(37)
        );
    }

    public function test_unknown_window_still_produces_a_readable_sentence(): void
    {
        $this->app->setLocale('en');
        $this->assertSame(
            'Too many attempts. Please try again later.',
            ThrottleMessage::forSeconds(0)
        );

        $this->app->setLocale('id');
        $this->assertSame(
            'Terlalu banyak percobaan. Silakan coba lagi nanti.',
            ThrottleMessage::forSeconds(0)
        );
    }

    /**
     * The real regression guard.
     *
     * `/forgot-password` is one of the paths that returned a bare
     * `__('...throttle_error')` with no replacements, so the rendered page
     * showed ":seconds". Hit it past the limit and assert the placeholder is
     * gone from the actual response body.
     */
    public function test_throttled_response_never_leaks_the_placeholder(): void
    {
        $this->app->setLocale('en');

        config([
            'authentication.security.rate_limits.forgot_password.enabled'      => true,
            'authentication.security.rate_limits.forgot_password.max_attempts'  => 1,
            'authentication.security.rate_limits.forgot_password.decay_minutes' => 1,
            'authentication.security.rate_limits.forgot_password.strategy'      => 'ip',
        ]);

        $this->post('/forgot-password', ['email' => 'throttle@example.test']);

        $response = $this->post('/forgot-password', ['email' => 'throttle@example.test']);

        $this->assertSame(302, $response->getStatusCode());

        $errors = $response->getSession()->get('errors');
        $body = (string) $response->getContent()
            . (string) ($errors?->first('email') ?? '')
            . (string) ($errors?->first('identifier') ?? '');

        // Guard against a vacuous pass: the throttle must actually have fired.
        $this->assertStringContainsString(
            'Too many attempts. Please try again in',
            $body
        );

        $this->assertStringNotContainsString(':seconds', $body);
        $this->assertStringNotContainsString('%s', $body);
    }
}