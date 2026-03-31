<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactSubmissionMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $messageText,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New Contact Form Submission');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-submission');
    }
}
