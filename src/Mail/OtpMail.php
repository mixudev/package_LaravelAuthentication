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
 * NOTE: does NOT implement ShouldQueue. Queueing is driven by
 * config('authentication.mail.queue'); the dispatcher chooses ->queue() vs ->send().
 * A hardcoded ShouldQueue here forced all OTP mail onto the queue even when the
 * host has queue=false and no worker running, so the code was never delivered.
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
        $queueName = (string) config('authentication.mail.queue_name', 'auth-emails');
        $queueConnection = config('authentication.mail.queue_connection');

        if ($queueConnection) {
            $this->onConnection((string) $queueConnection);
        }

        $this->onQueue($queueName);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'Laravel');
        $defaultSubject = "{$appName} — Kode Verifikasi Masuk (OTP)";
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
