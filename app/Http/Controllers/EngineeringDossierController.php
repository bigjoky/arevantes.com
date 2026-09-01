<?php

namespace App\Http\Controllers;

use App\Models\EngineeringDossier;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Services\Engineering\EngineeringDossierWorkflow;
use App\Support\BusinessLineCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EngineeringDossierController extends Controller
{
    public function index(Request $request): Response
    {
        $dossiers = EngineeringDossier::query()
            ->with([
                'opportunity:id,code,title,stage',
                'installation:id,project_reference,site_name,status',
                'creator:id,name', 'updater:id,name', 'approver:id,name', 'handoverActor:id,name',
                'versions' => fn ($query) => $query->select(['id', 'engineering_dossier_id', 'version', 'fingerprint', 'change_note', 'created_by', 'created_at']),
                'versions.creator:id,name',
                'events.actor:id,name',
            ])
            ->latest()
            ->limit(100)
            ->get();
        $dossiers->each(fn (EngineeringDossier $dossier) => $dossier->setAttribute(
            'current_fingerprint',
            $dossier->versions->firstWhere('version', $dossier->current_version)?->fingerprint,
        ));

        return Inertia::render('engineering/index', [
            'dossiers' => $dossiers,
            'opportunities' => Opportunity::query()
                ->whereNotIn('stage', ['won', 'lost'])
                ->latest()
                ->limit(200)
                ->get(['id', 'installation_id', 'code', 'title', 'stage']),
            'installations' => Installation::query()
                ->where('status', '!=', 'cancelled')
                ->latest()
                ->limit(200)
                ->get(['id', 'project_reference', 'site_name', 'service_type', 'status']),
            'canManage' => $request->user()->can('manage-installations'),
        ]);
    }

    public function store(Request $request, EngineeringDossierWorkflow $workflow): RedirectResponse
    {
        $this->normaliseTargets($request);
        $workflow->create($this->validated($request), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Technical dossier created and versioned.')]);
    }

    public function update(Request $request, EngineeringDossier $engineeringDossier, EngineeringDossierWorkflow $workflow): RedirectResponse
    {
        $this->normaliseTargets($request);
        $data = $this->validated($request);
        $expectedVersion = $request->validate(['expected_version' => ['required', 'integer', 'min:1']])['expected_version'];
        $changeNote = $request->validate(['change_note' => ['required', 'string', 'min:10', 'max:1000']])['change_note'];
        $workflow->revise($engineeringDossier, $data, $expectedVersion, $changeNote, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Technical dossier revised and versioned.')]);
    }

    public function transition(Request $request, EngineeringDossier $engineeringDossier, EngineeringDossierWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['submit', 'return', 'approve', 'handover'])],
            'expected_version' => ['required', 'integer', 'min:1'],
            'expected_fingerprint' => ['nullable', 'string', 'size:64'],
            'note' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $workflow->transition(
            $engineeringDossier,
            $data['action'],
            $data['expected_version'],
            $data['expected_fingerprint'] ?? null,
            $data['note'],
            $request->user(),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('Technical dossier status updated.')]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'installation_id' => ['nullable', 'integer', 'exists:installations,id'],
            'title' => ['required', 'string', 'max:255'],
            'business_line' => ['required', Rule::in(BusinessLineCatalog::INSTALLATION_TYPES)],
            'kind' => ['required', Rule::in(['survey', 'design', 'calculation', 'technical_report'])],
            'scope' => ['required', 'string', 'max:10000'],
            'site_conditions' => ['nullable', 'string', 'max:20000'],
            'calculations' => ['nullable', 'string', 'max:30000'],
            'proposed_solution' => ['nullable', 'string', 'max:30000'],
            'risks' => ['nullable', 'string', 'max:20000'],
            'standards' => ['nullable', 'string', 'max:10000'],
            'conclusions' => ['nullable', 'string', 'max:20000'],
        ]);
    }

    private function normaliseTargets(Request $request): void
    {
        $request->merge([
            'opportunity_id' => in_array($request->input('opportunity_id'), [null, '', 'none'], true) ? null : $request->input('opportunity_id'),
            'installation_id' => in_array($request->input('installation_id'), [null, '', 'none'], true) ? null : $request->input('installation_id'),
        ]);
    }
}
