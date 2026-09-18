<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SmtpTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(private readonly string $organizationName) {}

    public function envelope(): Envelope { return new Envelope(subject: $this->organizationName.' SMTP test email'); }

    public function content(): Content
    {
        return new Content(view: 'emails.smtp-test', with: ['organizationName' => $this->organizationName]);
    }
}
