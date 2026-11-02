<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebsiteContactMailMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly bool $visitor, public readonly ?string $opportunityUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->visitor ? 'Hemos recibido tu consulta · AREVANTES' : 'Nueva solicitud web · AREVANTES', replyTo: config('contact-mail.reply_to') ? [config('contact-mail.reply_to')] : []);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.website-contact');
    }
}
