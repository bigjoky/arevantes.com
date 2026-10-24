<?php

namespace App\Services\Projects;

use App\Models\AuditLog;
use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Person;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Services\Operations\ProjectProfitability;
use App\Support\BusinessLineCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProjectWorkspace
{
    public const STATUSES = ['lead', 'survey_booked', 'quoted', 'approved', 'scheduled', 'in_progress', 'completed', 'cancelled'];

    public const PHASES = ['preparation', 'execution', 'completed', 'after_sales', 'cancelled', 'other'];

    private const FIELDS = ['id', 'person_id', 'commercial_user_id', 'technician_user_id', 'project_reference', 'site_name', 'service_type', 'status', 'system_power_kw', 'survey_date', 'scheduled_date', 'completed_date', 'site_address_line_1', 'site_city', 'site_province_state', 'created_at', 'updated_at'];

    private function base(bool $projects = true): Builder
    {
        return Installation::query()->when($projects, fn ($q) => $q->whereNotNull('project_reference')->where('project_reference', '!=', ''));
    }

    /** Preserve the assignment scope of the existing service/work-order workspaces. */
    private function assigned(Builder|Relation $query): Builder|Relation
    {
        if (request()->user()->hasRole('technician')) {
            return $query->getModel() instanceof WorkOrder
                ? $query->where('assigned_user_id', request()->user()->id)
                : $query->where(fn ($s) => $s->where('assigned_user_id', request()->user()->id)->orWhereNull('assigned_user_id'));
        }

        return $query;
    }

    private function phaseSql(): string
    {
        $service = '0 = 1';
        if (Gate::allows('manage-operations')) {
            $assignment = request()->user()->hasRole('technician') ? ' AND (assigned_user_id = '.(int) request()->user()->id.' OR assigned_user_id IS NULL)' : '';
            $service = "(EXISTS (SELECT 1 FROM maintenance_plans WHERE installation_id = installations.id AND is_active = true$assignment) OR EXISTS (SELECT 1 FROM service_tickets WHERE installation_id = installations.id AND status NOT IN ('resolved','closed')$assignment))";
        }

        return "CASE WHEN status = 'cancelled' THEN 'cancelled' WHEN status = 'completed' AND $service THEN 'after_sales' WHEN status = 'completed' THEN 'completed' WHEN status = 'in_progress' THEN 'execution' WHEN status IN ('lead','survey_booked','quoted','approved','scheduled') THEN 'preparation' ELSE 'other' END";
    }

    private function filters(Request $request): array
    {
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(self::STATUSES)],
            'phase' => ['nullable', Rule::in(self::PHASES)], 'service_type' => ['nullable', Rule::in(BusinessLineCatalog::INSTALLATION_TYPES)],
            'customer' => ['nullable', 'integer', 'exists:people,id'], 'technician' => ['nullable', 'integer', 'exists:users,id'],
            'project' => ['nullable', 'integer', 'exists:installations,id'],
            'created_from' => ['nullable', 'date'], 'created_to' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
            'sort' => ['nullable', Rule::in(['project_reference', 'site_name', 'scheduled_date', 'completed_date', 'created_at', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'per_page' => ['nullable', Rule::in([10, 15, 30, 50])],
            'page' => ['nullable', 'integer', 'min:1'], 'period' => ['nullable', Rule::in(['month', 'quarter', 'year', 'last12'])],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'service_state' => ['nullable', Rule::in(['review', 'follow_up', 'incidents', 'none'])],
        ]);
        abort_if($validator->fails(), 422, $validator->errors()->first());
        $filters = $validator->validated();
        abort_if(($filters['phase'] ?? null) === 'after_sales' && Gate::denies('manage-operations'), 403);

        return $filters;
    }

    private function apply(Builder $query, array $filters): Builder
    {
        if ($search = $filters['search'] ?? null) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(project_reference) LIKE ?', [$like])->orWhereRaw('LOWER(site_name) LIKE ?', [$like])->orWhereRaw('LOWER(site_address_line_1) LIKE ?', [$like])
                ->orWhereHas('customer', fn ($c) => $c->whereRaw('LOWER(company_name) LIKE ?', [$like])->orWhereRaw("LOWER(first_name || ' ' || COALESCE(last_name, '')) LIKE ?", [$like])));
        }
        foreach (['status' => 'status', 'service_type' => 'service_type', 'customer' => 'person_id', 'technician' => 'technician_user_id', 'project' => 'id'] as $key => $column) {
            if (filled($filters[$key] ?? null)) {
                $query->where($column, $filters[$key]);
            }
        }
        if ($phase = $filters['phase'] ?? null) {
            $query->whereRaw($this->phaseSql().' = ?', [$phase]);
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            if (filled($filters[$key] ?? null)) {
                $query->whereDate('scheduled_date', $operator, $filters[$key]);
            }
        }

        foreach (['created_from' => '>=', 'created_to' => '<='] as $key => $operator) {
            if (filled($filters[$key] ?? null)) {
                $query->whereDate('installations.created_at', $operator, $filters[$key]);
            }
        }

        return $query;
    }

    public function listing(Request $request, bool $projects = true): array
    {
        $filters = $this->filters($request);
        $query = $this->apply($this->base($projects), $filters);
        $counts = (clone $query)->selectRaw($this->phaseSql().' as phase, COUNT(*) as total')->groupBy('phase')->pluck('total', 'phase');
        $records = $query->with(['customer:id,first_name,last_name,company_name', 'technician:id,name'])
            ->select(self::FIELDS)->selectRaw($this->phaseSql().' as phase')->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
        $items = $records->getCollection()->map(fn ($record) => $this->row($record))->all();

        return ['items' => $items, 'installations' => $items, 'pagination' => collect($records->toArray())->except('data')->all(), 'filters' => $filters,
            'counts' => $counts, 'customers' => Person::withTrashed()->whereIn('id', $this->base($projects)->select('person_id'))->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name']),
            'technicians' => User::whereIn('id', $this->base($projects)->select('technician_user_id'))->orderBy('name')->get(['id', 'name']),
            'permissions' => $this->permissions(), 'mode' => $projects ? 'projects' : 'installations'];
    }

    private function permissions(): array
    {
        return ['manage' => Gate::allows('manage-installations'), 'quotes' => Gate::allows('manage-quotes'),
            'customer' => Gate::allows('manage-customers'), 'planning' => Gate::allows('manage-operations'),
            'finance' => Gate::allows('view-finance')];
    }

    private function row(Installation $record): array
    {
        $row = $record->only(self::FIELDS);
        $row['customer_name'] = trim(($record->customer?->first_name ?? '').' '.($record->customer?->last_name ?? ''));
        $row['customer_company'] = $record->customer?->company_name;
        $row['phase'] = $record->getAttribute('phase');
        $row['technician_name'] = $record->technician?->name;
        $row['system_power_kw'] = in_array($record->service_type, ['solar', 'solar_pv'], true) ? $record->system_power_kw : null;
        foreach (['survey_date', 'scheduled_date', 'completed_date'] as $date) {
            $row[$date] = $record->$date?->toDateString();
        }

        return $row;
    }

    public function dashboard(Request $request): array
    {
        $filters = $this->filters($request);
        $end = now()->endOfDay();
        $start = match ($filters['period'] ?? 'last12') {
            'month' => now()->startOfMonth(), 'quarter' => now()->startOfQuarter(),
            'year' => Carbon::create((int) ($filters['year'] ?? now()->year), 1, 1)->startOfDay(),
            default => now()->startOfMonth()->subMonthsNoOverflow(11),
        };
        if (($filters['period'] ?? null) === 'year') {
            $end = $start->copy()->endOfYear();
        }
        $query = $this->base()->whereBetween('installations.created_at', [$start, $end]);
        $value = Gate::allows('view-finance') ? ", SUM(COALESCE((SELECT amount FROM quotes WHERE quotes.installation_id = installations.id AND status = 'accepted' ORDER BY id DESC LIMIT 1), estimated_budget, 0)) as value" : '';
        $groups = (clone $query)->selectRaw($this->phaseSql()." as phase, COUNT(*) as total, SUM(CASE WHEN service_type IN ('solar','solar_pv') AND status = 'in_progress' THEN COALESCE(system_power_kw,0) ELSE 0 END) as power$value")->groupBy('phase')->get()->keyBy('phase');
        $types = (clone $query)->selectRaw('service_type as type, COUNT(*) as total')->groupBy('service_type')->get();
        $month = DB::getDriverName() === 'pgsql' ? "to_char(scheduled_date, 'YYYY-MM')" : "strftime('%Y-%m', scheduled_date)";
        $power = (clone $query)->whereIn('service_type', ['solar', 'solar_pv'])->where('status', 'in_progress')->whereNotNull('system_power_kw')->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("$month as month, SUM(system_power_kw) as power")->groupBy('month')->orderBy('month')->get();
        $months = collect();
        for ($date = $start->copy()->startOfMonth(); $date->lte($end); $date = $date->addMonth()) {
            $key = $date->format('Y-m');
            $months->push(['month' => $key, 'power' => (float) ($power->firstWhere('month', $key)?->power ?? 0)]);
        }
        $ids = (clone $query)->select('installations.id');
        $dates = collect();
        foreach (['survey_date' => 'Visita técnica', 'scheduled_date' => 'Inicio programado'] as $field => $label) {
            $events = (clone $query)->whereDate($field, '>=', today())->where('status', '!=', 'cancelled')
                ->orderBy($field)->limit(6)->get(['id', 'project_reference', $field]);
            $dates = $dates->concat($events->map(fn ($record) => ['title' => $label, 'reference' => $record->project_reference, 'date' => $record->$field->toISOString(), 'href' => '/execution/projects/'.$record->id]));
        }
        if (Gate::allows('manage-operations')) {
            $orders = $this->assigned(WorkOrder::query())->whereIn('installation_id', $ids)->whereNotIn('status', ['completed', 'cancelled'])->where('scheduled_start', '>=', now())->with('installation:id,project_reference')->orderBy('scheduled_start')->limit(6)->get();
            $dates = $dates->concat($orders->map(fn ($order) => ['title' => $order->title, 'reference' => $order->installation?->project_reference, 'date' => $order->scheduled_start->toISOString(), 'href' => '/work-orders?work_order='.$order->id]));
            $plans = $this->assigned(MaintenancePlan::query())->whereIn('installation_id', $ids)->whereHas('installation', fn ($q) => $q->where('status', 'completed'))->where('is_active', true)->whereDate('next_due_on', '>=', today())->with('installation:id,project_reference')->orderBy('next_due_on')->limit(6)->get();
            $dates = $dates->concat($plans->map(fn ($plan) => ['title' => $plan->name, 'reference' => $plan->installation?->project_reference, 'date' => $plan->next_due_on->toISOString(), 'href' => '/maintenance?installation='.$plan->installation_id]));
        }

        return ['filters' => $filters, 'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'total' => (int) $groups->sum('total'), 'groups' => $groups->map(fn ($g) => ['total' => (int) $g->total])->all(),
            'value' => Gate::allows('view-finance') ? (string) $groups->sum('value') : null,
            'valueMissingCount' => Gate::allows('view-finance') ? (clone $query)->whereNull('estimated_budget')->whereDoesntHave('quotes', fn ($q) => $q->where('status', 'accepted')->whereNotNull('amount'))->count() : null,
            'power' => (float) $groups->sum('power'), 'powerSeries' => $months, 'powerDatedCount' => (clone $query)->whereIn('service_type', ['solar', 'solar_pv'])->where('status', 'in_progress')->whereNotNull('system_power_kw')->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])->count(),
            'types' => $types, 'upcoming' => $dates->sortBy('date')->take(6)->values(), 'permissions' => $this->permissions()];
    }

    public function detail(Request $request, Installation $installation, bool $project = true): array
    {
        abort_if($project && ! filled($installation->project_reference), 404);
        $tab = $request->query('tab', 'summary');
        abort_unless(in_array($tab, ['summary', 'quote', 'installations', 'planning', 'documents', 'equipment', 'costs', 'activity'], true), 422);
        if ($tab === 'quote') {
            abort_unless(Gate::allows('manage-quotes'), 403);
        }
        if (in_array($tab, ['planning', 'equipment'], true)) {
            abort_unless(Gate::allows('manage-operations'), 403);
        }
        if ($tab === 'costs') {
            abort_unless(Gate::allows('view-finance'), 403);
        }
        $installation->load(['customer:id,first_name,last_name,company_name', 'technician:id,name', 'commercial:id,name', 'people:id,first_name,last_name,company_name']);
        $record = $this->row($installation);
        $record['site_notes'] = $installation->site_notes;
        $record['technical_notes'] = $installation->technical_notes;
        $record['commercial_name'] = $installation->commercial?->name;
        $record['people'] = $installation->people->map(fn ($p) => ['id' => $p->id, 'name' => trim($p->first_name.' '.$p->last_name), 'role' => $p->pivot->role, 'is_primary' => (bool) $p->pivot->is_primary])->all();
        $quoteFields = ['id', 'number', 'status', 'accepted_at', 'person_id', 'opportunity_id'];
        if (Gate::allows('view-finance') && Gate::allows('manage-quotes')) {
            $quoteFields[] = 'amount';
        }
        $quote = $installation->quotes()->where('status', 'accepted')->latest('id')->first($quoteFields);
        $opportunity = Gate::allows('manage-customers') ? ($quote?->opportunity_id ? $quote->opportunity()->first(['id', 'code', 'title']) : $installation->opportunities()->latest('id')->first(['id', 'code', 'title'])) : null;
        $records = null;
        if ($tab === 'planning') {
            $records = $this->assigned($installation->workOrders())->with('assignedUser:id,name')->select(['id', 'installation_id', 'assigned_user_id', 'number', 'title', 'status', 'scheduled_start', 'scheduled_end'])->latest('scheduled_start')->paginate(15, ['*'], 'section_page')->withQueryString();
        } elseif ($tab === 'equipment') {
            $records = $installation->assets()->with(['product:id,name,sku', 'serial:id,serial_number,warranty_starts_on,warranty_ends_on'])->latest()->paginate(15, ['*'], 'section_page')->withQueryString()
                ->through(fn ($asset) => [...$asset->only(['id', 'asset_tag', 'name', 'category', 'status', 'commissioned_at']), 'product' => $asset->product,
                    'serial_number' => $asset->serial_number ?: $asset->serial?->serial_number,
                    'warranty_ends_on' => ($asset->warranty_ends_on ?: $asset->serial?->warranty_ends_on)?->toDateString()]);
        } elseif ($tab === 'documents' && Gate::allows('manage-operations')) {
            $records = WorkOrderAttachment::query()->whereHas('workOrder', fn ($q) => $this->assigned($q)->where('installation_id', $installation->id))->select('id', 'work_order_id', 'original_name', 'kind', 'mime_type', 'size', 'created_at')->latest()->paginate(15, ['*'], 'section_page')->withQueryString();
        } elseif ($tab === 'activity') {
            $records = AuditLog::with('user:id,name')->where(fn ($q) => $q->where(fn ($q) => $q->where('auditable_type', Installation::class)->where('auditable_id', $installation->id))
                ->when(Gate::allows('manage-operations'), fn ($q) => $q->orWhere(fn ($q) => $q->where('auditable_type', WorkOrder::class)->whereIn('auditable_id', $this->assigned($installation->workOrders())->select('id')))))
                ->select('id', 'user_id', 'event', 'created_at')->latest()->paginate(15, ['*'], 'section_page')->withQueryString();
        }
        $dossiers = $installation->engineeringDossiers()->latest()->limit(5)->get(['id', 'code', 'title', 'status']);
        $materialRequested = Gate::allows('view-purchases') ? $installation->purchaseDocuments()->where('type', 'purchase_order')->whereNotIn('status', ['draft', 'cancelled'])->min('created_at') : null;

        return ['journey' => app(ProjectJourney::class)->for($request->user(), $installation), 'installation' => $record, 'quote' => Gate::allows('manage-quotes') ? $quote : null, 'opportunity' => $opportunity, 'tab' => $tab, 'mode' => $project ? 'projects' : 'installations',
            'records' => $records, 'dossiers' => $dossiers, 'permissions' => $this->permissions(),
            'costs' => $tab === 'costs' ? app(ProjectProfitability::class)->for($installation) : null,
            'milestones' => ['created' => $installation->created_at?->toISOString(), 'accepted' => $quote?->accepted_at?->toISOString(), 'materials' => $materialRequested, 'scheduled' => $installation->scheduled_date?->toDateString(), 'completed' => $installation->status === 'completed' ? $installation->completed_date?->toDateString() : null]];
    }

    public function afterSales(Request $request): array
    {
        $filters = $this->filters($request);
        $query = $this->apply($this->base(false)->where('status', 'completed'), $filters);
        $ticketIds = $this->assigned(ServiceTicket::query())->whereNotIn('status', ['resolved', 'closed'])->whereNotNull('installation_id')->select('installation_id');
        $plans = $this->assigned(MaintenancePlan::query())->where('is_active', true)->whereNotNull('installation_id');
        $reviewIds = (clone $plans)->whereDate('next_due_on', '<=', today())->select('installation_id');
        $planIds = (clone $plans)->select('installation_id');
        $state = $filters['service_state'] ?? '';
        if ($state === 'incidents') {
            $query->whereIn('id', $ticketIds);
        } elseif ($state === 'review') {
            $query->whereIn('id', $reviewIds);
        } elseif ($state === 'follow_up') {
            $query->whereIn('id', $planIds);
        } elseif ($state === 'none') {
            $query->whereNotIn('id', $planIds)->whereNotIn('id', $ticketIds);
        }
        $scope = (clone $query)->select('installations.id');
        $counts = ['total' => (clone $query)->count(), 'incidents' => (clone $query)->whereIn('id', $ticketIds)->count(), 'review' => (clone $query)->whereIn('id', $reviewIds)->count(), 'follow_up' => (clone $query)->whereIn('id', $planIds)->count()];
        $records = $query->with(['customer:id,first_name,last_name,company_name', 'technician:id,name'])->select(self::FIELDS)->orderBy($filters['sort'] ?? 'completed_date', $filters['direction'] ?? 'desc')->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
        $pageIds = $records->getCollection()->pluck('id');
        $open = $this->assigned(ServiceTicket::query())->whereIn('installation_id', $pageIds)->whereNotIn('status', ['resolved', 'closed'])->selectRaw('installation_id, COUNT(*) as total')->groupBy('installation_id')->pluck('total', 'installation_id');
        $dates = (clone $plans)->whereIn('installation_id', $pageIds)->selectRaw('installation_id, MIN(next_due_on) as next_review, MAX(last_completed_on) as last_review')->groupBy('installation_id')->get()->keyBy('installation_id');
        $items = $records->getCollection()->map(fn ($i) => [...$this->row($i), 'open_tickets' => (int) ($open[$i->id] ?? 0), 'next_review' => $dates->get($i->id)?->next_review ? Carbon::parse($dates->get($i->id)->next_review)->toDateString() : null, 'last_review' => $dates->get($i->id)?->last_review ? Carbon::parse($dates->get($i->id)->last_review)->toDateString() : null, 'has_plan' => $dates->has($i->id), 'service_state' => ($open[$i->id] ?? 0) > 0 ? 'Incidencias' : ($dates->get($i->id)?->next_review && Carbon::parse($dates->get($i->id)->next_review)->lte(today()) ? 'Revisión pendiente' : ($dates->has($i->id) ? 'En seguimiento' : 'Sin plan registrado'))])->all();
        $upcoming = (clone $plans)->whereIn('installation_id', $scope)->with('installation:id,project_reference,site_name')->orderBy('next_due_on')->limit(6)->get(['id', 'installation_id', 'name', 'next_due_on']);
        $tickets = $this->assigned(ServiceTicket::query())->whereIn('installation_id', $scope)->whereNotIn('status', ['resolved', 'closed'])->with('installation:id,project_reference,site_name')->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")->oldest()->limit(6)->get(['id', 'installation_id', 'number', 'title', 'priority', 'status', 'created_at']);

        return ['items' => $items, 'pagination' => collect($records->toArray())->except('data')->all(), 'filters' => $filters, 'counts' => $counts, 'upcoming' => $upcoming, 'tickets' => $tickets, 'permissions' => $this->permissions()];
    }
}
