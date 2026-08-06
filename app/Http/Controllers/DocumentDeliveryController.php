<?php

namespace App\Http\Controllers;

use App\Mail\DocumentSharedMail;
use App\Models\CompanyProfile;
use App\Models\CustomerDocumentAcceptance;
use App\Models\CustomerPortalEvent;
use App\Models\DocumentDelivery;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Services\Documents\DocumentFingerprint;
use App\Services\Documents\DocumentPdf;
use App\Services\Quotes\QuoteStatusWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentDeliveryController extends Controller
{
    public function sendQuote(Request $request, Quote $quote): RedirectResponse
    {
        abort_if(in_array($quote->status, ['accepted', 'rejected', 'expired'], true), 422, __('This quote can no longer be sent for acceptance.'));

        return $this->send($request, $quote, 'quote');
    }

    public function sendSalesDocument(Request $request, SalesDocument $salesDocument): RedirectResponse
    {
        abort_if($salesDocument->type === 'invoice' && ! $salesDocument->locked_at, 422, __('Issue the invoice before sending it.'));

        return $this->send($request, $salesDocument, $salesDocument->type);
    }

    public function show(Request $request, string $token): Response
    {
        $delivery = $this->delivery($token);
        $document = $delivery->quote ?: $delivery->salesDocument;
        if (! $delivery->first_viewed_at) {
            $delivery->update(['first_viewed_at' => now(), 'status' => $delivery->status === 'sent' ? 'viewed' : $delivery->status]);
            if ($document->customer) {
                CustomerPortalEvent::record($document->customer, 'document_viewed', null, $request, $document, ['number' => $document->number, 'delivery_id' => $delivery->id]);
            }
        }

        return Inertia::render('shared/document', ['company' => CompanyProfile::first()?->only(['legal_name', 'trade_name', 'email', 'phone']), 'document' => $document->load('lines'), 'kind' => $delivery->quote_id ? 'quote' : $delivery->salesDocument->type, 'token' => $token, 'canRespond' => (bool) $delivery->quote_id && in_array($delivery->quote->status, ['draft', 'sent'], true) && ! $delivery->responded_at, 'deliveryStatus' => $delivery->status]);
    }

    public function respond(Request $request, string $token, QuoteStatusWorkflow $workflow): RedirectResponse
    {
        $delivery = $this->delivery($token);
        abort_unless($delivery->quote_id && ! $delivery->responded_at, 422);
        $data = $request->validate(['response' => ['required', 'in:accepted,rejected']]);
        $quote = $delivery->quote;
        abort_unless(in_array($quote->status, ['draft', 'sent'], true), 422);
        $workflow->apply($quote, $data['response']);
        $delivery->update(['status' => $data['response'], 'responded_at' => now(), 'response_ip' => $request->ip(), 'response_user_agent' => Str::limit((string) $request->userAgent(), 1000, '')]);
        if ($data['response'] === 'accepted') {
            $fingerprint = app(DocumentFingerprint::class)->for($quote);
            CustomerDocumentAcceptance::firstOrCreate([
                'person_id' => $quote->person_id,
                'document_type' => 'quote',
                'document_id' => $quote->id,
                'document_version' => $delivery->document_version ?: $fingerprint['version'],
            ], [
                'document_hash' => $delivery->document_hash ?: $fingerprint['hash'],
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            ]);
        }
        if ($quote->customer) {
            CustomerPortalEvent::record($quote->customer, 'document_'.$data['response'], null, $request, $quote, ['number' => $quote->number, 'version' => $delivery->document_version]);
        }

        return to_route('documents.shared.show', $token);
    }

    public function pdf(Request $request, string $token, DocumentPdf $pdf): HttpResponse
    {
        $delivery = $this->delivery($token);
        $document = $delivery->quote ?: $delivery->salesDocument;
        $kind = $delivery->quote_id ? 'quote' : $delivery->salesDocument->type;
        $delivery->update(['last_downloaded_at' => now()]);
        if ($document->customer) {
            CustomerPortalEvent::record($document->customer, 'document_downloaded', null, $request, $document, ['number' => $document->number, 'delivery_id' => $delivery->id]);
        }

        return response($pdf->render($document->load('lines'), $kind), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$document->number.'.pdf"']);
    }

    private function send(Request $request, object $document, string $kind): RedirectResponse
    {
        $snapshot = $document->customer_snapshot ?? [];
        $default = $snapshot['billing_email'] ?? $snapshot['email'] ?? null;
        $data = $request->validate(['recipient_email' => ['required', 'email', 'max:255']]);
        $token = Str::random(64);
        $expires = now()->addDays($kind === 'quote' ? 30 : 90);
        $relation = $document instanceof Quote ? 'quote_id' : 'sales_document_id';
        $fingerprint = app(DocumentFingerprint::class)->for($document);
        $document->deliveries()->whereIn('status', ['pending', 'sent', 'viewed'])->update(['status' => 'superseded']);
        $delivery = DocumentDelivery::create([$relation => $document->id, 'sent_by' => $request->user()->id, 'recipient_email' => $data['recipient_email'] ?: $default, 'token_hash' => hash('sha256', $token), 'status' => 'pending', 'document_version' => $fingerprint['version'], 'document_hash' => $fingerprint['hash'], 'expires_at' => $expires]);
        try {
            Mail::to($delivery->recipient_email)->send(new DocumentSharedMail($document, $kind, route('documents.shared.show', $token), $expires->format('d/m/Y')));
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
            if ($document instanceof Quote && $document->status === 'draft') {
                $document->update(['status' => 'sent', 'sent_at' => now()]);
            }
            if ($document->customer) {
                CustomerPortalEvent::record($document->customer, 'document_shared', null, $request, $document, ['number' => $document->number, 'recipient_email' => $delivery->recipient_email, 'expires_at' => $expires->toIso8601String()]);
            }
        } catch (\Throwable $exception) {
            report($exception);
            $delivery->update(['status' => 'failed', 'failure_reason' => Str::limit($exception->getMessage(), 1000)]);

            return back()->withErrors(['recipient_email' => __('The document could not be sent. Check the mail configuration.')]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Document sent.')]);
    }

    public function revoke(Request $request, DocumentDelivery $delivery): RedirectResponse
    {
        $document = $delivery->quote ?: $delivery->salesDocument;
        abort_unless($document, 404);
        $delivery->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $request->user()->id]);
        if ($document->customer) {
            CustomerPortalEvent::record($document->customer, 'document_revoked', null, $request, $document, ['number' => $document->number, 'delivery_id' => $delivery->id]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Shared document revoked.')]);
    }

    private function delivery(string $token): DocumentDelivery
    {
        $delivery = DocumentDelivery::with(['quote', 'salesDocument'])->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($delivery->revoked_at || $delivery->expires_at->isPast() || in_array($delivery->status, ['superseded', 'failed', 'revoked'], true), 410, __('This document link has expired.'));

        return $delivery;
    }
}
