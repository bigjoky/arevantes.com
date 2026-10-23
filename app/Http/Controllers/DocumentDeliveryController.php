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
use App\Services\Quotes\QuoteDeliveryArchive;
use App\Services\Quotes\QuoteSelection;
use App\Services\Quotes\QuoteStatusWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentDeliveryController extends Controller
{
    public function sendQuote(Request $request, Quote $quote, QuoteStatusWorkflow $workflow, DocumentPdf $pdf): RedirectResponse
    {
        abort_unless(app()->runningUnitTests() || ! in_array(config('mail.default'), ['log', 'array'], true), 422, __('Configure a real email transport before sending quotes.'));

        return DB::transaction(function () use ($request, $quote, $workflow, $pdf): RedirectResponse {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            abort_if(in_array($quote->status, ['accepted', 'rejected', 'expired'], true), 422, __('This quote can no longer be sent for acceptance.'));
            abort_if($quote->customer?->do_not_contact, 422, __('This customer has blocked commercial communications.'));
            if ($request->filled('expected_version') && (int) $request->input('expected_version') !== $quote->version) {
                return back()->withErrors(['recipient_email' => __('This quote changed. Reload before sending it.')]);
            }
            $workflow->assertCanTransition($quote, 'sent');

            return $this->send($request, $quote, 'quote', $pdf, $workflow);
        });
    }

    public function sendSalesDocument(Request $request, SalesDocument $salesDocument, DocumentPdf $pdf): RedirectResponse
    {
        abort_if($salesDocument->type === 'invoice' && ! $salesDocument->locked_at, 422, __('Issue the invoice before sending it.'));

        return $this->send($request, $salesDocument, $salesDocument->type, $pdf);
    }

    public function show(Request $request, string $token): Response
    {
        $delivery = $this->delivery($token);
        $document = $delivery->quote ?: $delivery->salesDocument;
        $delivery->quote_id ? $document->load('lines.components.unit') : $document->load('lines');

        $currentFingerprint = app(DocumentFingerprint::class)->for($document);
        if (! $delivery->document_version || ! $delivery->document_hash) {
            $delivery->update(['document_version' => $currentFingerprint['version'], 'document_hash' => $currentFingerprint['hash']]);
        } else {
            abort_unless(hash_equals($delivery->document_version, $currentFingerprint['version']) && hash_equals($delivery->document_hash, $currentFingerprint['hash']), 409, __('This shared document has been replaced by a newer version.'));
        }
        if (! $delivery->first_viewed_at) {
            $delivery->update(['first_viewed_at' => now(), 'status' => $delivery->status === 'sent' ? 'viewed' : $delivery->status]);
            if ($document->customer) {
                CustomerPortalEvent::record($document->customer, 'document_viewed', null, $request, $document, ['number' => $document->number, 'delivery_id' => $delivery->id]);
            }
        }

        return Inertia::render('shared/document', ['company' => CompanyProfile::first()?->only(['legal_name', 'trade_name', 'email', 'phone']), 'document' => $document, 'kind' => $delivery->quote_id ? 'quote' : $delivery->salesDocument->type, 'token' => $token, 'canRespond' => (bool) $delivery->quote_id && in_array($delivery->quote->status, ['draft', 'sent'], true) && ! $delivery->quote->valid_until?->isPast() && ! $delivery->responded_at, 'deliveryStatus' => $delivery->status, 'documentVersion' => $currentFingerprint['version'], 'documentHash' => $currentFingerprint['hash']]);
    }

    public function respond(Request $request, string $token, QuoteStatusWorkflow $workflow, QuoteSelection $selection): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', 'in:accepted,rejected'], 'expected_version' => ['required', 'string', 'max:80'], 'expected_hash' => ['required', 'string', 'size:64'], 'selected_line_ids' => ['sometimes', 'array'], 'selected_line_ids.*' => ['integer']]);

        DB::transaction(function () use ($request, $token, $workflow, $selection, $data): void {
            $delivery = $this->delivery($token, true);
            abort_unless($delivery->quote_id && ! $delivery->responded_at, 422);
            $quote = Quote::with('lines.components')->lockForUpdate()->findOrFail($delivery->quote_id);
            abort_unless(in_array($quote->status, ['draft', 'sent'], true), 422);
            abort_if($quote->valid_until?->isPast(), 422, __('This quote has expired.'));
            $sentFingerprint = app(DocumentFingerprint::class)->for($quote);
            abort_unless(
                hash_equals((string) $delivery->document_version, $data['expected_version'])
                && hash_equals((string) $delivery->document_hash, $data['expected_hash'])
                && hash_equals($sentFingerprint['version'], $data['expected_version'])
                && hash_equals($sentFingerprint['hash'], $data['expected_hash']),
                409,
                __('This document changed after it was shared. Request an updated version before responding.'),
            );

            if ($data['response'] === 'accepted') {
                $quote = $selection->apply($quote, $data['selected_line_ids'] ?? []);
            }

            $workflow->apply($quote, $data['response'], null, 'shared_link');
            $delivery->update(['status' => $data['response'], 'responded_at' => now(), 'response_ip' => $request->ip(), 'response_user_agent' => Str::limit((string) $request->userAgent(), 1000, '')]);

            if ($data['response'] === 'accepted') {
                $fingerprint = app(DocumentFingerprint::class)->for($quote);
                $delivery->update(['document_version' => $fingerprint['version'], 'document_hash' => $fingerprint['hash']]);
                CustomerDocumentAcceptance::firstOrCreate([
                    'person_id' => $quote->person_id,
                    'document_type' => 'quote',
                    'document_id' => $quote->id,
                    'document_version' => $fingerprint['version'],
                ], [
                    'document_hash' => $fingerprint['hash'],
                    'accepted_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                ]);
            }

            if ($quote->customer) {
                CustomerPortalEvent::record($quote->customer, 'document_'.$data['response'], null, $request, $quote, ['number' => $quote->number, 'version' => $delivery->document_version]);
            }
        });

        return to_route('documents.shared.show', $token);
    }

    public function pdf(Request $request, string $token, DocumentPdf $pdf): HttpResponse
    {
        $delivery = $this->delivery($token);
        $document = $delivery->quote ?: $delivery->salesDocument;
        $kind = $delivery->quote_id ? 'quote' : $delivery->salesDocument->type;
        $currentFingerprint = app(DocumentFingerprint::class)->for($document);
        abort_unless($delivery->document_version && $delivery->document_hash && hash_equals($delivery->document_version, $currentFingerprint['version']) && hash_equals($delivery->document_hash, $currentFingerprint['hash']), 409, __('This shared document has been replaced by a newer version.'));
        $delivery->update(['last_downloaded_at' => now()]);
        if ($document->customer) {
            CustomerPortalEvent::record($document->customer, 'document_downloaded', null, $request, $document, ['number' => $document->number, 'delivery_id' => $delivery->id]);
        }

        return response($pdf->render($document->load('lines'), $kind), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$document->number.'.pdf"']);
    }

    private function send(Request $request, object $document, string $kind, DocumentPdf $pdf, ?QuoteStatusWorkflow $quoteWorkflow = null): RedirectResponse
    {
        $snapshot = $document->customer_snapshot ?? [];
        $default = $snapshot['billing_email'] ?? $snapshot['email'] ?? null;
        $data = $request->validate(['recipient_email' => ['required', 'email', 'max:255']]);
        $token = Str::random(64);
        $expires = now()->addDays($kind === 'quote' ? 30 : 90);
        $relation = $document instanceof Quote ? 'quote_id' : 'sales_document_id';
        $fingerprint = app(DocumentFingerprint::class)->for($document);
        try {
            $pdfData = $pdf->render($document, $kind, final: true);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['recipient_email' => __('The document PDF could not be generated.')]);
        }
        $delivery = DocumentDelivery::create([$relation => $document->id, 'sent_by' => $request->user()->id, 'recipient_email' => $data['recipient_email'] ?: $default, 'token_hash' => hash('sha256', $token), 'status' => 'pending', 'document_version' => $fingerprint['version'], 'document_hash' => $fingerprint['hash'], 'expires_at' => $expires]);
        try {
            if ($document instanceof Quote) {
                app(QuoteDeliveryArchive::class)->store($delivery, $pdfData);
            }
            Mail::to($delivery->recipient_email)->send(new DocumentSharedMail($document, $kind, route('documents.shared.show', $token), $expires->format('d/m/Y'), $pdfData));
            $document->deliveries()->whereKeyNot($delivery->id)->whereIn('status', ['pending', 'sent', 'viewed'])->update(['status' => 'superseded']);
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
            if ($document instanceof Quote && $document->status === 'draft') {
                $quoteWorkflow?->apply($document, 'sent', $request->user(), 'email');
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

    private function delivery(string $token, bool $lock = false): DocumentDelivery
    {
        $query = DocumentDelivery::with(['quote', 'salesDocument'])->where('token_hash', hash('sha256', $token));
        if ($lock) {
            $query->lockForUpdate();
        }
        $delivery = $query->firstOrFail();
        abort_if($delivery->revoked_at || $delivery->expires_at->isPast() || in_array($delivery->status, ['superseded', 'failed', 'revoked'], true), 410, __('This document link has expired.'));

        return $delivery;
    }
}
