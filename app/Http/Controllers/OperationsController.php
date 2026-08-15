<?php

namespace App\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Opportunity;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Services\Crm\LeadResponseSla;
use App\Services\Crm\OpportunitySalesCycle;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\WorkOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function index(Request $request, TechnicianSchedule $schedule): Response
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
        ]);
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
        if ($filters['view'] === 'today') {
            $baseActivities->when(
                $filters['focus'] ?? null,
                fn (Builder $query, int $focus) => $query->whereKey($focus),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('starts_at', '<=', now()->endOfDay())
                    ->orWhere('due_at', '<=', now()->endOfDay())
                    ->orWhere(fn (Builder $query) => $query->whereNull('starts_at')->whereNull('due_at')))
            );
        }

        $activities = (clone $baseActivities)
            ->orderByRaw("case when status = 'blocked' then 0 when due_at is null then 2 else 1 end")
            ->orderBy('due_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $boardActivities = (clone $baseActivities)
            ->whereIn('status', ['pending', 'in_progress', 'blocked'])
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->limit(200)
            ->get();

        $selectedDate = isset($filters['week'])
            ? Carbon::createFromFormat('Y-m-d', $filters['week'])
            : now();
        $anchor = $filters['view'] === 'month'
            ? $selectedDate->copy()->startOfMonth()
            : $selectedDate->copy()->startOfWeek();
        $rangeStart = $filters['view'] === 'month' ? $anchor->copy()->startOfWeek() : $anchor;
        $rangeEnd = $filters['view'] === 'month' ? $anchor->copy()->endOfMonth()->endOfWeek() : $anchor->copy()->endOfWeek();
        $workOrdersQuery = WorkOrder::query()
            ->with(['installation:id,project_reference,site_name,status,site_postal_code,site_latitude,site_longitude', 'installation.materialRequirements:id,installation_id', 'assignedUser:id,name,role', 'assignedUser.fieldServiceProfile', 'stockReservations:id,work_order_id,status'])
            ->whereNotIn('status', ['completed', 'cancelled']);
        if ($request->user()->hasRole('technician')) {
            $workOrdersQuery->where('assigned_user_id', $request->user()->id);
        }
        $planningOrdersQuery = (clone $workOrdersQuery)
            ->when($filters['owner'] ?? null, fn (Builder $query, int $owner) => $query->where('assigned_user_id', $owner))
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('title', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%")));
        $planningOrders = $planningOrdersQuery
            ->where(fn (Builder $query) => $query->whereNull('scheduled_start')->orWhereBetween('scheduled_start', [$rangeStart, $rangeEnd]))
            ->orderByRaw('case when scheduled_start is null then 1 else 0 end')
            ->orderBy('scheduled_start')
            ->limit(200)
            ->get();
        $planningIssues = $schedule->planningIssues($planningOrders);
        $planningOrders->each(function (WorkOrder $order) use ($planningIssues): void {
            $order->setAttribute('planning_issues', $planningIssues[$order->id] ?? []);
            $order->setAttribute('has_schedule_conflict', collect($planningIssues[$order->id] ?? [])->contains('type', 'overlap'));
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
            'weekActivities' => $weekActivities->orderBy('starts_at')->orderBy('due_at')->limit(200)->get(),
            'metrics' => $this->metrics($request, $workOrdersQuery),
            'exceptions' => $this->operationalExceptions($request, $workOrdersQuery),
            'installations' => Installation::where('status', '!=', 'cancelled')->orderBy('project_reference')->get(['id', 'project_reference', 'site_name', 'status']),
            'users' => User::with('fieldServiceProfile')->whereIn('role', ['commercial', 'technician', 'admin'])->orderBy('name')->get(['id', 'name', 'role']),
            'templates' => WorkOrderTemplate::where('is_active', true)->orderBy('name')->get(),
            'filters' => [...$filters, 'week' => $anchor->toDateString()],
            'canManage' => $request->user()->can('manage-installations'),
            'currentUserId' => $request->user()->id,
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
        DB::transaction(function () use ($data, $opportunity, $installation, $status, $request, $salesCycle, $responseSla): void {
            $activity = CrmActivity::create([
                ...$data,
                'person_id' => $data['person_id'] ?? $opportunity?->person_id ?? $installation?->person_id,
                'installation_id' => $data['installation_id'] ?? $opportunity?->installation_id,
                'status' => $status,
                'blocked_reason' => $status === 'blocked' ? ($data['blocked_reason'] ?? null) : null,
                'completed_at' => $status === 'completed' ? now() : null,
                'created_by' => $request->user()->id,
            ]);
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
            $data['completed_at'] = $data['status'] === 'completed' ? now() : null;
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

        DB::transaction(function () use ($activity, $data, $previousOpportunity, $targetOpportunity, $request, $salesCycle, $responseSla): void {
            $activity->update($data);
            $responseSla->record($activity);
            collect([$previousOpportunity, $targetOpportunity])->filter()->unique('id')->each(function (Opportunity $opportunity) use ($request, $salesCycle): void {
                $salesCycle->synchronizeStage($opportunity, $request->user());
                $salesCycle->refreshScore($opportunity);
            });
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Activity updated.')]);
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
        $start = $activity->starts_at;
        $end = $data['scheduled_end'] ?? $activity->due_at;
        abort_if($start && $end && Carbon::parse($end)->lessThanOrEqualTo($start), 422, __('The scheduled end must be after the start.'));
        if ($start && $end) {
            $schedule->assertAvailable($activity->assigned_user_id, $start, $end);
        }

        $order = $factory->create([
            'installation_id' => $activity->installation_id,
            'activity_id' => $activity->id,
            'assigned_user_id' => $activity->assigned_user_id,
            'title' => $activity->title,
            'kind' => $data['kind'],
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'instructions' => $activity->notes,
        ], $request->user()->id);
        if ($start && $order->installation->status !== 'cancelled') {
            $order->installation->update([
                'status' => $data['kind'] === 'survey' ? 'survey_booked' : (in_array($data['kind'], ['installation', 'commissioning'], true) ? 'scheduled' : $order->installation->status),
            ]);
        }

        return to_route('work-orders.index', ['work_order' => $order->id])
            ->with('toast', ['type' => 'success', 'message' => __('Work order created from activity.')]);
    }

    private function validateActivity(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
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
        foreach (['installation_id', 'person_id', 'opportunity_id', 'assigned_user_id'] as $field) {
            if ($request->input($field) === 'none' || $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
    }

    /** @param Builder<CrmActivity> $query */
    private function scopeForUser(Builder $query, Request $request): void
    {
        if (! $request->user()->hasRole('admin', 'commercial')) {
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
            ->when($filters['owner'] ?? null, fn (Builder $query, int $owner) => $query->where('assigned_user_id', $owner));
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
            'overdue' => (clone $activities)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'today' => (clone $activities)->where(fn (Builder $query) => $query->whereDate('starts_at', today())->orWhereDate('due_at', today()))->count(),
            'blocked' => (clone $activities)->where('status', 'blocked')->count(),
            'unassigned' => $request->user()->hasRole('technician') ? 0 : (clone $activities)->whereNull('assigned_user_id')->count() + (clone $orders)->whereNull('assigned_user_id')->count(),
            'unscheduled' => (clone $orders)->whereNull('scheduled_start')->count(),
            'ready_to_plan' => $request->user()->can('manage-installations')
                ? Installation::query()->where('status', 'approved')->whereDoesntHave('workOrders', fn (Builder $query) => $query->whereNotIn('status', ['completed', 'cancelled']))->count()
                : 0,
        ];
    }

    /** @param Builder<WorkOrder> $workOrdersQuery
     * @return array<int, array<string, mixed>>
     */
    private function operationalExceptions(Request $request, Builder $workOrdersQuery): array
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

        $materials = (clone $workOrdersQuery)
            ->where(fn (Builder $query) => $query->whereNull('scheduled_start')->orWhere('scheduled_start', '<=', now()->addDays(14)))
            ->whereHas('installation.materialRequirements')
            ->whereDoesntHave('stockReservations', fn (Builder $query) => $query->where('status', 'active'))
            ->orderBy('scheduled_start')
            ->limit(6)
            ->get()
            ->map(fn (WorkOrder $order) => [
                'id' => 'materials-'.$order->id,
                'kind' => 'materials',
                'title' => $order->number.' · '.__('Materials not reserved'),
                'detail' => $order->title,
                'href' => '/work-orders?work_order='.$order->id,
                'severity' => 'warning',
                'sort_at' => $order->scheduled_start?->toIso8601String(),
            ]);

        return $tickets->concat($maintenance)->concat($materials)
            ->sortBy(fn (array $item) => ($item['severity'] === 'critical' ? '0-' : '1-').($item['sort_at'] ?? '9999'))
            ->take(12)
            ->values()
            ->map(fn (array $item) => collect($item)->except('sort_at')->all())
            ->all();
    }
}
