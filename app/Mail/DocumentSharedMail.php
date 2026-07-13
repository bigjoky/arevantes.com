<?php

namespace App\Mail;

use App\Services\Documents\DocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentSharedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public object $document, public string $kind, public string $url, public string $expires) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: strtoupper($this->kind).' '.$this->document->number.' - '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.document-shared', with: ['company' => config('app.name'), 'number' => $this->document->number, 'url' => $this->url, 'expires' => $this->expires, 'acceptance' => $this->kind === 'quote']);
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => app(DocumentPdf::class)->render($this->document->loadMissing('lines'), $this->kind), $this->document->number.'.pdf')->withMime('application/pdf')];
    }
}
