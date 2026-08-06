<?php

namespace App\Http\Controllers;

use App\Mail\CustomerPortalMail;
use App\Models\CompanyProfile;
use App\Models\CustomerDocumentAcceptance;
use App\Models\CustomerPortalAccess;
use App\Models\CustomerPortalEvent;
use App\Models\CustomerPortalPreference;
use App\Models\CustomerSatisfactionSurvey;
use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\ServiceTicket;
use App\Models\WorkOrderAttachment;
use App\Services\Documents\DocumentFingerprint;
use App\Services\Documents\DocumentPdf;
use App\Services\Quotes\QuoteStatusWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPortalController extends Controller
{
    public function invite(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->category === 'customer', 422);
        $data = $request->validate(['recipient_email' => ['required', 'email', 'max:255'], 'expires_days' => ['nullable', 'integer', 'min:1', 'max:365']]);
        $token = Str::random(64);
        $expires = now()->addDays($data['expires_days'] ?? 90);
        $person->portalAccesses()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $access = CustomerPortalAccess::create(['person_id' => $person->id, 'created_by' => $request->user()->id, 'recipient_email' => $data['recipient_email'], 'token_hash' => hash('sha256', $token), 'expires_at' => $expires]);
        $name = $person->company_name ?: trim($person->first_name.' '.$person->last_name);
        try {
            Mail::to($access->recipient_email)->send(new CustomerPortalMail($name, route('customer-portal.show', $token), $expires->format('d/m/Y')));
            $access->update(['sent_at' => now()]);
            CustomerPortalEvent::record($person, 'portal_invited', $access, $request, null, ['recipient_email' => $access->recipient_email, 'expires_at' => $expires->toIso8601String()]);
        } catch (\Throwable $exception) {
            report($exception);
            $access->update(['revoked_at' => now()]);

            return back()->withErrors(['recipient_email' => __('The portal invitation could not be sent.')]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Customer portal invitation sent.')]);
    }

    public function revoke(Request $request, Person $person, CustomerPortalAccess $access): RedirectResponse
    {
        abort_unless($access->person_id === $person->id, 404);
        $access->update(['revoked_at' => now()]);
        CustomerPortalEvent::record($person, 'portal_access_revoked', $access, $request, null, ['recipient_email' => $access->recipient_email]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Portal access revoked.')]);
    }

    public function show(Request $request, string $token, DocumentFingerprint $fingerprints): Response
    {
        $access = $this->access($token);
        $customer = $access->customer;
        $installationIds = $customer->installations()->pluck('id');
        $installations = $customer->installations()->get()->map(fn ($item) => [...$item->only(['id', 'service_type', 'status', 'project_reference', 'site_name', 'site_city', 'completed_date', 'system_power_kw']), 'monitoring' => $this->monitoring($item)]);
        $quotes = Quote::with('lines')->where('person_id', $customer->id)->whereIn('status', ['sent', 'accepted', 'rejected', 'expired'])->latest()->get();
        $documents = SalesDocument::with('lines')->where('person_id', $customer->id)->where('status', '!=', 'draft')->latest('issue_date')->get();
        $acceptances = CustomerDocumentAcceptance::where('person_id', $customer->id)->get()->keyBy(fn ($item) => $item->document_type.'-'.$item->document_id.'-'.$item->document_version);
        $preference = CustomerPortalPreference::firstOrNew(['person_id' => $customer->id]);
        $surveyedTickets = CustomerSatisfactionSurvey::where('person_id', $customer->id)->whereNotNull('service_ticket_id')->pluck('service_ticket_id');
        CustomerPortalEvent::record($customer, 'portal_accessed', $access, $request);

        return Inertia::render('portal/index', [
            'company' => CompanyProfile::first()?->only(['legal_name', 'trade_name', 'email', 'phone']),
            'customer' => $customer->only(['first_name', 'last_name', 'company_name', 'email']), 'token' => $token,
            'installations' => $installations,
            'quotes' => $quotes->map(function (Quote $quote) use ($fingerprints, $acceptances) {
                $fingerprint = $fingerprints->for($quote);

                return [...$quote->only(['id', 'number', 'version', 'status', 'issue_date', 'valid_until', 'amount']), 'document_version' => $fingerprint['version'], 'accepted_at' => $acceptances->get('quote-'.$quote->id.'-'.$fingerprint['version'])?->accepted_at?->toIso8601String(), 'can_accept' => in_array($quote->status, ['sent'], true)];
            }),
            'documents' => $documents->map(function (SalesDocument $document) use ($fingerprints, $acceptances) {
                $fingerprint = $fingerprints->for($document);

                return [...$document->only(['id', 'type', 'number', 'status', 'issue_date', 'due_date', 'amount', 'paid_amount']), 'document_version' => $fingerprint['version'], 'accepted_at' => $acceptances->get('sales-'.$document->id.'-'.$fingerprint['version'])?->accepted_at?->toIso8601String(), 'can_accept' => ! in_array($document->status, ['draft', 'cancelled'], true)];
            }),
            'tickets' => ServiceTicket::whereIn('installation_id', $installationIds)->latest()->get(['id', 'installation_id', 'number', 'kind', 'priority', 'status', 'title', 'description', 'resolution', 'created_at', 'updated_at', 'resolved_at']),
            'maintenance' => MaintenancePlan::whereIn('installation_id', $installationIds)->where('is_active', true)->orderBy('next_due_on')->get(['id', 'installation_id', 'name', 'next_due_on', 'last_completed_on']),
            'certificates' => WorkOrderAttachment::query()->where('kind', 'document')->whereHas('workOrder.installation', fn ($query) => $query->where('person_id', $customer->id))->with('workOrder:id,installation_id,number')->latest()->get()->map(fn ($item) => ['id' => $item->id, 'name' => $item->original_name, 'caption' => $item->caption, 'size' => $item->size, 'work_order' => $item->workOrder->number, 'created_at' => $item->created_at->toIso8601String()]),
            'timeline' => $customer->portalEvents()->latest('occurred_at')->limit(30)->get()->map(fn ($event) => ['id' => $event->id, 'type' => $event->event_type, 'title' => $this->eventTitle($event->event_type, $event->metadata ?? []), 'occurred_at' => $event->occurred_at->toIso8601String()]),
            'preferences' => ['email_documents' => $preference->exists ? $preference->email_documents : true, 'email_tickets' => $preference->exists ? $preference->email_tickets : true, 'email_maintenance' => $preference->exists ? $preference->email_maintenance : true],
            'surveyed_ticket_ids' => $surveyedTickets,
            'has_sale_survey' => CustomerSatisfactionSurvey::where('person_id', $customer->id)->where('context', 'sale')->exists(),
        ]);
    }

    public function storeTicket(Request $request, string $token): RedirectResponse
    {
        $access = $this->access($token);
        $data = $request->validate(['installation_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:3000'], 'priority' => ['required', 'in:low,normal,high']]);
        abort_unless($access->customer->installations()->whereKey($data['installation_id'])->exists(), 403);
        $ticket = ServiceTicket::create([...$data, 'number' => 'SAT-'.now()->format('Y').'-'.str_pad((string) (ServiceTicket::count() + 1), 5, '0', STR_PAD_LEFT), 'kind' => 'incident', 'status' => 'open']);
        CustomerPortalEvent::record($access->customer, 'ticket_opened', $access, $request, $ticket, ['number' => $ticket?->number, 'title' => $data['title']]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Support request created.')]);
    }

    public function pdf(Request $request, string $token, string $kind, int $document, DocumentPdf $pdf): HttpResponse
    {
        $access = $this->access($token);
        if ($kind === 'quote') {
            $item = Quote::where('person_id', $access->person_id)->findOrFail($document);
            $pdfKind = 'quote';
        } else {
            $item = SalesDocument::where('person_id', $access->person_id)->findOrFail($document);
            $pdfKind = $item->type;
        }
        CustomerPortalEvent::record($access->customer, 'document_downloaded', $access, $request, $item, ['number' => $item->number]);

        return response($pdf->render($item->load('lines'), $pdfKind), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$item->number.'.pdf"']);
    }

    public function accept(Request $request, string $token, string $kind, int $document, DocumentFingerprint $fingerprints, QuoteStatusWorkflow $workflow): RedirectResponse
    {
        $access = $this->access($token);
        abort_unless(in_array($kind, ['quote', 'sales'], true), 404);
        $item = $kind === 'quote'
            ? Quote::with('lines')->where('person_id', $access->person_id)->findOrFail($document)
            : SalesDocument::with('lines')->where('person_id', $access->person_id)->findOrFail($document);
        abort_if($kind === 'quote' ? ! in_array($item->status, ['sent', 'accepted'], true) : in_array($item->status, ['draft', 'cancelled'], true), 422, __('This document cannot be accepted.'));
        $fingerprint = $fingerprints->for($item);

        DB::transaction(function () use ($access, $request, $kind, $item, $fingerprint, $workflow): void {
            $acceptance = CustomerDocumentAcceptance::firstOrCreate([
                'person_id' => $access->person_id,
                'document_type' => $kind,
                'document_id' => $item->id,
                'document_version' => $fingerprint['version'],
            ], [
                'customer_portal_access_id' => $access->id,
                'document_hash' => $fingerprint['hash'],
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            ]);
            if ($acceptance->wasRecentlyCreated && $item instanceof Quote && $item->status === 'sent') {
                $workflow->apply($item, 'accepted');
            }
            if ($acceptance->wasRecentlyCreated) {
                CustomerPortalEvent::record($access->customer, 'document_accepted', $access, $request, $item, ['number' => $item->number, 'version' => $fingerprint['version']]);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Document accepted.')]);
    }

    public function updatePreferences(Request $request, string $token): RedirectResponse
    {
        $access = $this->access($token);
        $data = $request->validate(['email_documents' => ['required', 'boolean'], 'email_tickets' => ['required', 'boolean'], 'email_maintenance' => ['required', 'boolean']]);
        CustomerPortalPreference::updateOrCreate(['person_id' => $access->person_id], $data);
        CustomerPortalEvent::record($access->customer, 'preferences_updated', $access, $request, null, $data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Notification preferences updated.')]);
    }

    public function storeSurvey(Request $request, string $token): RedirectResponse
    {
        $access = $this->access($token);
        $data = $request->validate(['service_ticket_id' => ['nullable', 'integer'], 'installation_id' => ['nullable', 'integer'], 'context' => ['required', 'in:sale,intervention'], 'score' => ['required', 'integer', 'between:1,5'], 'comments' => ['nullable', 'string', 'max:2000']]);
        abort_unless(($data['service_ticket_id'] ?? null) || ($data['installation_id'] ?? null), 422);
        $ticket = null;
        if ($data['service_ticket_id'] ?? null) {
            $ticket = ServiceTicket::whereKey($data['service_ticket_id'])->whereNotNull('resolved_at')->whereHas('installation', fn ($query) => $query->where('person_id', $access->person_id))->firstOrFail();
            abort_if(CustomerSatisfactionSurvey::where('person_id', $access->person_id)->where('service_ticket_id', $ticket->id)->exists(), 422, __('This survey has already been completed.'));
            $data['installation_id'] = $ticket->installation_id;
        } elseif ($data['installation_id'] ?? null) {
            abort_unless($access->customer->installations()->whereKey($data['installation_id'])->exists(), 403);
            abort_if($data['context'] === 'sale' && CustomerSatisfactionSurvey::where('person_id', $access->person_id)->where('context', 'sale')->exists(), 422, __('This survey has already been completed.'));
        }
        $survey = CustomerSatisfactionSurvey::create([...$data, 'person_id' => $access->person_id, 'customer_portal_access_id' => $access->id, 'submitted_at' => now()]);
        CustomerPortalEvent::record($access->customer, 'survey_submitted', $access, $request, $survey, ['score' => $survey->score, 'context' => $survey->context]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Thank you for your feedback.')]);
    }

    public function certificate(Request $request, string $token, WorkOrderAttachment $attachment): StreamedResponse
    {
        $access = $this->access($token);
        abort_unless($attachment->kind === 'document' && $attachment->workOrder()->whereHas('installation', fn ($query) => $query->where('person_id', $access->person_id))->exists(), 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        CustomerPortalEvent::record($access->customer, 'certificate_downloaded', $access, $request, $attachment, ['name' => $attachment->original_name]);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type]);
    }

    private function access(string $token): CustomerPortalAccess
    {
        $access = CustomerPortalAccess::with('customer')->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($access->revoked_at || $access->expires_at->isPast(), 410, __('This portal link has expired.'));
        $access->update(['first_accessed_at' => $access->first_accessed_at ?: now(), 'last_accessed_at' => now()]);

        return $access;
    }

    private function monitoring(Installation $item): array
    {
        $provider = $item->huawei_station_code ? 'huawei' : ($item->deye_station_id ? 'deye' : null);

        return ['provider' => $provider, 'status' => $provider ? ($item->{$provider.'_status'} ?: 'unknown') : 'not_connected', 'current_power_kw' => $provider ? $item->{$provider.'_current_power_kw'} : null, 'today_energy_kwh' => $provider ? $item->{$provider.'_today_energy_kwh'} : null, 'synced_at' => $provider ? $item->{$provider.'_synced_at'} : null];
    }

    private function eventTitle(string $type, array $metadata): string
    {
        return match ($type) {
            'portal_invited' => __('Portal invitation sent to :email', ['email' => $metadata['recipient_email'] ?? '']),
            'portal_accessed' => __('Customer accessed the portal'),
            'portal_access_revoked' => __('Portal access revoked'),
            'ticket_opened' => __('Support ticket :number opened', ['number' => $metadata['number'] ?? '']),
            'document_shared' => __('Document :number shared', ['number' => $metadata['number'] ?? '']),
            'document_viewed' => __('Document :number viewed', ['number' => $metadata['number'] ?? '']),
            'document_downloaded' => __('Document :number downloaded', ['number' => $metadata['number'] ?? '']),
            'certificate_downloaded' => __('Certificate :name downloaded', ['name' => $metadata['name'] ?? '']),
            'document_accepted' => __('Document :number accepted', ['number' => $metadata['number'] ?? '']),
            'document_rejected' => __('Document :number rejected', ['number' => $metadata['number'] ?? '']),
            'document_revoked' => __('Shared document :number revoked', ['number' => $metadata['number'] ?? '']),
            'preferences_updated' => __('Notification preferences updated'),
            'survey_submitted' => __('Satisfaction survey submitted (:score/5)', ['score' => $metadata['score'] ?? '']),
            default => Str::headline($type),
        };
    }
}
