<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\InstallationAsset;
use App\Models\MaintenancePlan;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\ServiceSlaPolicy;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrderTemplate;
use App\Services\AfterSales\MaintenancePlanLifecycle;
use App\Services\AfterSales\ServiceTicketFactory;
use App\Services\AfterSales\ServiceWorkGenerator;
use App\Services\AfterSales\SlaManager;
use App\Services\Analytics\RecurringServiceEconomics;
use App\Services\Planning\RecurringServiceCapacityForecast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AfterSalesController extends Controller
{
    public function index(Request $request, RecurringServiceCapacityForecast $capacity, RecurringServiceEconomics $economics): Response
    {
        if ($request->query('view') === 'installations') {
            return Inertia::render('after-sales/overview', app(\App\Services\Projects\ProjectWorkspace::class)->afterSales($request));
        }

        return $this->renderWorkspace($request, $capacity, $economics, 'tickets');
    }

    public function maintenance(Request $request, RecurringServiceCapacityForecast $capacity, RecurringServiceEconomics $economics): Response
    {
        return $this->renderWorkspace($request, $capacity, $economics, 'maintenance');
    }

    public function assets(Request $request, RecurringServiceCapacityForecast $capacity, RecurringServiceEconomics $economics): Response
    {
        return $this->renderWorkspace($request, $capacity, $economics, 'assets');
    }

    public function contracts(Request $request, RecurringServiceCapacityForecast $capacity, RecurringServiceEconomics $economics): Response
    {
        return $this->renderWorkspace($request, $capacity, $economics, 'contracts');
    }

    private function renderWorkspace(Request $request, RecurringServiceCapacityForecast $capacity, RecurringServiceEconomics $economics, string $section): Response
    {
        $context = $request->validate(['installation' => ['nullable', 'integer', 'exists:installations,id']]);
        $installationId = $context['installation'] ?? null;
        $showsTickets = $section === 'tickets';
        $showsPlans = in_array($section, ['maintenance', 'contracts'], true);
        $showsAssets = $section === 'assets';

        $tickets = $showsTickets
            ? ServiceTicket::with(['installation:id,project_reference,site_name,service_type', 'assignedUser:id,name', 'workOrder:id,number,status'])
                ->when($request->user()->hasRole('technician'), fn ($query) => $query->where(fn ($query) => $query->where('assigned_user_id', $request->user()->id)->orWhereNull('assigned_user_id')))
                ->when($installationId, fn ($query) => $query->where('installation_id', $installationId))
                ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
                ->orderByRaw("case when status in ('resolved','closed') then 1 else 0 end")
                ->latest()
                ->get()
            : collect();
        $plans = $showsPlans
            ? MaintenancePlan::with([
                'installation:id,project_reference,site_name', 'assignedUser:id,name', 'template:id,name,kind',
                'coveredAssets:id,installation_id,asset_tag,name,category', 'preventiveMaterials.product:id,sku,name,supply_criticality',
                'versions' => fn ($query) => $query->select(['id', 'maintenance_plan_id', 'version', 'change_note', 'created_by', 'created_at']),
                'versions.creator:id,name', 'cycleCommitments.approver:id,name',
                'cycleCommitments.completer:id,name',
                'cycleCommitments.billing.invoice:id,number,status,amount,paid_amount,due_date',
                'cycleCommitments.billing.preparer:id,name',
                'cycleCommitments.communications.confirmer:id,name',
                'cycleCommitments.communications.sender:id,name',
                'cycleCommitments.serviceTicket:id,number,status',
                'cycleCommitments.workOrder:id,number,status,scheduled_start,scheduled_end',
                'cycleCommitments.scheduleRevisions',
                'renewalOpportunity:id,code,title,stage',
            ])
                ->when($request->user()->hasRole('technician'), fn ($query) => $query->where(fn ($scope) => $scope->where('assigned_user_id', $request->user()->id)->orWhereNull('assigned_user_id')))
                ->when($installationId, fn ($query) => $query->where('installation_id', $installationId))
                ->orderBy('next_due_on')
                ->get()
            : collect();
        $plans->each(fn (MaintenancePlan $plan) => $plan->cycleCommitments->each(function ($commitment): void {
            $snapshot = $commitment->completion_snapshot;
            $commitment->setAttribute('completion_summary', $snapshot ? [
                'completed_at' => $commitment->completed_at?->toIso8601String(),
                'checklist_completed' => $snapshot['checklist']['completed'] ?? 0,
                'checklist_required' => $snapshot['checklist']['required'] ?? 0,
                'evidence_total' => $snapshot['evidence']['total'] ?? 0,
                'pending_documents' => $snapshot['evidence']['pending_documents'] ?? 0,
                'consumed_quantity' => $snapshot['materials']['consumed_quantity'] ?? 0,
                'minutes' => $snapshot['time']['minutes'] ?? 0,
                'travel_km' => $snapshot['time']['travel_km'] ?? 0,
                'assets_serviced' => $snapshot['assets']['serviced'] ?? 0,
                'next_due_on' => $snapshot['continuity']['next_due_on_after'] ?? null,
            ] : null);
        }));
        if (! $request->user()->can('view-finance')) {
            $plans->each(function (MaintenancePlan $plan): void {
                $plan->makeHidden(['billing_frequency_months', 'billing_amount', 'next_billing_on']);
                $plan->cycleCommitments->each->makeHidden(['planned_revenue', 'planned_labor_cost', 'planned_material_cost', 'planned_travel_cost']);
                $plan->cycleCommitments->each(fn ($commitment) => $commitment->unsetRelation('billing'));
            });
        }
        if (! $request->user()->can('manage-customers')) {
            $plans->each(fn (MaintenancePlan $plan) => $plan->cycleCommitments->each(
                fn ($commitment) => $commitment->unsetRelation('communications'),
            ));
        }

        $installations = Installation::query()
            ->with(['customer:id,first_name,last_name,company_name'])
            ->when($showsPlans || $showsAssets, fn ($query) => $query->with([
                'assets:id,installation_id,asset_tag,name,category,status,manufacturer,model,serial_number,last_serviced_at,service_count,warranty_ends_on',
            ]))
            ->orderBy('project_reference')
            ->get(['id', 'person_id', 'project_reference', 'site_name', 'service_type', 'status']);
        $warranties = $showsAssets
            ? SerialNumber::with(['product:id,name,sku'])->whereNotNull('installation_id')->orderBy('warranty_ends_on')->get()
            : collect();

        $page = match ($section) {
            'maintenance' => 'maintenance/index',
            'assets' => 'assets/index',
            'contracts' => 'contracts-services/index',
            default => 'after-sales/index',
        };

        return Inertia::render($page, [
            'initialSection' => $section,
            'tickets' => $tickets,
            'plans' => $plans,
            'installations' => $installations,
            'users' => ($showsTickets || $showsPlans) ? User::whereIn('role', ['technician', 'admin'])->orderBy('name')->get(['id', 'name']) : [],
            'warranties' => $warranties,
            'slaPolicies' => $showsTickets ? ServiceSlaPolicy::where('is_active', true)->orderBy('priority')->get() : [],
            'templates' => $showsPlans ? WorkOrderTemplate::where('is_active', true)->whereIn('kind', ['maintenance', 'repair'])->orderBy('name')->get() : [],
            'stockProducts' => $showsPlans && $request->user()->can('manage-installations')
                ? Product::where('is_active', true)->where('track_stock', true)->orderBy('name')->get(['id', 'sku', 'name', 'supply_criticality'])
                : [],
            'metrics' => [
                'open' => $tickets->whereNotIn('status', ['resolved', 'closed'])->count(),
                'response_breaches' => $tickets->whereNull('first_responded_at')->filter(fn (ServiceTicket $ticket) => $ticket->first_response_due_at?->isPast())->count(),
                'resolution_breaches' => $tickets->whereNotIn('status', ['resolved', 'closed'])->filter(fn (ServiceTicket $ticket) => $ticket->resolution_due_at?->isPast())->count(),
                'average_first_response_minutes' => round($tickets->filter(fn (ServiceTicket $ticket) => $ticket->first_responded_at)->avg(fn (ServiceTicket $ticket) => $ticket->created_at->diffInMinutes($ticket->first_responded_at)) ?? 0),
                'average_resolution_minutes' => round($tickets->filter(fn (ServiceTicket $ticket) => $ticket->resolved_at)->avg(fn (ServiceTicket $ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at)) ?? 0),
                'recurrences' => $tickets->sum('reopened_count'),
                'satisfaction' => round($tickets->whereNotNull('satisfaction_rating')->avg('satisfaction_rating') ?? 0, 1),
                'annual_recurring_value' => $showsPlans && $request->user()->can('view-finance')
                    ? round((float) $plans->where('is_active', true)->sum(fn (MaintenancePlan $plan) => (float) ($plan->billing_amount ?: 0) * (12 / max(1, (int) ($plan->billing_frequency_months ?: 12)))), 2)
                    : null,
                'renewals_due' => $plans->where('is_active', true)->whereNull('renewal_opportunity_id')->filter(fn (MaintenancePlan $plan) => $plan->contract_ends_on?->lessThanOrEqualTo(today()->addDays($plan->renewal_notice_days)))->count(),
                'installed_assets' => $showsAssets ? $installations->sum(fn (Installation $installation) => $installation->assets->count()) : 0,
                'warranties_expiring' => $warranties->filter(fn (SerialNumber $serial) => $serial->warranty_ends_on?->between(today(), today()->addDays(90)))->count(),
            ],
            'canManage' => $request->user()->can('manage-installations'),
            'canManageCustomers' => $request->user()->can('manage-customers'),
            'serviceCapacity' => $section === 'maintenance' ? $capacity->forecast(today(), 6, $request->user()) : null,
            'serviceEconomics' => $section === 'contracts' && $request->user()->can('view-finance') ? $economics->build(true) : null,
            'canViewFinancials' => $request->user()->can('view-finance'),
            'canManageBilling' => $request->user()->can('manage-treasury'),
        ]);
    }

    public function storeTicket(Request $request, SlaManager $sla, ServiceWorkGenerator $work, ServiceTicketFactory $tickets): RedirectResponse
    {
        $data = $request->validate(['installation_id' => ['required', 'exists:installations,id'], 'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['technician', 'admin']))], 'kind' => ['required', Rule::in(['incident', 'maintenance', 'warranty', 'inspection'])], 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string']]);
        if ($request->user()->hasRole('technician')) {
            $data['assigned_user_id'] = $request->user()->id;
        }
        $ticket = $tickets->create([...$data, 'status' => 'open'], $request->user()->id);
        $sla->apply($ticket);
        $work->createForTicket($ticket, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Service ticket created.')]);
    }

    public function updateTicket(Request $request, ServiceTicket $ticket): RedirectResponse
    {
        $this->ensureAccess($request, $ticket);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'assigned', 'in_progress', 'resolved', 'closed'])], 'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['technician', 'admin']))], 'resolution' => ['nullable', 'string'], 'satisfaction_rating' => ['nullable', 'integer', 'between:1,5']]);
        if ($request->user()->hasRole('technician')) {
            $data['assigned_user_id'] = $request->user()->id;
        }
        abort_if(in_array($data['status'], ['resolved', 'closed'], true) && blank($data['resolution']), 422, __('Resolution is required.'));
        DB::transaction(function () use ($ticket, $data): void {
            if ($ticket->maintenance_plan_id) {
                MaintenancePlan::query()->lockForUpdate()->findOrFail($ticket->maintenance_plan_id);
            }
            $locked = ServiceTicket::query()->with(['maintenancePlan', 'serviceCycleCommitment'])->lockForUpdate()->findOrFail($ticket->id);
            $wasResolved = in_array($locked->status, ['resolved', 'closed'], true);
            $isResolved = in_array($data['status'], ['resolved', 'closed'], true);
            if ($isResolved && ! $wasResolved && $locked->serviceCycleCommitment && ! $locked->serviceCycleCommitment->completed_at) {
                throw ValidationException::withMessages(['status' => __('Complete the governed work order to close this recurring service ticket.')]);
            }
            $locked->update([
                ...$data,
                'first_responded_at' => $locked->first_responded_at ?: (in_array($data['status'], ['assigned', 'in_progress', 'resolved', 'closed'], true) ? now() : null),
                'reopened_count' => $wasResolved && ! $isResolved ? $locked->reopened_count + 1 : $locked->reopened_count,
                'resolved_at' => $isResolved ? ($locked->resolved_at ?: now()) : null,
            ]);
            if ($locked->maintenancePlan && ! $locked->serviceCycleCommitment && ! $wasResolved && $isResolved) {
                $nextDue = $locked->maintenancePlan->next_due_on->copy();
                do {
                    $nextDue = $nextDue->addMonthsNoOverflow($locked->maintenancePlan->interval_months);
                } while ($nextDue->lessThanOrEqualTo(today()));
                $locked->maintenancePlan->update(['last_completed_on' => today(), 'next_due_on' => $nextDue]);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Service ticket updated.')]);
    }

    public function createWorkOrder(Request $request, ServiceTicket $ticket, ServiceWorkGenerator $generator): RedirectResponse
    {
        $this->ensureAccess($request, $ticket);
        abort_if($ticket->work_order_id, 422, __('A work order already exists.'));
        $order = $generator->createForTicket($ticket, $request->user()->id);

        return to_route('work-orders.index', ['work_order' => $order->id])->with('toast', ['type' => 'success', 'message' => __('Work order created.')]);
    }

    public function storePlan(Request $request, MaintenancePlanLifecycle $lifecycle): RedirectResponse
    {
        $this->normalisePlanSelects($request);
        $data = $this->validatePlan($request);
        $this->validatePlanDates($data);
        $assetIds = $this->coveredAssetIds($data['installation_id'], $data['covered_asset_ids'] ?? []);
        unset($data['covered_asset_ids']);
        $lifecycle->create($data, $assetIds, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Maintenance plan created.')]);
    }

    public function updatePlan(Request $request, MaintenancePlan $plan, MaintenancePlanLifecycle $lifecycle): RedirectResponse
    {
        $this->normalisePlanSelects($request);
        $data = $this->validatePlan($request, true);
        $changeNote = $data['change_note'];
        unset($data['change_note']);
        $assetIds = array_key_exists('covered_asset_ids', $data)
            ? $this->coveredAssetIds((int) ($data['installation_id'] ?? $plan->installation_id), $data['covered_asset_ids'])
            : null;
        unset($data['covered_asset_ids']);
        $this->validatePlanDates($data, $plan);
        $lifecycle->revise($plan, $data, $assetIds, $changeNote, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Maintenance plan updated.')]);
    }

    public function prepareRenewal(Request $request, MaintenancePlan $plan, MaintenancePlanLifecycle $lifecycle): RedirectResponse
    {
        $opportunity = $lifecycle->prepareRenewal($plan, $request->user());

        return to_route('opportunities.show', $opportunity)->with('toast', ['type' => 'success', 'message' => __('Renewal opportunity prepared for human review.')]);
    }

    public function updateWarranty(Request $request, SerialNumber $serial): RedirectResponse
    {
        abort_unless($serial->installation_id, 422);
        $data = $request->validate(['warranty_starts_on' => ['nullable', 'date'], 'warranty_ends_on' => ['nullable', 'date', 'after_or_equal:warranty_starts_on']]);
        $serial->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Warranty updated.')]);
    }

    public function storeSlaPolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'customer_type' => ['nullable', 'string', 'max:30'],
            'first_response_minutes' => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
            'escalation_minutes' => ['required', 'integer', 'min:1'],
        ]);
        ServiceSlaPolicy::create([...$data, 'is_active' => true]);

        return back()->with('toast', ['type' => 'success', 'message' => __('SLA policy created.')]);
    }

    private function ensureAccess(Request $request, ServiceTicket $ticket): void
    {
        if ($request->user()->hasRole('technician')) {
            abort_unless(! $ticket->assigned_user_id || $ticket->assigned_user_id === $request->user()->id, 403);
        }
    }

    private function normalisePlanSelects(Request $request): void
    {
        foreach (['assigned_user_id', 'work_order_template_id', 'billing_frequency_months'] as $field) {
            if ($request->input($field) === 'none') {
                $request->merge([$field => null]);
            }
        }
    }

    private function validatePlan(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? ['sometimes'] : [];

        return $request->validate([
            'installation_id' => [...$sometimes, 'required', 'exists:installations,id'],
            'work_order_template_id' => [...$sometimes, 'nullable', 'exists:work_order_templates,id'],
            'assigned_user_id' => [...$sometimes, 'nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['technician', 'admin']))],
            'name' => [...$sometimes, 'required', 'string', 'max:255'],
            'service_level' => [...$sometimes, 'required', Rule::in(['standard', 'priority', 'critical'])],
            'coverage_type' => [...$sometimes, 'required', Rule::in(['preventive', 'labour', 'parts_and_labour', 'full'])],
            'covered_asset_ids' => [...$sometimes, 'array'],
            'covered_asset_ids.*' => ['integer', 'distinct', 'exists:installation_assets,id'],
            'included_visits' => [...$sometimes, 'nullable', 'integer', 'between:0,365'],
            'response_minutes' => [...$sometimes, 'nullable', 'integer', 'between:1,525600'],
            'resolution_minutes' => [...$sometimes, 'nullable', 'integer', 'between:1,525600'],
            'interval_months' => [...$sometimes, 'required', 'integer', 'between:1,120'],
            'lead_days' => [...$sometimes, 'nullable', 'integer', 'between:0,90'],
            'starts_on' => [...$sometimes, 'required', 'date'],
            'contract_ends_on' => [...$sometimes, 'nullable', 'date'],
            'renewal_notice_days' => [...$sometimes, 'required', 'integer', 'between:1,365'],
            'billing_frequency_months' => [...$sometimes, 'nullable', 'required_with:billing_amount,next_billing_on', Rule::in([1, 3, 12])],
            'billing_amount' => [...$sometimes, 'nullable', 'required_with:billing_frequency_months,next_billing_on', 'numeric', 'between:0.01,9999999999999.99'],
            'next_billing_on' => [...$sometimes, 'nullable', 'required_with:billing_frequency_months,billing_amount', 'date'],
            'next_due_on' => [...$sometimes, 'required', 'date'],
            'generate_work_order' => [...$sometimes, 'boolean'],
            'scope' => [...$sometimes, 'nullable', 'string', 'max:10000'],
            'is_active' => [...$sometimes, 'boolean'],
            'change_note' => [$partial ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function validatePlanDates(array $data, ?MaintenancePlan $plan = null): void
    {
        $startsOn = isset($data['starts_on']) ? Carbon::parse($data['starts_on'])->startOfDay() : $plan?->starts_on;
        $nextDueOn = isset($data['next_due_on']) ? Carbon::parse($data['next_due_on'])->startOfDay() : $plan?->next_due_on;
        $contractEndsOn = isset($data['contract_ends_on']) ? Carbon::parse($data['contract_ends_on'])->startOfDay() : $plan?->contract_ends_on;
        if ($startsOn && $nextDueOn && $nextDueOn->lessThan($startsOn)) {
            throw ValidationException::withMessages(['next_due_on' => __('The next maintenance date cannot be before the plan start date.')]);
        }
        if ($startsOn && $contractEndsOn && $contractEndsOn->lessThan($startsOn)) {
            throw ValidationException::withMessages(['contract_ends_on' => __('The contract end date cannot be before the plan start date.')]);
        }
    }

    /** @param array<int, int|string> $assetIds */
    private function coveredAssetIds(int $installationId, array $assetIds): array
    {
        $ids = collect($assetIds)->map(fn ($id) => (int) $id)->unique()->values();
        abort_if(InstallationAsset::whereIn('id', $ids)->where('installation_id', '!=', $installationId)->exists(), 422, __('Covered assets must belong to the selected installation.'));

        return $ids->all();
    }
}
