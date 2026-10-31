<?php

namespace App\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\OperationalVehicle;
use App\Models\Opportunity;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Services\Crm\LeadResponseSla;
use App\Services\Crm\OpportunitySalesCycle;
use App\Services\Planning\WorkforceCapacityForecast;
use App\Services\Supply\CriticalMaterialRisk;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\VehicleAvailability;
use App\Services\Work\WorkOrderFactory;
use App\Support\Time\BusinessTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function index(Request $request, TechnicianSchedule $schedule, WorkforceCapacityForecast $capacityForecast, CriticalMaterialRisk $criticalMaterialRisk): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'pending', 'in_progress', 'blocked', 'completed', 'cancelled', 'all'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'type' => ['nullable', Rule::in(['task', 'visit', 'reminder', 'call', 'email', 'meeting'])],
            'owner' => ['nullable', 'integer'],
            'view' => ['nullable', Rule::in(['today', 'board', 'week', 'month'])],
            'week' => ['nullable', 'date_format:Y-m-d'],
            'focus' => ['nullable', 'integer'],
            'planning_request' => ['nullable', 'integer', Rule::exists('planning_scenario_requests', 'id')->where('area', 'operations')],
        ]);
        if (isset($filters['planning_request'])) {
            $filters['planning_request'] = (int) $filters['planning_request'];
        }
        $filters['status'] ??= 'active';
        $filters['view'] ??= 'today';

        $baseActivities = CrmActivity::query()
            ->with([
                'installation:id,project_reference,site_name,status',
                'person:id,first_name,last_name',
                'assignedUser:id,name,role',
                'workOrder:id,number,status',
            ]);
        $this->scopeForUser($baseActivities, $request);
        $this->applyFilters($baseActivities, $filters);
        if ($filters['view'] === 'today' && ! isset($filters['planning_request'])) {
            $baseActivities->when(
                $filters['focus'] ?? null,
                fn (Builder $query, int $focus) => $query->whereKey($focus),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('starts_at', '<=', BusinessTime::now()->endOfDay())
                    ->orWhere('due_at', '<=', BusinessTime::now()->endOfDay())
                    ->orWhere(fn (Builder $query) => $query->whereNull('starts_at')->whereNull('due_at')))
            );
        }

        $activities = (clone $baseActivities)
            ->orderByRaw("case when status = 'blocked' then 0 when due_at is null then 2 else 1 end")
            ->orderBy('due_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $boardActivities = $filters['view'] === 'board'
            ? (clone $baseActivities)
                ->whereIn('status', ['pending', 'in_progress', 'blocked'])
                ->orderByRaw('case when due_at is null then 1 else 0 end')
                ->orderBy('due_at')
                ->limit(200)
                ->get()
            : collect();

        $selectedDate = isset($filters['week'])
            ? Carbon::createFromFormat('Y-m-d', $filters['week'])
            : BusinessTime::now();
        $anchor = $filters['view'] === 'month'
            ? $selectedDate->copy()->startOfMonth()
            : $selectedDate->copy()->startOfWeek();
        $rangeStart = $filters['view'] === 'month' ? $anchor->copy()->startOfWeek() : $anchor;
        $rangeEnd = $filters['view'] === 'month' ? $anchor->copy()->endOfMonth()->endOfWeek() : $anchor->copy()->endOfWeek();
        $workOrdersQuery = WorkOrder::query()
            ->with(['installation:id,project_reference,site_name,status,site_postal_code,site_latitude,site_longitude', 'installation.materialRequirements:id,installation_id', 'assignedUser:id,name,role', 'assignedUser.fieldServiceProfile', 'stockReservations:id,work_order_id,status'])
            ->whereIn('status', ['planned', 'in_progress', 'blocked']);
        if (! $request->user()->can('manage-installations')) {
            $workOrdersQuery->where('assigned_user_id', $request->user()->id);
        }
        $planningOrdersQuery = (clone $workOrdersQuery)
            ->when($filters['owner'] ?? null, fn (Builder $query, int $owner) => $query->where('assigned_user_id', $owner))
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('title', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%")));
        $isPlanningView = in_array($filters['view'], ['week', 'month'], true);
        $scheduledOrdersQuery = (clone $planningOrdersQuery)
            ->whereNotNull('scheduled_start')
            ->where('scheduled_start', '<=', $rangeEnd)
            ->where(fn (Builder $query) => $query->whereNull('scheduled_end')->orWhere('scheduled_end', '>=', $rangeStart));
        $unscheduledOrdersQuery = (clone $planningOrdersQuery)->whereNull('scheduled_start');
        $scheduledTotal = $isPlanningView ? (clone $scheduledOrdersQuery)->count() : 0;
        $unscheduledTotal = $isPlanningView ? (clone $unscheduledOrdersQuery)->count() : 0;
        $scheduledOrders = $isPlanningView
            ? $scheduledOrdersQuery->orderBy('scheduled_start')->orderBy('id')->limit(500)->get()
            : collect();
        $unscheduledOrders = $isPlanningView
            ? $unscheduledOrdersQuery->orderBy('created_at')->orderBy('id')->limit(50)->get()
            : collect();
        $planningOrders = $scheduledOrders->concat($unscheduledOrders);
        $materialRisk = $criticalMaterialRisk->forecast(today(), 45, $request->user());
        $materialRiskByOrder = collect($materialRisk['rows'])->groupBy('work_order_id');
        $planningIssues = $schedule->planningIssues($planningOrders);
        $planningOrders->each(function (WorkOrder $order) use (&$planningIssues, $materialRiskByOrder): void {
            $risks = $materialRiskByOrder->get($order->id, collect());
            if ($risks->isNotEmpty()) {
                $shortage = round((float) $risks->sum('shortage_quantity'), 3);
                $severity = $risks->contains('severity', 'blocked') || $risks->contains('severity', 'critical') ? 'critical' : 'warning';
                $planningIssues[$order->id] = collect($planningIssues[$order->id] ?? [])->reject(fn (array $issue) => $issue['type'] === 'materials')->push([
                    'type' => 'materials', 'severity' => $severity,
                    'message' => $shortage > 0 ? __('Material shortage: :quantity units', ['quantity' => $shortage]) : __('Inbound material arrives after the planned work'),
                ])->values()->all();
            }
            $order->setAttribute('planning_issues', $planningIssues[$order->id] ?? []);
            $order->setAttribute('has_schedule_conflict', collect($planningIssues[$order->id] ?? [])->contains('type', 'overlap'));
            $hasPendingMaterials = collect($planningIssues[$order->id] ?? [])->contains('type', 'materials');
            $order->setAttribute('material_status', $risks->contains(fn (array $risk) => in_array($risk['severity'], ['blocked', 'critical'], true))
                ? 'blocked'
                : ($risks->isNotEmpty() || $hasPendingMaterials ? 'warning' : 'ready'));
        });

        $weekActivities = CrmActivity::query()
            ->with(['installation:id,project_reference,site_name', 'assignedUser:id,name,role', 'workOrder:id,number,status'])
            ->whereIn('status', ['pending', 'in_progress', 'blocked'])
            ->where(fn (Builder $query) => $query->whereBetween('starts_at', [$rangeStart, $rangeEnd])->orWhereBetween('due_at', [$rangeStart, $rangeEnd]));
        $this->scopeForUser($weekActivities, $request);
        $this->applyFilters($weekActivities, [...$filters, 'status' => 'active']);

        return Inertia::render('operations/index', [
            'activities' => $activities,
            'boardActivities' => $boardActivities,
            'planningOrders' => $planningOrders,
            'planningMeta' => [
                'scheduled_total' => $scheduledTotal,
                'scheduled_loaded' => $scheduledOrders->count(),
                'unscheduled_total' => $unscheduledTotal,
                'unscheduled_loaded' => $unscheduledOrders->count(),
                'truncated' => $scheduledTotal > $scheduledOrders->count() || $unscheduledTotal > $unscheduledOrders->count(),
            ],
            'weekActivities' => $isPlanningView
                ? $weekActivities->orderBy('starts_at')->orderBy('due_at')->limit(500)->get()
                : collect(),
            'metrics' => $this->metrics($request, $workOrdersQuery),
            'exceptions' => $this->operationalExceptions($request, $materialRisk),
            'installations' => Installation::where('status', '!=', 'cancelled')->when(! $request->user()->can('manage-installations'), fn ($q) => $q->where(fn ($q) => $q->whereHas('workOrders', fn ($w) => $w->where('assigned_user_id', $request->user()->id))->orWhereHas('activities', fn ($a) => $a->where('assigned_user_id', $request->user()->id))))->orderBy('project_reference')->get(['id', 'project_reference', 'site_name', 'status']),
            'vehicles' => OperationalVehicle::orderBy('name')->get(['id', 'registration', 'name', 'status']),
            'users' => User::with('fieldServiceProfile')->whereIn('role', ['commercial', 'technician', 'admin'])->when(! $request->user()->can('manage-installations'), fn ($q) => $q->whereKey($request->user()->id))->orderBy('name')->get(['id', 'name', 'role']),
            'templates' => WorkOrderTemplate::where('is_active', true)->orderBy('name')->get(),
            'filters' => [...$filters, 'week' => $anchor->toDateString()],
            'canManage' => $request->user()->can('manage-installations'),
            'currentUserId' => $request->user()->id,
            'capacityForecast' => $isPlanningView
                ? $capacityForecast->forecast($anchor->copy()->startOfWeek(), 6, $request->user())
                : null,
        ]);
    }

    public function store(Request $request, OpportunitySalesCycle $salesCycle, LeadResponseSla $responseSla): RedirectResponse
    {
        $this->normaliseEmptySelects($request);
        $data = $this->validateActivity($request);
        if ($request->user()->hasRole('technician')) {
            $data['assigned_user_id'] = $request->user()->id;
            $data['status'] = 'pending';
        }
        $installation = filled($data['installation_id'] ?? null) ? Installation::find($data['installation_id']) : null;
        $opportunity = filled($data['opportunity_id'] ?? null) ? Opportunity::find($data['opportunity_id']) : null;
        abort_if($opportunity && in_array($opportunity->stage, ['won', 'lost'], true), 422, __('Closed opportunities cannot receive new activities.'));
        abort_if($installation?->person_id && filled($data['person_id'] ?? null) && $installation->person_id !== (int) $data['person_id'], 422, __('The installation does not belong to this customer.'));
        abort_if($opportunity && filled($data['person_id'] ?? null) && $opportunity->person_id !== (int) $data['person_id'], 422, __('The opportunity does not belong to this customer.'));
        abort_if($opportunity && $installation && $opportunity->installation_id && $opportunity->installation_id !== $installation->id, 422, __('The installation does not belong to this opportunity.'));

        $status = $data['status'] ?? 'pending';
        abort_if((! empty($data['vehicle_id']) || ! empty($data['vehicle_required'])) && ! $request->user()->can('manage-installations'), 403);
        unset($data['vehicle_change_reason'], $data['expected_updated_at']);
        DB::transaction(function () use ($data, $opportunity, $installation, $status, $request, $salesCycle, $responseSla): void {
            $this->assertActivityAvailability($data);
            $vehicles = app(VehicleAvailability::class);
            if ((! empty($data['vehicle_id']) || ! empty($data['vehicle_required'])) && ! empty($data['assigned_user_id']) && ! empty($data['starts_at']) && ! empty($data['due_at'])) {
                $schedule = app(TechnicianSchedule::class);
                $schedule->lockTechnicians([(int) $data['assigned_user_id']]);
                $schedule->assertAvailable((int) $data['assigned_user_id'], $data['starts_at'], $data['due_at']);
            }
            $vehicles->lockVehicles([$data['vehicle_id'] ?? null]);
            if ((! empty($data['vehicle_id']) || ! empty($data['vehicle_required'])) && ! empty($data['starts_at']) && ! empty($data['due_at'])) {
                app(TechnicianSchedule::class)->assertStaffRecord(isset($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null);
            }
            if (! in_array($status, ['completed', 'cancelled'], true) && (! empty($data['vehicle_id']) || ! empty($data['starts_at']) || ! empty($data['due_at']) || $status === 'in_progress')) {
                $vehicles->assertAvailable(isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null, $data['starts_at'] ?? null, $data['due_at'] ?? null, required: (bool) ($data['vehicle_required'] ?? false));
            }
            $activity = CrmActivity::create([
                ...$data,
                'person_id' => $data['person_id'] ?? $opportunity?->person_id ?? $installation?->person_id,
                'installation_id' => $data['installation_id'] ?? $opportunity?->installation_id,
                'status' => $status,
                'blocked_reason' => $status === 'blocked' ? ($data['blocked_reason'] ?? null) : null,
                'completed_at' => $status === 'completed' ? BusinessTime::now() : null,
                'created_by' => $request->user()->id,
            ]);
            if ($activity->vehicle_id || $activity->vehicle_required) {
                $activity->vehicleHistory()->create(['old_vehicle_id' => null, 'new_vehicle_id' => $activity->vehicle_id, 'old_assignee_id' => null, 'new_assignee_id' => $activity->assigned_user_id, 'old_start_utc' => null, 'old_end_utc' => null,
                    'new_start_utc' => $activity->starts_at?->copy()->utc(), 'new_end_utc' => $activity->due_at?->copy()->utc(), 'old_required' => false, 'new_required' => $activity->vehicle_required,
                    'reason' => __('Vehicle assigned when creating the visit.'), 'changed_by' => $request->user()->id]);
            }
            $responseSla->record($activity);
            if ($opportunity) {
                $salesCycle->synchronizeStage($opportunity, $request->user());
                $salesCycle->refreshScore($opportunity);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Activity created.')]);
    }

    public function update(Request $request, CrmActivity $activity, OpportunitySalesCycle $salesCycle, LeadResponseSla $responseSla): RedirectResponse
    {
        Gate::authorize('update', $activity);
        abort_if($activity->work_order_id !== null, 422, __('Update linked work through its work order.'));
        $this->normaliseEmptySelects($request);

        $data = $request->user()->hasRole('technician')
            ? $request->validate([
                'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'blocked', 'completed', 'cancelled'])],
                'notes' => ['sometimes', 'nullable', 'string'],
                'blocked_reason' => ['nullable', 'string', 'max:1000', 'required_if:status,blocked'],
            ])
            : $this->validateActivity($request, true);

        if (array_key_exists('status', $data)) {
            $data['completed_at'] = $data['status'] === 'completed' ? BusinessTime::now() : null;
            $data['blocked_reason'] = $data['status'] === 'blocked' ? ($data['blocked_reason'] ?? $activity->blocked_reason) : null;
        }
        $previousOpportunity = $activity->opportunity;
        $targetOpportunityId = array_key_exists('opportunity_id', $data) ? $data['opportunity_id'] : $activity->opportunity_id;
        $targetOpportunity = $targetOpportunityId ? Opportunity::find($targetOpportunityId) : null;
        $targetStatus = $data['status'] ?? $activity->status;
        abort_if(
            $targetOpportunity && in_array($targetOpportunity->stage, ['won', 'lost'], true) && in_array($targetStatus, ['pending', 'in_progress', 'blocked'], true),
            422,
            __('Closed opportunities cannot receive active activities.'),
        );

        $reason = $data['vehicle_change_reason'] ?? null;
        $expected = $data['expected_updated_at'] ?? null;
        unset($data['vehicle_change_reason'], $data['expected_updated_at']);
        DB::transaction(function () use ($activity, $data, $reason, $expected, $previousOpportunity, $targetOpportunity, $request, $salesCycle, $responseSla): void {
            $lockedActivity = CrmActivity::whereKey($activity->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedActivity->work_order_id !== null, 422, __('Update linked work through its work order.'));
            if ($expected && ! $lockedActivity->updated_at->equalTo(Carbon::parse($expected))) {
                throw ValidationException::withMessages(['expected_updated_at' => __('This activity changed on the server. Refresh before retrying.')]);
            }
            $oldStart = $lockedActivity->starts_at;
            $oldEnd = $lockedActivity->due_at;
            $newStart = array_key_exists('starts_at', $data) ? ($data['starts_at'] ? BusinessTime::parse($data['starts_at'], 'starts_at') : null) : $oldStart;
            $newEnd = array_key_exists('due_at', $data) ? ($data['due_at'] ? BusinessTime::parse($data['due_at'], 'due_at') : null) : $oldEnd;
            $newVehicle = array_key_exists('vehicle_id', $data) ? $data['vehicle_id'] : $lockedActivity->vehicle_id;
            $newRequired = array_key_exists('vehicle_required', $data) ? (bool) $data['vehicle_required'] : $lockedActivity->vehicle_required;
            $newAssignee = array_key_exists('assigned_user_id', $data) ? $data['assigned_user_id'] : $lockedActivity->assigned_user_id;
            $changedAssignee = (int) $newAssignee !== (int) $lockedActivity->assigned_user_id;
            $changedVehicle = (int) $newVehicle !== (int) $lockedActivity->vehicle_id || $newRequired !== $lockedActivity->vehicle_required;
            $changedTime = $oldStart?->toISOString() !== $newStart?->toISOString() || $oldEnd?->toISOString() !== $newEnd?->toISOString();
            if ($changedVehicle && ! $request->user()->can('manage-installations')) {
                abort(403);
            }
            if (in_array($lockedActivity->status, ['completed', 'cancelled'], true) && ($changedVehicle || (($lockedActivity->vehicle_id || $newVehicle) && $changedTime))) {
                throw ValidationException::withMessages(['vehicle_id' => __('Closed activities cannot have their vehicle assignment changed.')]);
            }
            $this->assertActivityAvailability([...$lockedActivity->getAttributes(), 'starts_at' => $oldStart, 'due_at' => $oldEnd, ...$data], $lockedActivity->id);
            $vehicles = app(VehicleAvailability::class);
            $ownerForVehicle = array_key_exists('assigned_user_id', $data) ? $data['assigned_user_id'] : $lockedActivity->assigned_user_id;
            if (($newVehicle || $newRequired) && $ownerForVehicle && $newStart && $newEnd && ! in_array($data['status'] ?? $lockedActivity->status, ['completed', 'cancelled'], true)) {
                $schedule = app(TechnicianSchedule::class);
                $schedule->lockTechnicians([(int) $ownerForVehicle]);
                $schedule->assertAvailable((int) $ownerForVehicle, $newStart, $newEnd, exceptActivityId: $lockedActivity->id);
            }
            $vehicles->lockVehicles([$lockedActivity->vehicle_id, $newVehicle]);
            $newOwner = array_key_exists('assigned_user_id', $data) ? $data['assigned_user_id'] : $lockedActivity->assigned_user_id;
            if (($newVehicle || $newRequired) && $newStart && $newEnd && ! in_array($data['status'] ?? $lockedActivity->status, ['completed', 'cancelled'], true)) {
                app(TechnicianSchedule::class)->assertStaffRecord($newOwner ? (int) $newOwner : null);
            }
            if (! in_array($data['status'] ?? $lockedActivity->status, ['completed', 'cancelled'], true) && ($newVehicle || $newStart || $newEnd || ($data['status'] ?? $lockedActivity->status) === 'in_progress')) {
                $vehicles->assertAvailable($newVehicle ? (int) $newVehicle : null, $newStart, $newEnd, required: $newRequired, exceptActivityId: $lockedActivity->id);
            }
            if (($changedVehicle || $changedTime || $changedAssignee) && ($lockedActivity->vehicle_id || $newVehicle || $newRequired)) {
                if (! filled($reason)) {
                    throw ValidationException::withMessages(['vehicle_change_reason' => __('Enter a reason for the vehicle assignment change.')]);
                }
                $lockedActivity->vehicleHistory()->create(['old_vehicle_id' => $lockedActivity->vehicle_id, 'new_vehicle_id' => $newVehicle, 'old_assignee_id' => $lockedActivity->assigned_user_id, 'new_assignee_id' => $newAssignee,
                    'old_start_utc' => $oldStart?->copy()->utc(), 'old_end_utc' => $oldEnd?->copy()->utc(),
                    'new_start_utc' => $newStart?->copy()->utc(), 'new_end_utc' => $newEnd?->copy()->utc(),
                    'old_required' => $lockedActivity->vehicle_required, 'new_required' => $newRequired,
                    'reason' => $reason, 'changed_by' => $request->user()->id]);
            }
            $lockedActivity->update($data);
            $activity = $lockedActivity;
            $responseSla->record($activity);
            collect([$previousOpportunity, $targetOpportunity])->filter()->unique('id')->each(function (Opportunity $opportunity) use ($request, $salesCycle): void {
                $salesCycle->synchronizeStage($opportunity, $request->user());
                $salesCycle->refreshScore($opportunity);
            });
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Activity updated.')]);
    }

    private function assertActivityAvailability(array $data, ?int $exceptActivityId = null): void
    {
        $owner = $data['assigned_user_id'] ?? null;
        if (! $owner || (! in_array($data['type'] ?? '', ['visit', 'meeting'], true) && (! filled($data['starts_at'] ?? null) || ! filled($data['due_at'] ?? null)))
            || in_array($data['status'] ?? 'pending', ['completed', 'cancelled'], true)) {
            return;
        }
        $technicalUser = User::whereKey($owner)->where(fn ($q) => $q->where('role', 'technician')->orWhere(fn ($q) => $q->where('role', 'admin')->whereHas('fieldServiceProfile')))->exists();
        if (! $technicalUser) {
            return;
        }
        $schedule = app(TechnicianSchedule::class);
        $schedule->lockTechnicians([$owner]);
        $schedule->assertAvailable((int) $owner, $data['starts_at'] ?? null, $data['due_at'] ?? null, exceptActivityId: $exceptActivityId);
    }

    public function destroy(Request $request, CrmActivity $activity, OpportunitySalesCycle $salesCycle): RedirectResponse
    {
        Gate::authorize('delete', $activity);
        abort_if($activity->work_order_id !== null, 422, __('Work-order activities cannot be deleted directly.'));
        $opportunity = $activity->opportunity;
        DB::transaction(function () use ($activity, $opportunity, $request, $salesCycle): void {
            $activity->delete();
            if ($opportunity) {
                $salesCycle->synchronizeStage($opportunity, $request->user());
                $salesCycle->refreshScore($opportunity);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Activity deleted.')]);
    }

    public function convertToWorkOrder(Request $request, CrmActivity $activity, WorkOrderFactory $factory, TechnicianSchedule $schedule): RedirectResponse
    {
        Gate::authorize('convert', $activity);
        abort_if($activity->work_order_id !== null || $activity->installation_id === null || in_array($activity->status, ['completed', 'cancelled'], true), 422);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['survey', 'installation', 'commissioning', 'maintenance', 'repair'])],
            'scheduled_end' => ['nullable', 'date'],
        ]);
        $order = DB::transaction(function () use ($request, $activity, $factory, $schedule, $data) {
            Installation::whereKey($activity->installation_id)->lockForUpdate()->firstOrFail();
            $locked = CrmActivity::with('assignedUser')->whereKey($activity->id)->lockForUpdate()->firstOrFail();
            if ($locked->work_order_id) {
                return WorkOrder::findOrFail($locked->work_order_id);
            }
            abort_if($locked->installation_id !== $activity->installation_id || in_array($locked->status, ['completed', 'cancelled'], true), 422);
            $technicalAssignee = $locked->assignedUser?->hasRole('technician', 'admin') ? $locked->assigned_user_id : null;
            $start = $locked->starts_at;
            $end = $data['scheduled_end'] ?? $locked->due_at;
            abort_if($start && $end && Carbon::parse($end)->lessThanOrEqualTo($start), 422, __('The scheduled end must be after the start.'));
            if ($start && $end) {
                $schedule->assertAvailable($technicalAssignee, $start, $end, exceptActivityId: $locked->id);
            }
            $order = $factory->create([
                'installation_id' => $locked->installation_id, 'activity_id' => $locked->id,
                'vehicle_id' => $locked->vehicle_id, 'vehicle_required' => $locked->vehicle_required,
                'assigned_user_id' => $technicalAssignee, 'title' => $locked->title, 'kind' => $data['kind'],
                'scheduled_start' => $start, 'scheduled_end' => $end, 'instructions' => $locked->notes,
            ], $request->user()->id);
            if ($start && $order->installation->status !== 'cancelled') {
                $order->installation->update(['status' => $data['kind'] === 'survey' ? 'survey_booked' : (in_array($data['kind'], ['installation', 'commissioning'], true) ? 'scheduled' : $order->installation->status)]);
            }

            return $order;
        });

        return to_route('work-orders.index', ['work_order' => $order->id])
            ->with('toast', ['type' => 'success', 'message' => __('Work order created from activity.')]);
    }

    private function validateActivity(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'vehicle_id' => ['sometimes', 'nullable', 'exists:operational_vehicles,id'],
            'vehicle_required' => ['sometimes', 'boolean'],
            'vehicle_change_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'expected_updated_at' => ['sometimes', 'nullable', 'date'],
            'installation_id' => ['nullable', 'exists:installations,id'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('category', 'customer')],
            'opportunity_id' => ['nullable', 'exists:opportunities,id'],
            'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['commercial', 'technician', 'admin']))],
            'type' => [$required, Rule::in(['task', 'visit', 'reminder', 'call', 'email', 'meeting'])],
            'title' => [$required, 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'priority' => [$required, Rule::in(['low', 'normal', 'high'])],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'blocked', 'completed', 'cancelled'])],
            'blocked_reason' => ['nullable', 'string', 'max:1000', 'required_if:status,blocked'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    private function normaliseEmptySelects(Request $request): void
    {
        foreach (['installation_id', 'person_id', 'opportunity_id', 'assigned_user_id', 'vehicle_id'] as $field) {
            if ($request->input($field) === 'none' || $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
    }

    /** @param Builder<CrmActivity> $query */
    private function scopeForUser(Builder $query, Request $request): void
    {
        if (! $request->user()->can('manage-installations')) {
            $query->where('assigned_user_id', $request->user()->id);
        }
    }

    /** @param Builder<CrmActivity> $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('title', 'like', "%{$search}%")->orWhere('notes', 'like', "%{$search}%")))
            ->when(($filters['status'] ?? 'active') === 'active', fn (Builder $query) => $query->whereIn('status', ['pending', 'in_progress', 'blocked']))
            ->when(! in_array($filters['status'] ?? 'active', ['active', 'all'], true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['owner'] ?? null, fn (Builder $query, int $owner) => $query->where('assigned_user_id', $owner))
            ->when($filters['planning_request'] ?? null, fn (Builder $query, int $planningRequest) => $query->where('planning_scenario_request_id', $planningRequest));
    }

    /** @param Builder<WorkOrder> $workOrdersQuery
     * @return array<string, int>
     */
    private function metrics(Request $request, Builder $workOrdersQuery): array
    {
        $activities = CrmActivity::query()->whereIn('status', ['pending', 'in_progress', 'blocked']);
        $this->scopeForUser($activities, $request);
        $orders = clone $workOrdersQuery;

        return [
            'overdue' => (clone $activities)->whereNotNull('due_at')->where('due_at', '<', BusinessTime::now())->count(),
            'today' => (clone $activities)->where(fn (Builder $query) => $query->whereDate('starts_at', today())->orWhereDate('due_at', today()))->count(),
            'blocked' => (clone $activities)->where('status', 'blocked')->count(),
            'unassigned' => $request->user()->hasRole('technician') ? 0 : (clone $activities)->whereNull('assigned_user_id')->count() + (clone $orders)->whereNull('assigned_user_id')->count(),
            'unscheduled' => (clone $orders)->whereNull('scheduled_start')->count(),
            'ready_to_plan' => $request->user()->can('manage-installations')
                ? Installation::query()->where('status', 'approved')->whereDoesntHave('workOrders', fn (Builder $query) => $query->whereNotIn('status', ['completed', 'cancelled']))->count()
                : 0,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function operationalExceptions(Request $request, array $materialRisk): array
    {
        $tickets = ServiceTicket::query()
            ->with('installation:id,project_reference,site_name')
            ->whereIn('status', ['open', 'in_progress'])
            ->when($request->user()->hasRole('technician'), fn (Builder $query) => $query->where('assigned_user_id', $request->user()->id))
            ->orderByRaw("case when priority = 'high' then 0 else 1 end")
            ->oldest()
            ->limit(6)
            ->get()
            ->map(fn (ServiceTicket $ticket) => [
                'id' => 'ticket-'.$ticket->id,
                'kind' => 'incident',
                'title' => $ticket->number.' · '.$ticket->title,
                'detail' => ($ticket->installation?->project_reference ?: $ticket->installation?->site_name ?: __('Installation')).' · '.__('After-sales'),
                'href' => '/after-sales?ticket='.$ticket->id,
                'severity' => $ticket->priority === 'high' ? 'critical' : 'warning',
                'sort_at' => $ticket->created_at?->toIso8601String(),
            ]);

        $maintenance = MaintenancePlan::query()
            ->with('installation:id,project_reference,site_name,technician_user_id')
            ->where('is_active', true)
            ->whereDate('next_due_on', '<=', today()->addDays(30))
            ->when($request->user()->hasRole('technician'), fn (Builder $query) => $query->whereHas('installation', fn (Builder $query) => $query->where('technician_user_id', $request->user()->id)))
            ->orderBy('next_due_on')
            ->limit(6)
            ->get()
            ->map(fn (MaintenancePlan $plan) => [
                'id' => 'maintenance-'.$plan->id,
                'kind' => 'maintenance',
                'title' => $plan->name,
                'detail' => ($plan->installation?->project_reference ?: $plan->installation?->site_name ?: __('Installation')).' · '.__('Due :date', ['date' => $plan->next_due_on->format('d/m/Y')]),
                'href' => '/after-sales?plan='.$plan->id,
                'severity' => $plan->next_due_on->isPast() ? 'critical' : 'warning',
                'sort_at' => $plan->next_due_on->startOfDay()->toIso8601String(),
            ]);

        $materials = collect($materialRisk['rows'])
            ->whereIn('severity', ['blocked', 'critical', 'warning'])
            ->take(6)
            ->map(fn (array $risk) => [
                'id' => 'materials-'.$risk['work_order_id'].'-'.$risk['product_id'],
                'kind' => 'materials',
                'title' => $risk['work_order_number'].' · '.$risk['product'],
                'detail' => $request->user()->can('view-finance')
                    ? __('Shortage :quantity · exposure :amount', [
                        'quantity' => $risk['shortage_quantity'],
                        'amount' => number_format($risk['shortage_exposure'], 2).' EUR',
                    ])
                    : __('Shortage :quantity', ['quantity' => $risk['shortage_quantity']]),
                'href' => '/work-orders?work_order='.$risk['work_order_id'],
                'severity' => in_array($risk['severity'], ['blocked', 'critical'], true) ? 'critical' : 'warning',
                'sort_at' => $risk['scheduled_start'],
            ]);

        return $tickets->concat($maintenance)->concat($materials)
            ->sortBy(fn (array $item) => ($item['severity'] === 'critical' ? '0-' : '1-').($item['sort_at'] ?? '9999'))
            ->take(12)
            ->values()
            ->map(fn (array $item) => collect($item)->except('sort_at')->all())
            ->all();
    }
}
