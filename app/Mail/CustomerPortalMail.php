<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerPortalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $customer, public string $url, public string $expires) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu portal de cliente - '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.customer-portal', with: ['company' => config('app.name'), 'customer' => $this->customer, 'url' => $this->url, 'expires' => $this->expires]);
    }
}
