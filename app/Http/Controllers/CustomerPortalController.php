<?php

namespace App\Http\Controllers;

use App\Mail\CustomerPortalMail;
use App\Models\CompanyProfile;
use App\Models\CustomerPortalAccess;
use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\ServiceTicket;
use App\Services\Documents\DocumentPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CustomerPortalController extends Controller
{
    public function invite(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->category === 'customer', 422);
        $data = $request->validate(['recipient_email' => ['required', 'email', 'max:255']]);
        $token = Str::random(64);
        $expires = now()->addDays(90);
        $person->portalAccesses()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $access = CustomerPortalAccess::create(['person_id' => $person->id, 'created_by' => $request->user()->id, 'recipient_email' => $data['recipient_email'], 'token_hash' => hash('sha256', $token), 'expires_at' => $expires]);
        $name = $person->company_name ?: trim($person->first_name.' '.$person->last_name);
        try {
            Mail::to($access->recipient_email)->send(new CustomerPortalMail($name, route('customer-portal.show', $token), $expires->format('d/m/Y')));
            $access->update(['sent_at' => now()]);
        } catch (\Throwable $exception) {
            report($exception);
            $access->update(['revoked_at' => now()]);

            return back()->withErrors(['recipient_email' => __('The portal invitation could not be sent.')]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Customer portal invitation sent.')]);
    }

    public function revoke(Person $person, CustomerPortalAccess $access): RedirectResponse
    {
        abort_unless($access->person_id === $person->id, 404);
        $access->update(['revoked_at' => now()]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Portal access revoked.')]);
    }

    public function show(string $token): Response
    {
        $access = $this->access($token);
        $customer = $access->customer;
        $installationIds = $customer->installations()->pluck('id');
        $installations = $customer->installations()->get()->map(fn ($item) => [...$item->only(['id', 'service_type', 'status', 'project_reference', 'site_name', 'site_city', 'completed_date', 'system_power_kw']), 'monitoring' => $this->monitoring($item)]);

        return Inertia::render('portal/index', [
            'company' => CompanyProfile::first()?->only(['legal_name', 'trade_name', 'email', 'phone']),
            'customer' => $customer->only(['first_name', 'last_name', 'company_name', 'email']), 'token' => $token,
            'installations' => $installations,
            'quotes' => Quote::where('person_id', $customer->id)->whereIn('status', ['sent', 'accepted', 'rejected', 'expired'])->latest()->get(['id', 'number', 'status', 'issue_date', 'valid_until', 'amount']),
            'documents' => SalesDocument::where('person_id', $customer->id)->where('status', '!=', 'draft')->latest('issue_date')->get(['id', 'type', 'number', 'status', 'issue_date', 'due_date', 'amount', 'paid_amount']),
            'tickets' => ServiceTicket::whereIn('installation_id', $installationIds)->latest()->get(['id', 'installation_id', 'number', 'kind', 'priority', 'status', 'title', 'created_at', 'resolved_at']),
            'maintenance' => MaintenancePlan::whereIn('installation_id', $installationIds)->where('is_active', true)->orderBy('next_due_on')->get(['id', 'installation_id', 'name', 'next_due_on', 'last_completed_on']),
        ]);
    }

    public function storeTicket(Request $request, string $token): RedirectResponse
    {
        $access = $this->access($token);
        $data = $request->validate(['installation_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:3000'], 'priority' => ['required', 'in:low,normal,high']]);
        abort_unless($access->customer->installations()->whereKey($data['installation_id'])->exists(), 403);
        ServiceTicket::create([...$data, 'number' => 'SAT-'.now()->format('Y').'-'.str_pad((string) (ServiceTicket::count() + 1), 5, '0', STR_PAD_LEFT), 'kind' => 'incident', 'status' => 'open']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Support request created.')]);
    }

    public function pdf(string $token, string $kind, int $document, DocumentPdf $pdf): HttpResponse
    {
        $access = $this->access($token);
        if ($kind === 'quote') {
            $item = Quote::where('person_id', $access->person_id)->findOrFail($document);
            $pdfKind = 'quote';
        } else {
            $item = SalesDocument::where('person_id', $access->person_id)->findOrFail($document);
            $pdfKind = $item->type;
        }

        return response($pdf->render($item->load('lines'), $pdfKind), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$item->number.'.pdf"']);
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
}
