<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public string $token
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'CityQuants – Reset Your Password');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reset-password', with: [
            'user' => $this->user,
            'link' => route('user.reset.password', $this->token),
        ]);
    }
}