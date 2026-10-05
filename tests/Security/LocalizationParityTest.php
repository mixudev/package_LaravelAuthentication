<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\DTO\AuthenticationResult;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationThrottledException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Guards the promise that every user-facing sentence the package emits exists in
 * both shipped locales, and that no controller or middleware hardcodes English
 * into an API `message`, a flash status, or an abort message.
 */
class LocalizationParityTest extends TestCase
{
    private const LOCALES = ['en', 'id'];

    public function test_every_locale_file_defines_the_same_keys(): void
    {
        $keysets = [];

        foreach (self::LOCALES as $locale) {
            $keysets[$locale] = $this->catalogueKeys($locale);
        }

        $en = $keysets['en'];

        $this->assertNotEmpty($en, 'The English catalogue is empty.');

        foreach ($keysets as $locale => $keys) {
            $missing = array_values(array_diff($en, $keys));
            $extra = array_values(array_diff($keys, $en));

            $this->assertSame([], $missing, "Locale [{$locale}] is missing keys: ".implode(', ', $missing));
            $this->assertSame([], $extra, "Locale [{$locale}] has keys English does not: ".implode(', ', $extra));
        }
    }

    /**
     * Placeholders are legitimate in a catalogue — they are what call sites fill.
     * What must never reach a user is an *unfilled* placeholder, so each
     * placeholder key is rendered with every placeholder it declares and the
     * result must be clean. A key that names a placeholder no call site supplies
     * fails here.
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('placeholderKeys')]
    public function test_a_placeholder_key_is_fully_replaced_when_rendered(string $key, string $template, string $locale): void
    {
        preg_match_all('/:([a-z_]+)/', $template, $matches);

        $replacements = [];
        foreach ($matches[1] as $placeholder) {
            $replacements[$placeholder] = match ($placeholder) {
                'seconds', 'minutes', 'min', 'max' => '30',
                'provider' => 'Google',
                default => 'value',
            };
        }

        $rendered = trans("authentication::messages.{$key}", $replacements);

        $this->assertIsString($rendered);
        $this->assertStringNotContainsString(
            ':',
            str_replace(['http://', 'https://'], '', $rendered),
            "Key [{$key}] in [{$locale}] rendered with an unfilled placeholder: {$rendered}"
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function placeholderKeys(): array
    {
        $cases = [];

        foreach (['en', 'id'] as $locale) {
            /** @var array<string, string> $catalogue */
            $catalogue = require __DIR__.'/../../resources/lang/'.$locale.'/messages.php';

            foreach ($catalogue as $key => $value) {
                if (preg_match('/:([a-z_]+)/', $value) !== 1) {
                    continue;
                }

                $cases["{$locale}.{$key}"] = [(string) $key, (string) $value, $locale];
            }
        }

        return $cases;
    }

    /**
     * A stale published translation file is the realistic failure mode here: the
     * host copies an older `messages.php` that has fewer keys, so `trans()`
     * returns the raw key and the API answers "messages.authenticated".
     */
    public function test_a_stale_published_catalogue_still_renders_readable_copy(): void
    {
        $host = $this->app->langPath('vendor/authentication/en');
        $this->assertDirectoryDoesNotExist($host);

        try {
            mkdir($host, 0777, true);
            file_put_contents($host.'/messages.php', "<?php\n\nreturn ['logged_out' => 'You have been logged out.'];\n");

            $message = trans('authentication::messages.authenticated');

            $this->assertIsString($message);
            $this->assertStringNotContainsString('messages.', $message, 'A missing key leaked the raw translation key to the user.');
        } finally {
            @unlink($host.'/messages.php');
            @rmdir($host);
        }
    }

    public function test_no_controller_or_middleware_hardcodes_a_user_facing_english_sentence(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (!preg_match_all("/(?:'message'\\s*=>|->with\\('status',|withErrors\\(\\[|abort\\(\\s*\\d+\\s*,)\\s*'([^']{12,})'/", $line, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $candidate) {
                    $offenders[] = basename($file).':'.($index + 1).' => '.$candidate;
                }
            }
        }

        $this->assertSame([], $offenders, "User-facing copy is still hardcoded:\n".implode("\n", $offenders));
    }

    /**
     * A default parameter is a constant expression, so `__()` cannot be one.
     * Every copy that used to sit in a signature therefore had to be a literal,
     * which is how "If an account with that email exists…" and the exception
     * sentences survived the first pass.
     *
     * Machine identifiers are excluded on purpose: `$status = 'within_budget'`
     * and `protected string $errorCode = 'INVALID_CREDENTIALS'` are wire values
     * that must stay stable across locales and must never be translated.
     */
    public function test_no_default_parameter_carries_a_user_facing_english_sentence(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles(extra: ['Exceptions', 'DTO']) as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (!preg_match('/\\$(?:message|genericMessage|reason|detail|errorMessage)\\w*\\s*=\\s*\'([^\']{12,})\'/', $line, $match)) {
                    continue;
                }

                // A sentence has whitespace. A snake_case token is a wire value.
                if (preg_match('/\\s/', $match[1]) !== 1) {
                    continue;
                }

                $offenders[] = basename($file).':'.($index + 1).' => '.$match[1];
            }
        }

        $this->assertSame([], $offenders, "A signature still hardcodes user-facing copy:\n".implode("\n", $offenders));
    }

    /**
     * The exception and DTO defaults are the sentences a user actually sees when
     * nothing overrides them, so both locales are pinned here.
     */
    public function test_default_exception_sentences_follow_the_locale(): void
    {
        app()->setLocale('en');
        $this->assertSame(
            'These credentials do not match our records.',
            (new InvalidCredentialsException())->getMessage()
        );
        $this->assertSame(
            'Too many login attempts. Please try again later.',
            (new AuthenticationThrottledException(30))->getMessage()
        );

        app()->setLocale('id');
        $this->assertNotSame(
            'These credentials do not match our records.',
            (new InvalidCredentialsException())->getMessage(),
            'InvalidCredentialsException ignored the active locale.'
        );
        $this->assertNotSame(
            'Too many login attempts. Please try again later.',
            (new AuthenticationThrottledException(30))->getMessage(),
            'AuthenticationThrottledException ignored the active locale.'
        );
        $this->assertNotSame(
            'These credentials do not match our records.',
            AuthenticationResult::failed()->message,
            'AuthenticationResult::failed() ignored the active locale.'
        );
    }

    public function test_an_explicit_message_still_overrides_the_localized_default(): void
    {
        app()->setLocale('id');

        $this->assertSame(
            'Custom throttle sentence.',
            (new AuthenticationThrottledException(30, 'Custom throttle sentence.'))->getMessage()
        );
        $this->assertSame(
            'Custom credential sentence.',
            (new InvalidCredentialsException('Custom credential sentence.'))->getMessage()
        );
        $this->assertSame(
            'Custom failure sentence.',
            \Vendor\LaravelAuthentication\DTO\AuthenticationResult::failed(message: 'Custom failure sentence.')->message
        );
    }

    public function test_email_bodies_render_in_the_active_locale(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);

            $otp = view('authentication::emails.otp', [
                'code'          => '123456',
                'expiryMinutes' => 10,
                'identifier'    => 'user@example.com',
                'user'          => null,
                'appName'       => 'Acme',
                'appUrl'        => 'https://example.test',
            ])->render();

            $device = view('authentication::emails.new-device', [
                'user'      => null,
                'device'    => new \Vendor\LaravelAuthentication\Models\AuthenticationDevice([
                    'device_name' => 'Chrome on Windows',
                    'ip_address'  => '203.0.113.10',
                    'location'    => 'Jakarta, ID',
                ]),
                'appName'   => 'Acme',
                'appUrl'    => 'https://example.test',
                'secureUrl' => 'https://example.test/sessions',
            ])->render();

            $expected = $locale === 'id'
                ? ['verifikasi' => true, 'not_requested' => 'tidak meminta']
                : ['verifikasi' => false, 'not_requested' => 'did not request'];

            $haystack = $otp.$device;

            if ($expected['verifikasi']) {
                $this->assertStringContainsString('verifikasi', $haystack, "OTP email did not render Indonesian for [{$locale}].");
                $this->assertStringContainsString($expected['not_requested'], $haystack, "OTP email did not render Indonesian for [{$locale}].");
            } else {
                $this->assertStringContainsString('verification code', $haystack, "OTP email did not render English for [{$locale}].");
                $this->assertStringContainsString('did not request', $haystack, "OTP email did not render English for [{$locale}].");
            }
        }
    }

    /**
     * The API surface must answer in the active locale, not in the language the
     * source was written in.
     */
    public function test_api_messages_follow_the_active_locale(): void
    {
        $en = [
            'messages.unauthenticated' => 'Unauthenticated.',
            'messages.authenticated'   => 'Signed in successfully.',
            'messages.logged_out'      => 'You have been logged out successfully.',
            'messages.account_locked'  => 'Your account is temporarily locked for security reasons.',
        ];

        $id = [
            'messages.unauthenticated' => 'Anda belum terautentikasi.',
            'messages.authenticated'   => 'Berhasil masuk.',
            'messages.logged_out'      => 'Anda berhasil keluar dari sistem.',
            'messages.account_locked'  => 'Akun Anda sementara dikunci karena alasan keamanan.',
        ];

        app()->setLocale('en');
        foreach ($en as $key => $expected) {
            $this->assertSame($expected, trans('authentication::'.$key), "English copy drifted for [{$key}].");
        }

        app()->setLocale('id');
        foreach ($id as $key => $expected) {
            $this->assertSame($expected, trans('authentication::'.$key), "Indonesian copy drifted for [{$key}].");
        }
    }

    /**
     * @return list<string>
     */
    private function catalogueKeys(string $locale): array
    {
        $path = __DIR__.'/../../resources/lang/'.$locale.'/messages.php';
        $this->assertFileExists($path);

        /** @var array<string, string> $catalogue */
        $catalogue = require $path;

        $keys = [];
        foreach (array_keys($catalogue) as $key) {
            $keys[] = (string) $key;
        }

        return $keys;
    }

    /**
     * @param  list<string>  $extra
     * @return list<string>
     */
    private function sourceFiles(array $extra = []): array
    {
        $root = realpath(__DIR__.'/../../src');

        if ($root === false) {
            return [];
        }

        $directories = array_merge(
            ['Http/Controllers', 'Http/Middleware', 'Http/Requests', 'Mail', 'DTO'],
            $extra
        );

        $files = [];
        foreach ($directories as $subdirectory) {
            $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subdirectory);
            if (! is_dir($path)) {
                continue;
            }

            foreach ((array) glob($path.DIRECTORY_SEPARATOR.'*.php') as $file) {
                $files[] = (string) $file;
            }
        }

        return $files;
    }
}