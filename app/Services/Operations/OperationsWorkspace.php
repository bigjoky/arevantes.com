<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\OperationalVehicle;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Supply\CriticalMaterialRisk;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\VehicleAvailability;
use App\Services\Work\WorkOrderWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OperationsWorkspace
{
    public function base(Request $request): Builder
    {
        return WorkOrder::query()->when(! $request->user()->can('manage-installations'), fn ($q) => $q->where('assigned_user_id', $request->user()->id));
    }

    public function rows(Builder $query): Builder
    {
        return $query->select(['id', 'installation_id', 'assigned_user_id', 'number', 'title', 'kind', 'status', 'scheduled_start', 'scheduled_end', 'updated_at', 'estimated_duration_minutes', 'instructions', 'completion_notes', 'completed_at', 'required_skills', 'service_zone', 'allowed_transitions_snapshot', 'signed_at', 'vehicle_id', 'vehicle_required'])->with(['vehicle:id,name,registration,status', 'installation:id,person_id,project_reference,site_name,site_address_line_1,site_city,site_latitude,site_longitude', 'installation.customer:id,company_name,first_name,last_name', 'assignedUser:id,name'])->with('activities:id,work_order_id,priority')->withCount(['checklistItems', 'checklistItems as completed_tasks_count' => fn ($q) => $q->whereNotNull('completed_at')]);
    }

    public function range(Request $request): array
    {
        $data = $request->validate(['week' => ['nullable', 'date_format:Y-m-d'], 'owner' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', 'in:planned,in_progress,blocked,completed,cancelled'], 'kind' => ['nullable', 'in:survey,installation,commissioning,maintenance,repair'], 'installation' => ['nullable', 'integer'], 'vehicle' => ['nullable', 'integer', 'exists:operational_vehicles,id'], 'mine' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'in:10,15,30,50'], 'view' => ['nullable', 'in:week,month,day,list'], 'metric' => ['nullable', 'in:today,delayed,completed'], 'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])]]);
        $date = isset($data['week']) ? CarbonImmutable::parse($data['week'], config('app.timezone')) : CarbonImmutable::now(config('app.timezone'));
        $start = ($data['view'] ?? 'week') === 'month' ? $date->startOfMonth()->startOfWeek() : (($data['view'] ?? 'week') === 'day' ? $date->startOfDay() : $date->startOfWeek());
        $end = ($data['view'] ?? 'week') === 'month' ? $date->endOfMonth()->endOfWeek() : (($data['view'] ?? 'week') === 'day' ? $date->endOfDay() : $start->endOfWeek());

        return [$data, $start, $end];
    }

    private function navigation(array $filters, $start, $end): array
    {
        $anchor = isset($filters['week']) ? CarbonImmutable::parse($filters['week'], config('app.timezone')) : CarbonImmutable::now(config('app.timezone'));
        $view = $filters['view'] ?? 'week';
        $previous = match ($view) {
            'day' => $anchor->subDay(),'month' => $anchor->subMonthNoOverflow(),default => $start->subWeek()
        };
        $next = match ($view) {
            'day' => $anchor->addDay(),'month' => $anchor->addMonthNoOverflow(),default => $start->addWeek()
        };

        return ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'previous' => $previous->toDateString(), 'next' => $next->toDateString()];
    }

    public function common(Request $request): array
    {
        return ['vehicles' => $request->user()->can('manage-operations') ? OperationalVehicle::orderBy('name')->get(['id', 'registration', 'name', 'status']) : collect(), 'canManage' => $request->user()->can('manage-installations'), 'users' => User::query()->whereIn('role', ['technician', 'admin'])->when(! $request->user()->can('manage-installations'), fn ($q) => $q->whereKey($request->user()->id))->orderBy('name')->get(['id', 'name']), 'timezone' => config('app.timezone'), 'statuses' => array_keys(WorkOrderWorkflow::DEFAULT_TRANSITIONS), 'kinds' => ['survey', 'installation', 'commissioning', 'maintenance', 'repair']];
    }

    public function planning(Request $request): array
    {
        [$filters,$start,$end] = $this->range($request);
        $query = $this->base($request)->when(! filled($filters['status'] ?? null), fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']));
        foreach (['owner' => 'assigned_user_id', 'installation' => 'installation_id', 'vehicle' => 'vehicle_id', 'kind' => 'kind', 'status' => 'status'] as $key => $column) {
            $query->when($filters[$key] ?? null, fn ($q, $value) => $q->where($column, $value));
        }
        $scheduled = $this->rows(clone $query)->whereInstant('scheduled_start', '<=', $end)->whereInstant('scheduled_end', '>', $start)->orderBy('scheduled_start')->limit(500)->get();
        $scheduled->load(['installation.materialRequirements:id,installation_id', 'stockReservations:id,work_order_id,status']);
        $issues = app(TechnicianSchedule::class)->planningIssues($scheduled);
        $scheduled->each(function ($o) use ($issues) {
            $decision = app(TechnicianSchedule::class)->availability($o->assigned_user_id, $o->scheduled_start, $o->scheduled_end, $o->id, $o->required_skills ?? [], $o->service_zone);
            $o->setAttribute('availability', $decision);
            $vehicleDecision = app(VehicleAvailability::class)->decision($o->vehicle_id, $o->scheduled_start, $o->scheduled_end, $o->id, $o->vehicle_required);
            $o->setAttribute('vehicle_availability', $vehicleDecision);
            $o->setAttribute('planning_issues', [...($issues[$o->id] ?? []), ...array_map(fn ($message) => ['type' => 'vehicle', 'severity' => 'warning', 'message' => $message], $vehicleDecision['issues']), ...array_map(fn ($message) => ['type' => $decision['status'], 'severity' => 'warning', 'message' => $message], $decision['issues'])]);
            $o->setAttribute('scheduled_start_day', $o->scheduled_start->toDateString());
            $o->setAttribute('scheduled_end_day', $o->scheduled_end->copy()->subMicrosecond()->toDateString());
        });
        $backlog = $this->rows(clone $query)->where(fn ($q) => $q->whereNull('scheduled_start')->orWhereNull('scheduled_end')->orWhereNull('assigned_user_id'))->orderBy('id')->paginate(10)->withQueryString();
        $days = [];
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return [...$this->common($request), 'filters' => $filters, 'orders' => $scheduled, 'backlog' => $backlog, 'days' => $days, 'range' => $this->navigation($filters, $start, $end), 'installations' => Installation::query()->when(! $request->user()->can('manage-installations'), fn ($q) => $q->where(fn ($q) => $q->whereHas('workOrders', fn ($w) => $w->where('assigned_user_id', $request->user()->id))->orWhereHas('activities', fn ($a) => $a->where('assigned_user_id', $request->user()->id))))->orderBy('project_reference')->get(['id', 'project_reference', 'site_name']), 'activities' => $this->unlinkedActivities($request, $filters, $start, $end), 'truncated' => $scheduled->count() === 500];
    }

    public function dashboard(Request $request): array
    {
        $base = $this->base($request);
        $materialRisks = collect(app(CriticalMaterialRisk::class)->forecast(today(), 7, $request->user())['rows'])->take(5)->map(fn ($row) => collect($row)->only(['work_order_id', 'work_order', 'product', 'shortage_quantity', 'severity'])->all())->values();
        $active = (clone $base)->whereNotIn('status', ['completed', 'cancelled']);
        $today = (clone $active)->where('scheduled_start', '<=', now()->endOfDay())->where('scheduled_end', '>=', now()->startOfDay());
        $pending = (clone $active)->where(fn ($q) => $q->whereNull('scheduled_start')->orWhereNull('scheduled_end')->orWhereNull('assigned_user_id'));
        $late = (clone $active)->where(fn ($q) => $q->where('scheduled_end', '<', now())->orWhere(fn ($s) => $s->where('status', 'planned')->where('scheduled_start', '<', now())));
        $progress = (clone $base)->where('status', 'in_progress');

        $planning = $this->planning($request);
        $attention = $this->rows(clone $active)->where(fn ($q) => $q->where('status', 'blocked')->orWhereNull('assigned_user_id')->orWhereNull('scheduled_start')->orWhere('scheduled_end', '<', now()))->orderBy('scheduled_start')->limit(5)->get();
        $attention = $attention->concat($planning['orders']->filter(fn ($o) => count($o->planning_issues ?? []) > 0))->unique('id')->take(5)->values();

        return [...$planning, 'materialRisks' => $materialRisks, 'metrics' => ['today' => $today->count(), 'pending' => $pending->count(), 'in_progress' => $progress->count(), 'delayed' => $late->count(), 'completed' => (clone $base)->where('status', 'completed')->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()])->count()],
            'attention' => $attention,
            'execution' => $this->rows($progress)->orderBy('scheduled_start')->limit(5)->get(),
            'upcoming' => $this->rows(clone $active)->where('kind', 'installation')->where('scheduled_start', '>=', now())->orderBy('scheduled_start')->limit(5)->get()];
    }

    public function listing(Request $request): array
    {
        [$f] = $this->range($request);
        $q = $this->base($request);
        foreach (['status' => 'status', 'kind' => 'kind', 'owner' => 'assigned_user_id', 'installation' => 'installation_id'] as $key => $column) {
            if (filled($f[$key] ?? null)) {
                $q->where($column, $f[$key]);
            }
        }
        if ($f['mine'] ?? false) {
            $q->where('assigned_user_id', $request->user()->id);
        }
        if (($f['metric'] ?? null) === 'today') {
            $q->whereNotIn('status', ['completed', 'cancelled'])->where('scheduled_start', '<=', now()->endOfDay())->where('scheduled_end', '>=', now()->startOfDay());
        }
        if (($f['metric'] ?? null) === 'delayed') {
            $q->whereNotIn('status', ['completed', 'cancelled'])->where(fn ($s) => $s->where('scheduled_end', '<', now())->orWhere(fn ($t) => $t->where('status', 'planned')->where('scheduled_start', '<', now())));
        }
        if (($f['metric'] ?? null) === 'completed') {
            $q->where('status', 'completed')->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()]);
        }
        if (! empty($f['date_from'])) {
            $q->whereDate('scheduled_start', '>=', $f['date_from']);
        }
        if (! empty($f['date_to'])) {
            $q->whereDate('scheduled_start', '<=', $f['date_to']);
        }
        if ($search = $f['q'] ?? null) {
            $q->where(fn ($s) => $s->whereRaw('LOWER(number) LIKE ?', ['%'.mb_strtolower($search).'%'])->orWhereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%'])->orWhereHas('installation', fn ($i) => $i->whereRaw('LOWER(project_reference) LIKE ?', ['%'.mb_strtolower($search).'%'])->orWhereRaw('LOWER(site_name) LIKE ?', ['%'.mb_strtolower($search).'%'])));
        }

        return [...$this->common($request), 'filters' => $f, 'orders' => $this->rows($q)->orderByDesc('id')->paginate($f['per_page'] ?? 15)->withQueryString(), 'counts' => $this->base($request)->selectRaw('status,COUNT(*) as total')->groupBy('status')->pluck('total', 'status')];
    }

    public function detail(Request $request, WorkOrder $order): array
    {
        abort_unless($this->base($request)->whereKey($order->id)->exists(), 403);
        $tab = $request->validate(['tab' => ['nullable', 'in:summary,tasks,technicians,materials,documents,photos,reports,activity']])['tab'] ?? 'summary';
        $order = $this->rows($this->base($request))->findOrFail($order->id);
        $order->installation->makeHidden(['estimated_budget']);
        $order->makeHidden(['signature_path']);
        $content = match ($tab) {
            'tasks' => $order->checklistItems()->paginate(20)->withQueryString(),
            'materials' => $order->stockReservations()->with(['product:id,name,sku,unit_id', 'product.unit:id,symbol', 'warehouse:id,name'])->paginate(15)->withQueryString(),
            'documents','photos' => $order->attachments()->where('kind', $tab === 'photos' ? 'photo' : 'document')->select(['id', 'work_order_id', 'kind', 'original_name', 'mime_type', 'size', 'created_at'])->paginate(15)->withQueryString(),
            'reports' => $order->timeEntries()->with('user:id,name')->select(['id', 'work_order_id', 'user_id', 'started_at', 'ended_at', 'duration_minutes', 'notes'])->paginate(15)->withQueryString()->through(fn ($entry) => $entry->setAppends([])),
            'activity' => AuditLog::query()->where('auditable_type', WorkOrder::class)->where('auditable_id', $order->id)->latest()->paginate(15, ['id', 'user_id', 'event', 'created_at'])->withQueryString(),
            default => null
        };

        $materialOptions = $tab === 'materials' && $request->user()->can('manage-catalog') ? [
            'products' => Product::with('unit:id,symbol')->where('track_stock', true)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit_id']),
            'warehouses' => Warehouse::where('is_active', true)->where('is_usable', true)->orderBy('name')->get(['id', 'name']),
        ] : null;

        return [...$this->common($request), 'order' => $order, 'vehicleHistory' => $tab === 'activity' ? $order->vehicleHistory()->with(['actor:id,name', 'previousVehicle:id,name,registration', 'nextVehicle:id,name,registration', 'previousAssignee:id,name', 'nextAssignee:id,name'])->limit(50)->get() : [], 'scheduleHistory' => $tab === 'activity' ? $order->reschedules()->with(['actor:id,name', 'previousTechnician:id,name', 'nextTechnician:id,name'])->latest('id')->limit(50)->get() : [], 'tab' => $tab, 'content' => $content, 'materialOptions' => $materialOptions, 'runningTimer' => $order->timeEntries()->where('user_id', $request->user()->id)->whereNull('ended_at')->first(['id', 'started_at'])?->setAppends([]), 'transitions' => ($order->allowed_transitions_snapshot ?: WorkOrderWorkflow::DEFAULT_TRANSITIONS)[$order->status] ?? []];
    }

    private function unlinkedActivities(Request $request, array $filters, $start, $end)
    {
        return CrmActivity::query()->whereNull('work_order_id')
            ->when(! $request->user()->can('manage-installations'), fn ($q) => $q->where('assigned_user_id', $request->user()->id))
            ->when($filters['owner'] ?? null, fn ($q, $id) => $q->where('assigned_user_id', $id))
            ->when($filters['installation'] ?? null, fn ($q, $id) => $q->where('installation_id', $id))
            ->when($filters['vehicle'] ?? null, fn ($q, $id) => $q->where('vehicle_id', $id))
            ->when($filters['kind'] ?? null, fn ($q, $kind) => $kind === 'survey' ? $q->where('type', 'visit') : $q->whereRaw('1 = 0'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status === 'planned' ? 'pending' : $status))
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereInstant('starts_at', '<=', $end)->whereInstant('due_at', '>=', $start))
                ->orWhere(fn ($q) => $q->whereNull('starts_at')->whereBetween('due_at', [$start, $end]))
                ->orWhere(fn ($q) => $q->whereNull('due_at')->whereBetween('starts_at', [$start, $end])))
            ->with(['assignedUser:id,name', 'vehicle:id,registration,name,status'])->orderBy('starts_at')->limit(200)
            ->get(['id', 'title', 'type', 'status', 'priority', 'starts_at', 'due_at', 'assigned_user_id', 'installation_id', 'opportunity_id', 'vehicle_id', 'vehicle_required']);
    }

    public function agenda(Request $request): array
    {
        [$filters,$start,$end] = $this->range($request);
        $query = $this->base($request);
        foreach (['owner' => 'assigned_user_id', 'installation' => 'installation_id', 'vehicle' => 'vehicle_id', 'kind' => 'kind', 'status' => 'status'] as $key => $column) {
            $query->when($filters[$key] ?? null, fn ($q, $value) => $q->where($column, $value));
        }
        $orders = $this->rows($query)->whereInstant('scheduled_start', '<=', $end)->where(fn ($q) => $q->whereInstant('scheduled_end', '>', $start)->orWhere(fn ($s) => $s->whereNull('scheduled_end')->whereInstant('scheduled_start', '>=', $start)))->orderBy('scheduled_start')->limit(500)->get();
        $activities = $this->unlinkedActivities($request, $filters, $start, $end);

        return [...$this->common($request), 'filters' => $filters, 'orders' => $orders, 'activities' => $activities, 'range' => $this->navigation($filters, $start, $end), 'truncated' => $orders->count() === 500 || $activities->count() === 200];
    }
}
