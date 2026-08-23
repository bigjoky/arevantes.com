<?php

namespace App\Services\Documents;

use App\Mail\DocumentSharedMail;
use App\Models\DocumentDelivery;
use App\Models\SalesDocument;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AcceptanceInvoiceNotifier
{
    public function __construct(
        private readonly DocumentFingerprint $fingerprints,
        private readonly DocumentPdf $pdf,
    ) {}

    public function send(SalesDocument $invoice): ?DocumentDelivery
    {
        $invoice->loadMissing('customer');
        $snapshot = $invoice->customer_snapshot ?? [];
        $recipient = $snapshot['billing_email'] ?? $snapshot['email'] ?? $invoice->customer?->billing_email ?? $invoice->customer?->email;
        if (! $recipient) {
            return null;
        }

        $token = Str::random(64);
        $expires = now()->addDays(90);
        $fingerprint = $this->fingerprints->for($invoice->loadMissing('lines'));
        $delivery = DocumentDelivery::create([
            'sales_document_id' => $invoice->id,
            'sent_by' => $invoice->created_by,
            'recipient_email' => $recipient,
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'document_version' => $fingerprint['version'],
            'document_hash' => $fingerprint['hash'],
            'expires_at' => $expires,
        ]);

        try {
            $pdfData = $this->pdf->render($invoice, 'invoice', final: true);
            Mail::to($recipient)->send(new DocumentSharedMail(
                $invoice,
                'invoice',
                route('documents.shared.show', $token),
                $expires->format('d/m/Y'),
                $pdfData,
            ));
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $exception) {
            if (app()->runningUnitTests()) {
                throw $exception;
            }

            report($exception);
            $delivery->update(['status' => 'failed', 'failure_reason' => Str::limit($exception->getMessage(), 1000)]);
        }

        return $delivery;
    }
}
