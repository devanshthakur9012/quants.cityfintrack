<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public string $token
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'CityQuants – Verify Your Email');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verify-email', with: [
            'user'  => $this->user,
            'link'  => route('user.verify.email', $this->token),
        ]);
    }
}