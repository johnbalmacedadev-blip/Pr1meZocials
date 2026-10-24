<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewUserRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $newUser,
        public string $organization,
        public string $teamUrl,
        public ?string $roleName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New registration on '.$this->organization.': '.$this->newUser->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin-new-user-registered',
        );
    }
}
