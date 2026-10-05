<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Vendor\LaravelAuthentication\Mail\NewDeviceLoginMail;
use Vendor\LaravelAuthentication\Mail\OtpMail;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * Email is the one surface a recipient cannot switch: there is no locale toggle
 * in an inbox. These tests pin the rendered body and subject for both shipped
 * locales so a copy edit that drops one language, or a template that regresses
 * to a hardcoded sentence, fails here instead of in a user's mailbox.
 */
class MailLocalizationTest extends TestCase
{
    private function device(): AuthenticationDevice
    {
        return new AuthenticationDevice([
            'device_name' => 'Chrome on Windows',
            'ip_address'  => '203.0.113.10',
            'location'    => 'Jakarta, ID',
        ]);
    }

    private function user(): User
    {
        return new User(['name' => 'Rina']);
    }

    public function test_otp_mail_body_renders_english(): void
    {
        app()->setLocale('en');

        $rendered = (new OtpMail('123456', 10, 'rina@example.test', $this->user()))
            ->render();

        foreach ([
            'Hello',
            'Use the following verification code to sign in to your account:',
            'This code is valid for 10 minutes.',
            'If you did not request this code',
            '123456',
        ] as $expected) {
            $this->assertStringContainsString($expected, $rendered, "OTP email is missing English copy: {$expected}");
        }
    }

    public function test_otp_mail_body_renders_indonesian(): void
    {
        app()->setLocale('id');

        $rendered = (new OtpMail('123456', 10, 'rina@example.test', $this->user()))
            ->render();

        foreach ([
            'Halo',
            'Gunakan kode verifikasi berikut',
            'Kode ini berlaku selama 10 menit',
            'tidak meminta kode ini',
            '123456',
        ] as $expected) {
            $this->assertStringContainsString($expected, $rendered, "OTP email is missing Indonesian copy: {$expected}");
        }
    }

    public function test_otp_mail_subject_follows_the_locale(): void
    {
        app()->setLocale('en');
        $this->assertStringContainsString('Login Verification Code', (new OtpMail('1', 5, 'x'))->envelope()->subject);

        app()->setLocale('id');
        $this->assertStringContainsString('Kode Verifikasi', (new OtpMail('1', 5, 'x'))->envelope()->subject);
    }

    public function test_new_device_mail_body_renders_english(): void
    {
        app()->setLocale('en');

        $rendered = (new NewDeviceLoginMail($this->user(), $this->device()))
            ->render();

        foreach ([
            'We detected a login to your account from a new device or location',
            'Device &amp; Browser:',
            'IP Address:',
            'Time:',
            'Chrome on Windows',
            'Secure Account',
        ] as $expected) {
            $this->assertStringContainsString($expected, $rendered, "New-device email is missing English copy: {$expected}");
        }
    }

    public function test_new_device_mail_body_renders_indonesian(): void
    {
        app()->setLocale('id');

        $rendered = (new NewDeviceLoginMail($this->user(), $this->device()))
            ->render();

        foreach ([
            'aktivitas masuk ke akun Anda',
            'Perangkat &amp; Browser:',
            'Alamat IP:',
            'Waktu:',
            'Chrome on Windows',
            'Amankan Akun &amp; Cabut Sesi',
        ] as $expected) {
            $this->assertStringContainsString($expected, $rendered, "New-device email is missing Indonesian copy: {$expected}");
        }
    }

    public function test_new_device_mail_subject_follows_the_locale(): void
    {
        app()->setLocale('en');
        $this->assertStringContainsString('New Device Login Detected', (new NewDeviceLoginMail($this->user(), $this->device()))->envelope()->subject);

        app()->setLocale('id');
        $this->assertStringContainsString('Perangkat Baru', (new NewDeviceLoginMail($this->user(), $this->device()))->envelope()->subject);
    }

    /**
     * A queued mailable is rendered by the worker, not the web request. The
     * locale must therefore be captured on the mailable at construction time,
     * or a worker running under `APP_LOCALE=en` sends Indonesian copy to an
     * Indonesian user. `locale()` therefore records the active locale and the
     * mailable restores it while rendering.
     */
    public function test_queued_mail_carries_its_locale_to_the_worker(): void
    {
        app()->setLocale('id');
        $mail = new OtpMail('123456', 10, 'rina@example.test', $this->user());

        // Simulate a worker whose own default differs from the request that
        // queued the mail.
        app()->setLocale('en');

        $this->assertStringContainsString(
            'Halo',
            $mail->render(),
            'A queued OTP email lost the locale it was queued under.'
        );
    }

    public function test_queued_new_device_mail_carries_its_locale_to_the_worker(): void
    {
        app()->setLocale('id');
        $mail = new NewDeviceLoginMail($this->user(), $this->device());

        app()->setLocale('en');

        $this->assertStringContainsString('mendeteksi aktivitas masuk', $mail->render());
    }

    public function test_a_configured_subject_overrides_the_localized_default(): void
    {
        config(['authentication.features.otp.email_subject' => 'Your Acme code']);
        config(['authentication.security.new_device_notification.mail_subject' => 'Acme security notice']);

        app()->setLocale('en');

        $this->assertSame('Your Acme code', (new OtpMail('1', 5, 'x'))->envelope()->subject);
        $this->assertSame('Acme security notice', (new NewDeviceLoginMail($this->user(), $this->device()))->envelope()->subject);
    }

    public function test_an_empty_configured_subject_falls_back_to_the_localized_default(): void
    {
        // `config(..., $default)` cannot supply a default when the key *exists*
        // with a null value, so an empty setting used to produce an empty
        // subject line.
        config(['authentication.features.otp.email_subject' => '']);
        config(['authentication.security.new_device_notification.mail_subject' => null]);

        app()->setLocale('id');

        $this->assertStringContainsString(
            (string) trans('authentication::messages.mail_otp_title'),
            (new OtpMail('1', 5, 'x'))->envelope()->subject
        );
        $this->assertStringContainsString(
            (string) trans('authentication::messages.mail_new_device_title'),
            (new NewDeviceLoginMail($this->user(), $this->device()))->envelope()->subject
        );
    }

    public function test_email_escapes_the_recipient_name(): void
    {
        app()->setLocale('en');

        $mail = new OtpMail('123456', 10, 'x', new User(['name' => '<script>alert(1)</script>']));

        $rendered = $mail->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $rendered);
    }

}