<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $heading,
        public string $body,
        public string $actionUrl,
        public string $actionLabel,
        public string $organization,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->heading.' · '.$this->organization,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notification',
        );
    }
}
