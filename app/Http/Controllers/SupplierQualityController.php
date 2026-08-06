<?php

namespace App\Http\Controllers;

use App\Models\SupplierQualityIncident;
use App\Services\Documents\PurchaseWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierQualityController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'resolved'])],
            'severity' => ['nullable', Rule::in(['minor', 'major', 'critical'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = SupplierQualityIncident::query()
            ->with([
                'purchaseDocument:id,supplier_id,number',
                'purchaseDocument.supplier:id,legal_name,trade_name',
                'product:id,sku,name',
                'warehouse:id,code,name',
                'reporter:id,name',
                'resolver:id,name',
            ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->whereHas('purchaseDocument', fn ($query) => $query->where('number', 'like', "%{$search}%"))
                ->orWhereHas('purchaseDocument.supplier', fn ($query) => $query->where('legal_name', 'like', "%{$search}%")->orWhere('trade_name', 'like', "%{$search}%"))
                ->orWhereHas('product', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))));

        return Inertia::render('purchases/quality/index', [
            'incidents' => $query->latest()->paginate(25)->withQueryString(),
            'filters' => $filters,
            'counts' => [
                'open' => SupplierQualityIncident::where('status', 'open')->count(),
                'critical' => SupplierQualityIncident::where('status', 'open')->where('severity', 'critical')->count(),
                'resolved' => SupplierQualityIncident::where('status', 'resolved')->count(),
            ],
        ]);
    }

    public function resolve(Request $request, SupplierQualityIncident $incident, PurchaseWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'resolution' => ['required', Rule::in(['replacement', 'credit_note', 'refund', 'accepted_as_is', 'discarded', 'other'])],
            'resolution_notes' => ['required', 'string', 'max:2000'],
        ]);

        $incident = DB::transaction(function () use ($request, $incident, $data) {
            $incident = SupplierQualityIncident::lockForUpdate()->findOrFail($incident->id);
            abort_unless($incident->status === 'open', 422);
            $incident->update([
                ...$data,
                'status' => 'resolved',
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
            ]);

            return $incident;
        });

        $workflow->record($incident->purchaseDocument, 'quality_incident_resolved', $request->user()->id, null, null, [
            'incident_id' => $incident->id,
            'resolution' => $incident->resolution,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier quality incident resolved.')]);
    }

    public function reopen(Request $request, SupplierQualityIncident $incident, PurchaseWorkflow $workflow): RedirectResponse
    {
        $incident = DB::transaction(function () use ($incident) {
            $incident = SupplierQualityIncident::lockForUpdate()->findOrFail($incident->id);
            abort_unless($incident->status === 'resolved', 422);
            $incident->update([
                'status' => 'open',
                'resolution' => null,
                'resolution_notes' => null,
                'resolved_by' => null,
                'resolved_at' => null,
            ]);

            return $incident;
        });

        $workflow->record($incident->purchaseDocument, 'quality_incident_reopened', $request->user()->id, null, null, ['incident_id' => $incident->id]);

        return back()->with('toast', ['type' => 'warning', 'message' => __('Supplier quality incident reopened.')]);
    }
}
