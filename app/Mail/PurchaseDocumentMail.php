<?php

namespace App\Mail;

use App\Models\PurchaseDocument;
use App\Services\Documents\PurchaseDocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseDocument $document, public ?string $messageText = null) {}

    public function envelope(): Envelope
    {
        $label = $this->document->type === 'purchase_rfq' ? __('Request for quotation') : __('Purchase order');

        return new Envelope(subject: "{$label} {$this->document->number} - ".config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.purchase-document');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => app(PurchaseDocumentPdf::class)->render($this->document),
                $this->document->number.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
