<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelAuthentication\Models\AuthenticationDevice;

/**
 * New-device login notification.
 *
 * NOTE: does NOT implement ShouldQueue. Queueing follows config('authentication.mail.queue');
 * the dispatcher chooses ->queue() vs ->send(), so mail is only queued when intended.
 */
class NewDeviceLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly AuthenticationDevice $device,
        public readonly ?string $customSubject = null,
        public readonly ?string $customView = null
    ) {
        $queueName = (string) config('authentication.mail.queue_name', 'auth-emails');
        $queueConnection = config('authentication.mail.queue_connection');

        if ($queueConnection) {
            $this->onConnection((string) $queueConnection);
        }

        $this->onQueue($queueName);
    }

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'Laravel');
        $defaultSubject = "{$appName} — Deteksi Masuk dari Perangkat Baru";
        $subject = $this->customSubject ?: (string) config('authentication.security.new_device_notification.mail_subject', $defaultSubject);

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $viewName = $this->customView ?: (string) config('authentication.views.new_device_email', 'authentication::emails.new-device');

        return new Content(
            view: $viewName,
            with: [
                'user'      => $this->user,
                'device'    => $this->device,
                'appName'   => config('app.name', 'Laravel'),
                'appUrl'    => config('app.url', 'http://localhost'),
                'secureUrl' => url(config('authentication.routes.web.prefix', '') . '/auth/sessions'),
            ],
        );
    }
}
