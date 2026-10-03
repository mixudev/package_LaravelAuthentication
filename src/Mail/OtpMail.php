<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for dispatching One-Time Password verification codes to users.
 *
 * Queue behavior is controlled by config('authentication.mail.queue'):
 * - true: OtpService calls Mail::queue() → background delivery via queue worker
 * - false: OtpService calls Mail::send() → immediate synchronous delivery
 *
 * The Queueable trait enables ->queue() support without forcing all emails into queue.
 */
class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly int $expiryMinutes,
        public readonly string $identifier,
        public readonly ?Authenticatable $user = null,
        public readonly ?string $customSubject = null,
        public readonly ?string $customView = null
    ) {
        // Queue connection can be customized via config
        $queueConnection = config('authentication.mail.queue_connection');

        if ($queueConnection) {
            $this->onConnection((string) $queueConnection);
        }

        // Use default queue unless explicitly overridden
        // This allows 'php artisan queue:work' to process auth emails
        // without requiring --queue=auth-emails flag
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'Laravel');
        $defaultSubject = "{$appName} — " . (string) __('authentication::messages.mail_otp_title');
        $subject = $this->customSubject ?: (string) config('authentication.features.otp.email_subject', $defaultSubject);

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $viewName = $this->customView ?: (string) config('authentication.features.otp.email_view', 'authentication::emails.otp');

        return new Content(
            view: $viewName,
            with: [
                'code'          => $this->code,
                'expiryMinutes' => $this->expiryMinutes,
                'identifier'    => $this->identifier,
                'user'          => $this->user,
                'appName'       => config('app.name', 'Laravel'),
                'appUrl'        => config('app.url', 'http://localhost'),
            ],
        );
    }
}
