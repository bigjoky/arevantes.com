<?php

namespace App\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\CrmSetting;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\OpportunityEvent;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SavedCrmFilter;
use App\Models\User;
use App\Services\Crm\LeadResponseSla;
use App\Services\Crm\OpportunityDossier;
use App\Services\Crm\OpportunitySalesCycle;
use App\Services\Customers\OpportunityCustomerResolver;
use App\Services\Documents\OpportunityNumber;
use App\Services\Projects\ProjectJourney;
use App\Support\Crm\OpportunityCatalog;
use App\Support\PhoneNormalizer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    public function index(Request $request): Response
    {
        $section = $request->input('section') === 'analysis' ? 'analysis' : 'pipeline';
        $view = in_array($request->input('view'), ['table', 'calendar'], true) ? $request->input('view') : 'kanban';
        $settings = CrmSetting::current();
        $query = Opportunity::query()
            ->when($request->boolean('archived'), fn ($query) => $query->onlyTrashed())
            ->with([
                'customer:id,first_name,last_name,company_name,phone,email,do_not_contact',
                'installation:id,person_id,project_reference,site_name',
                'owner:id,name',
                'parentOpportunity:id,person_id,code,title,stage',
                'quote:id,opportunity_id,number,status',
                'nextActivity.assignedUser:id,name',
                'stageHistory.changedBy:id,name',
            ]);
        $this->applyFilters($query, $request, $settings->stale_after_days);
        $filteredQuery = clone $query;
        $stageTotals = (clone $query)->withoutEagerLoads()->reorder()->selectRaw('stage, COUNT(*) count, COALESCE(SUM(estimated_value), 0) amount, COALESCE(SUM(estimated_value * probability / 100.0), 0) weighted')->groupBy('stage')->get()->toBase()->keyBy('stage');
        $pagination = null;
        if ($section === 'pipeline' && $view === 'table') {
            $sort = in_array($request->input('sort'), ['title', 'estimated_value', 'created_at'], true) ? $request->input('sort') : 'created_at';
            $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
            $pagination = (clone $query)->orderBy($sort, $direction)->orderBy('id', $direction)->paginate(25)->withQueryString();
            $opportunities = $pagination->getCollection();
        } elseif ($section === 'pipeline') {
            $stagePage = max(1, min(10000, $request->integer('stage_page', 1)));
            $pageStage = $request->input('page_stage');
            $ranked = (clone $query)->withoutEagerLoads()->reorder()->selectRaw('id, stage, ROW_NUMBER() OVER (PARTITION BY stage ORDER BY created_at DESC, id DESC) as stage_row');
            $ids = DB::query()->fromSub($ranked->toBase(), 'ranked')->where(fn ($rows) => $rows
                ->where('stage_row', '<=', 8)
                ->when(array_key_exists((string) $pageStage, OpportunityCatalog::STAGES), fn ($rows) => $rows->orWhere(fn ($stageRows) => $stageRows->where('stage', $pageStage)->whereBetween('stage_row', [($stagePage - 1) * 8 + 1, $stagePage * 8]))))
                ->pluck('id');
            if ($request->filled('opportunity')) {
                $ids->push($request->integer('opportunity'));
            }
            $opportunities = $query->whereIn('id', $ids)->latest()->get();
        } else {
            $opportunities = $query->latest()->get();
        }

        $open = $opportunities->whereNotIn('stage', ['won', 'lost']);
        $period = in_array($request->input('period'), ['30', '90', '365', 'all'], true)
            ? (string) $request->input('period')
            : '90';
        $periodStart = $period === 'all' ? null : now()->subDays((int) $period);
        $won = $opportunities->where('stage', 'won')->filter(
            fn (Opportunity $item) => $this->closedInPeriod($item, 'won', $periodStart),
        );
        $lost = $opportunities->where('stage', 'lost')->filter(
            fn (Opportunity $item) => $this->closedInPeriod($item, 'lost', $periodStart),
        );
        $closedCount = $won->count() + $lost->count();
        $averageCycleDays = $won->map(function (Opportunity $item): ?float {
            $wonAt = $item->stageHistory->firstWhere('to_stage', 'won')?->changed_at;

            return $wonAt ? $item->created_at->diffInHours($wonAt) / 24 : null;
        })->filter(fn ($days) => $days !== null)->average();

        $salesCycle = app(OpportunitySalesCycle::class);
        $responseSla = app(LeadResponseSla::class);
        $responseStatuses = $opportunities->mapWithKeys(
            fn (Opportunity $item) => [$item->id => $responseSla->status($item)],
        );
        $measuredResponses = $responseStatuses->filter(fn (string $status) => in_array($status, ['met', 'breached'], true));
        $responseMinutes = $opportunities
            ->map(fn (Opportunity $item) => $responseSla->responseMinutes($item))
            ->filter(fn (?int $minutes) => $minutes !== null);
        $responsePerformance = $section === 'analysis' ? $opportunities
            ->whereNotNull('owner_user_id')
            ->groupBy('owner_user_id')
            ->map(function ($items) use ($responseSla, $responseStatuses): array {
                $eligible = $items->filter(
                    fn (Opportunity $item) => $responseStatuses->get($item->id) !== 'not_applicable',
                );
                $measured = $eligible->filter(
                    fn (Opportunity $item) => in_array($responseStatuses->get($item->id), ['met', 'breached'], true),
                );
                $minutes = $eligible
                    ->map(fn (Opportunity $item) => $responseSla->responseMinutes($item))
                    ->filter(fn (?int $value) => $value !== null);

                return [
                    'owner_id' => $items->first()->owner_user_id,
                    'owner_name' => $items->first()->owner?->name ?? __('Unassigned'),
                    'total' => $eligible->count(),
                    'met' => $eligible->filter(fn (Opportunity $item) => $responseStatuses->get($item->id) === 'met')->count(),
                    'breached' => $eligible->filter(fn (Opportunity $item) => $responseStatuses->get($item->id) === 'breached')->count(),
                    'pending' => $eligible->filter(fn (Opportunity $item) => $responseStatuses->get($item->id) === 'pending')->count(),
                    'met_rate' => $measured->isNotEmpty()
                        ? round($measured->filter(fn (Opportunity $item) => $responseStatuses->get($item->id) === 'met')->count() / $measured->count() * 100, 1)
                        : null,
                    'average_minutes' => $minutes->isNotEmpty() ? round((float) $minutes->average(), 1) : null,
                ];
            })
            ->filter(fn (array $row) => $row['total'] > 0)
            ->sortByDesc('breached')
            ->values() : collect();

        $request->validate(['calendar_month' => ['nullable', 'date_format:Y-m']]);
        $calendarMonth = Carbon::parse(($request->input('calendar_month') ?: now()->format('Y-m')).'-01');
        $activitiesQuery = CrmActivity::query()->whereIn('status', ['pending', 'in_progress'])->whereNotNull('due_at')->whereIn('opportunity_id', (clone $filteredQuery)->reorder()->select('id'))->with('opportunity:id,title')->orderBy('due_at')->orderBy('id');
        $calendar = $view === 'calendar' ? (clone $activitiesQuery)->whereBetween('due_at', [$calendarMonth->copy()->startOfMonth(), $calendarMonth->copy()->endOfMonth()])->paginate(25, ['*'], 'calendar_page')->withQueryString() : null;

        return Inertia::render('opportunities/index', [
            'opportunities' => $opportunities->map(function (Opportunity $item) use ($salesCycle, $responseSla, $settings): array {
                $scoreBreakdown = $salesCycle->scoreBreakdown($item);
                $data = $item->toArray();
                unset($data['stage_history']);

                return [
                    ...$data,
                    'stage_label' => OpportunityCatalog::STAGES[$item->stage] ?? $item->stage,
                    'commercial_score' => min(100, (int) collect($scoreBreakdown)->sum('points')),
                    'expected_close_on' => $item->expected_close_on?->toDateString(),
                    'renewal_due_on' => $item->renewal_due_on?->toDateString(),
                    'available_stages' => $salesCycle->availableTransitions($item),
                    'stage_entered_at' => $this->stageEnteredAt($item)->toIso8601String(),
                    'days_in_stage' => round($this->stageEnteredAt($item)->diffInHours(now()) / 24, 1),
                    'is_stale' => ! in_array($item->stage, ['won', 'lost'], true)
                        && $this->stageEnteredAt($item)->lessThan(now()->subDays($settings->stale_after_days)),
                    'first_response_due_at' => $item->first_response_due_at?->toIso8601String(),
                    'first_responded_at' => $item->first_responded_at?->toIso8601String(),
                    'first_response_minutes' => $responseSla->responseMinutes($item),
                    'response_sla_status' => $responseSla->status($item),
                ];
            }),
            'customers' => Person::where('category', 'customer')->orderBy('company_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name', 'phone', 'email', 'preferred_contact_channel', 'do_not_contact']),
            'installations' => Installation::orderBy('project_reference')->get(['id', 'person_id', 'project_reference', 'site_name']),
            'users' => User::whereIn('role', ['admin', 'commercial'])->orderBy('name')->get(['id', 'name']),
            'sourceOptions' => OpportunityCatalog::SOURCES,
            'commercialKindOptions' => OpportunityCatalog::COMMERCIAL_KINDS,
            'recurrenceOptions' => OpportunityCatalog::RECURRENCE_INTERVALS,
            'lostReasonOptions' => OpportunityCatalog::LOST_REASONS,
            'wonOpportunities' => Opportunity::query()
                ->where('stage', 'won')
                ->orderByDesc('updated_at')
                ->get(['id', 'person_id', 'code', 'title']),
            'filters' => [
                ...$request->only(['search', 'stage', 'owner', 'source', 'commercial_kind', 'attention', 'archived', 'status', 'from', 'to', 'sort', 'direction', 'page_stage', 'stage_page', 'calendar_month']),
                'period' => $period,
                'view' => $view,
            ],
            'section' => $section,
            'stageOptions' => OpportunityCatalog::STAGES,
            'stageTotals' => $stageTotals->map(fn ($row) => ['count' => (int) $row->count, 'amount' => (float) $row->amount]),
            'pagination' => $pagination ? ['current_page' => $pagination->currentPage(), 'last_page' => $pagination->lastPage(), 'total' => $pagination->total(), 'prev' => $pagination->previousPageUrl(), 'next' => $pagination->nextPageUrl()] : null,
            'crmTasks' => $calendar ? $calendar->items() : $activitiesQuery->limit(6)->get(),
            'calendarPagination' => $calendar ? ['month' => $calendarMonth->format('Y-m'), 'prev_month' => $calendarMonth->copy()->subMonth()->format('Y-m'), 'next_month' => $calendarMonth->copy()->addMonth()->format('Y-m'), 'prev' => $calendar->previousPageUrl(), 'next' => $calendar->nextPageUrl(), 'total' => $calendar->total()] : null,
            'crmEvents' => OpportunityEvent::query()->whereIn('opportunity_id', (clone $filteredQuery)->reorder()->select('id'))->with(['opportunity:id,title', 'actor:id,name'])->latest('occurred_at')->limit(6)->get(),
            'metricsScope' => $section === 'analysis' ? 'filtered_pipeline' : 'loaded_cards_with_aggregate_values',
            'metrics' => [
                'pipeline_value' => round((float) $stageTotals->except(['won', 'lost'])->sum('amount'), 2),
                'weighted_forecast' => round((float) $stageTotals->except(['won', 'lost'])->sum('weighted'), 2),
                'won_value' => round((float) $won->sum('estimated_value'), 2),
                'new_leads' => $opportunities->filter(fn (Opportunity $item) => ! $periodStart || $item->created_at->greaterThanOrEqualTo($periodStart))->count(),
                'won_count' => $won->count(),
                'lost_count' => $lost->count(),
                'conversion_rate' => $closedCount > 0 ? round($won->count() / $closedCount * 100, 1) : 0,
                'average_cycle_days' => $averageCycleDays !== null ? round($averageCycleDays, 1) : null,
                'overdue' => $open->filter(fn (Opportunity $item) => $item->expected_close_on?->isPast())->count(),
                'without_next_action' => $open->whereNull('nextActivity')->count(),
                'stale' => $open->filter(fn (Opportunity $item) => $this->stageEnteredAt($item)->lessThan(now()->subDays($settings->stale_after_days)))->count(),
                'renewals_due' => $open->filter(fn (Opportunity $item) => in_array($item->commercial_kind, ['renewal', 'recurring'], true)
                    && $item->renewal_due_on?->lessThanOrEqualTo(today()->addDays(30)))->count(),
                'response_sla_breached' => $responseStatuses->filter(fn (string $status) => $status === 'breached')->count(),
                'response_sla_pending' => $responseStatuses->filter(fn (string $status) => $status === 'pending')->count(),
                'response_sla_met_rate' => $measuredResponses->isNotEmpty()
                    ? round($measuredResponses->filter(fn (string $status) => $status === 'met')->count() / $measuredResponses->count() * 100, 1)
                    : null,
                'average_first_response_minutes' => $responseMinutes->isNotEmpty()
                    ? round((float) $responseMinutes->average(), 1)
                    : null,
                'funnel' => collect(array_keys(OpportunityCatalog::STAGES))->mapWithKeys(fn (string $stage) => [$stage => $opportunities->where('stage', $stage)->count()]),
                'stage_days' => $this->averageDaysByStage($opportunities),
            ],
            'forecast' => $section === 'analysis' && ! $request->boolean('archived') ? $this->forecast($open) : [],
            'responsePerformance' => $responsePerformance,
            'savedFilters' => SavedCrmFilter::query()->where('user_id', $request->user()->id)->where('scope', 'opportunities')->orderByDesc('is_default')->orderBy('name')->get(),
            'crmSettings' => $settings,
            'canConfigureCrm' => $request->user()->hasRole('admin'),
        ]);
    }

    public function show(Opportunity $opportunity, OpportunityDossier $dossier, OpportunitySalesCycle $salesCycle): Response
    {
        if ($opportunity->trashed()) {
            return Inertia::render('opportunities/archived', [
                'opportunity' => $opportunity->only(['id', 'code', 'title', 'deleted_at']),
            ]);
        }

        $opportunity->load([
            'customer',
            'installation:id,person_id,project_reference,site_name,service_type,status',
            'owner:id,name',
            'creator:id,name',
            'updater:id,name',
            'parentOpportunity:id,code,title,stage',
            'followUpOpportunities:id,parent_opportunity_id,code,title,stage,estimated_value',
            'events.actor:id,name',
            'stageHistory.changedBy:id,name',
            'activities' => fn ($query) => $query->with(['creator:id,name', 'assignedUser:id,name', 'completedBy:id,name'])->latest(),
            'quote' => fn ($query) => $query->with([
                'versions.createdBy:id,name',
                'statusHistory.changedBy:id,name',
            ]),
        ]);

        $currentQuote = Quote::where('opportunity_id', $opportunity->id)->where('status', 'accepted')->latest('id')->first() ?? Quote::where('opportunity_id', $opportunity->id)->latest('id')->first();
        $currentQuote?->load(['versions.createdBy:id,name', 'statusHistory.changedBy:id,name']);
        $opportunity->setRelation('quote', $currentQuote);

        $timeline = $dossier->timeline($opportunity);
        $opportunity->unsetRelation('events')->unsetRelation('stageHistory');
        $opportunity->quote?->unsetRelation('statusHistory');

        return Inertia::render('opportunities/show', [
            'stageOptions' => OpportunityCatalog::STAGES,
            'opportunity' => [
                ...$opportunity->toArray(),
                'expected_close_on' => $opportunity->expected_close_on?->toDateString(),
                'renewal_due_on' => $opportunity->renewal_due_on?->toDateString(),
                'available_stages' => $salesCycle->availableTransitions($opportunity),
                'score_breakdown' => $salesCycle->scoreBreakdown($opportunity),
            ],
            'timeline' => $timeline,
            'journey' => app(ProjectJourney::class)->for(request()->user(), opportunity: $opportunity),
            'sourceOptions' => OpportunityCatalog::SOURCES,
            'commercialKindOptions' => OpportunityCatalog::COMMERCIAL_KINDS,
            'users' => User::whereIn('role', ['admin', 'commercial'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(
        Request $request,
        OpportunitySalesCycle $salesCycle,
        OpportunityCustomerResolver $customerResolver,
        LeadResponseSla $responseSla,
    ): RedirectResponse {
        $customerInput = $this->validatedCustomerInput($request);
        $opportunityInput = $this->validated($request, true);
        $nextActionInput = $this->validatedNextAction($request, $opportunityInput['stage']);
        $createdCustomer = false;
        $opportunity = null;

        DB::transaction(function () use ($customerInput, $opportunityInput, $nextActionInput, $request, $salesCycle, $customerResolver, $responseSla, &$createdCustomer, &$opportunity): void {
            $customer = filled($customerInput['phone'])
                ? $customerResolver->resolve(
                    $customerInput['phone'],
                    [
                        ...$customerInput['attributes'],
                        'lead_source' => ($opportunityInput['source'] ?? null) === 'other'
                            ? $opportunityInput['source_detail']
                            : ($opportunityInput['source'] ?? null),
                    ],
                    $opportunityInput['person_id'] ?? null,
                )
                : Person::query()
                    ->where('category', 'customer')
                    ->findOrFail($opportunityInput['person_id']);
            $createdCustomer = $customer->wasRecentlyCreated;
            $this->recordCustomerConsent($customer, $customerInput['consent'], $request);
            $data = $salesCycle->prepareActiveData([
                ...$opportunityInput,
                'person_id' => $customer->id,
            ], $request->user());
            $this->ensureInstallationBelongsToCustomer($data);
            $this->ensureCommercialOrigin($data);

            $opportunity = Opportunity::create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $opportunity->update(['code' => app(OpportunityNumber::class)->next($opportunity->id)]);
            $responseSla->start($opportunity);
            $salesCycle->ensureNextAction($opportunity, $request->user(), $nextActionInput);
            $salesCycle->refreshScore($opportunity);
        });

        return to_route('opportunities.show', $opportunity)->with('toast', [
            'type' => 'success',
            'message' => $createdCustomer
                ? __('Customer and opportunity created.')
                : __('Opportunity created.'),
        ]);
    }

    public function update(Request $request, Opportunity $opportunity, OpportunitySalesCycle $salesCycle): RedirectResponse
    {
        $changeNote = $request->validate([
            'change_note' => ['required', 'string', 'min:5', 'max:2000'],
        ])['change_note'] ?? null;
        $data = $salesCycle->prepareActiveData($this->validated($request), $request->user());
        $this->ensureInstallationBelongsToCustomer($data);
        $this->ensureCommercialOrigin($data, $opportunity);
        abort_if(
            $opportunity->quote()->exists()
                && ((int) $opportunity->person_id !== (int) $data['person_id']
                    || (int) $opportunity->installation_id !== (int) ($data['installation_id'] ?? 0)),
            422,
            __('The customer or installation cannot be changed after preparing a quote.'),
        );
        DB::transaction(function () use ($opportunity, $data, $salesCycle, $request, $changeNote): void {
            $salesCycle->assertCanTransition($opportunity, $data['stage']);
            $opportunity->stageChangeReason = $changeNote;
            $opportunity->stageChangeActorId = $request->user()->id;
            $opportunity->update([...$data, 'updated_by' => $request->user()->id]);
            $salesCycle->synchronizeStage($opportunity, $request->user());
            $salesCycle->refreshScore($opportunity);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Opportunity updated.')]);
    }

    public function changeStage(Request $request, Opportunity $opportunity, OpportunitySalesCycle $salesCycle): RedirectResponse
    {
        $data = $request->validate(['stage' => ['required', Rule::in(array_keys(OpportunityCatalog::STAGES))], 'expected_stage' => ['required', 'string'], 'probability' => ['required', 'integer', 'between:0,100'], 'change_note' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($opportunity, $request, $data, $salesCycle): void {
            $locked = Opportunity::query()->lockForUpdate()->findOrFail($opportunity->id);
            if ($locked->stage !== $data['expected_stage']) {
                throw ValidationException::withMessages(['stage' => __('La oportunidad ha cambiado. Recarga el pipeline antes de moverla.')]);
            }
            if ($data['stage'] === 'lost') {
                throw ValidationException::withMessages(['stage' => __('Indica el motivo de pérdida en el formulario de oportunidad.')]);
            }
            $salesCycle->assertCanTransition($locked, $data['stage']);
            $locked->stageChangeReason = $data['change_note'];
            $locked->stageChangeActorId = $request->user()->id;
            $locked->update(['stage' => $data['stage'], 'probability' => $data['stage'] === 'won' ? 100 : $data['probability'], 'updated_by' => $request->user()->id]);
            $salesCycle->synchronizeStage($locked, $request->user());
            $salesCycle->refreshScore($locked);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Opportunity updated.')]);
    }

    public function storeNote(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:2', 'max:5000']]);
        $opportunity->events()->create([
            'actor_id' => $request->user()->id,
            'event_type' => 'note_added',
            'summary' => __('Commercial note added'),
            'note' => $data['note'],
            'occurred_at' => now(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Note added to the opportunity history.')]);
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        abort_if($opportunity->quote()->exists(), 422, __('An opportunity linked to a quote cannot be archived.'));
        $opportunity->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Opportunity archived.')]);
    }

    public function storeActivity(Request $request, Opportunity $opportunity, OpportunitySalesCycle $salesCycle): RedirectResponse
    {
        abort_if(in_array($opportunity->stage, ['won', 'lost'], true), 422, __('Closed opportunities cannot receive new activities.'));
        $data = $request->validate([
            'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['admin', 'commercial']))],
            'type' => ['required', Rule::in(['task', 'visit', 'reminder', 'call', 'email', 'meeting'])],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'due_at' => ['required', 'date'],
        ]);

        CrmActivity::create([
            ...$data,
            'opportunity_id' => $opportunity->id,
            'person_id' => $opportunity->person_id,
            'installation_id' => $opportunity->installation_id,
            'created_by' => $request->user()->id,
            'status' => 'pending',
        ]);
        $salesCycle->refreshScore($opportunity);

        return back()->with('toast', ['type' => 'success', 'message' => __('Next action scheduled.')]);
    }

    public function completeActivity(
        Request $request,
        Opportunity $opportunity,
        CrmActivity $activity,
        OpportunitySalesCycle $salesCycle,
        LeadResponseSla $responseSla,
    ): RedirectResponse {
        abort_unless((int) $activity->opportunity_id === (int) $opportunity->id, 404);
        abort_if($activity->work_order_id !== null, 422, __('Linked work must be completed from its work order.'));
        abort_if(in_array($activity->status, ['completed', 'cancelled'], true), 422, __('This activity is already closed.'));
        $data = $request->validate([
            'completion_notes' => ['required', 'string', 'min:2', 'max:5000'],
            'schedule_next' => ['required', 'boolean'],
            'next_action_type' => ['nullable', Rule::requiredIf($request->boolean('schedule_next')), Rule::in(['task', 'visit', 'reminder', 'call', 'email', 'meeting'])],
            'next_action_title' => ['nullable', Rule::requiredIf($request->boolean('schedule_next')), 'string', 'max:255'],
            'next_action_priority' => ['nullable', Rule::requiredIf($request->boolean('schedule_next')), Rule::in(['low', 'normal', 'high'])],
            'next_action_due_at' => ['nullable', Rule::requiredIf($request->boolean('schedule_next')), 'date', ...($request->user()->hasRole('admin') ? [] : ['after:now'])],
            'next_action_assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['admin', 'commercial']))],
            'next_action_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $next = DB::transaction(function () use ($request, $opportunity, $activity, $data, $salesCycle, $responseSla): ?CrmActivity {
            $lockedOpportunity = Opportunity::query()->lockForUpdate()->findOrFail($opportunity->id);
            $lockedActivity = CrmActivity::query()->lockForUpdate()->findOrFail($activity->id);
            abort_unless((int) $lockedActivity->opportunity_id === (int) $lockedOpportunity->id, 404);
            abort_if(in_array($lockedActivity->status, ['completed', 'cancelled'], true), 422, __('This activity is already closed.'));

            $lockedActivity->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $request->user()->id,
                'completion_notes' => trim($data['completion_notes']),
                'blocked_reason' => null,
            ]);
            $responseSla->record($lockedActivity);

            if ($data['schedule_next']) {
                CrmActivity::create([
                    'opportunity_id' => $lockedOpportunity->id,
                    'person_id' => $lockedOpportunity->person_id,
                    'installation_id' => $lockedOpportunity->installation_id,
                    'assigned_user_id' => $data['next_action_assigned_user_id'] ?? $lockedOpportunity->owner_user_id,
                    'created_by' => $request->user()->id,
                    'type' => $data['next_action_type'],
                    'title' => trim($data['next_action_title']),
                    'notes' => filled($data['next_action_notes'] ?? null) ? trim($data['next_action_notes']) : null,
                    'priority' => $data['next_action_priority'],
                    'status' => 'pending',
                    'due_at' => $data['next_action_due_at'],
                ]);
            }

            $lockedOpportunity->unsetRelation('nextActivity');
            $salesCycle->ensureNextAction($lockedOpportunity, $request->user());
            $salesCycle->refreshScore($lockedOpportunity);

            return $lockedOpportunity->nextActivity()->first();
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => $next
                ? __('Activity completed. Next action: :title.', ['title' => $next->title])
                : __('Activity completed. There are no further actions.'),
        ]);
    }

    public function restore(int $opportunity): RedirectResponse
    {
        DB::transaction(function () use ($opportunity): void {
            $record = Opportunity::withTrashed()->lockForUpdate()->findOrFail($opportunity);
            if ($record->trashed()) {
                $record->restore();
            }
        });

        return to_route('opportunities.index')->with('toast', ['type' => 'success', 'message' => __('Opportunity restored.')]);
    }

    private function validated(Request $request, bool $creating = false): array
    {
        $request->merge(['commercial_kind' => $request->input('commercial_kind', 'new_business')]);

        $data = $request->validate([
            'person_id' => [$creating ? 'nullable' : 'required', Rule::exists('people', 'id')->where('category', 'customer')],
            'installation_id' => ['nullable', 'exists:installations,id'],
            'owner_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['admin', 'commercial']))],
            'title' => ['required', 'string', 'max:255'],
            'commercial_kind' => ['required', Rule::in(array_keys(OpportunityCatalog::COMMERCIAL_KINDS))],
            'parent_opportunity_id' => [
                Rule::requiredIf(in_array($request->input('commercial_kind'), ['renewal', 'upsell'], true)),
                'nullable',
                'exists:opportunities,id',
            ],
            'recurrence_interval' => [
                Rule::requiredIf($request->input('commercial_kind') === 'recurring'),
                'nullable',
                Rule::in(array_keys(OpportunityCatalog::RECURRENCE_INTERVALS)),
            ],
            'renewal_due_on' => [
                Rule::requiredIf(in_array($request->input('commercial_kind'), ['renewal', 'recurring'], true)),
                'nullable',
                'date',
            ],
            'stage' => ['required', Rule::in(array_keys(OpportunityCatalog::STAGES))],
            'probability' => ['required', 'integer', 'between:0,100'],
            'estimated_value' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'expected_close_on' => ['nullable', 'date'],
            'source' => ['nullable', Rule::in(array_keys(OpportunityCatalog::SOURCES))],
            'source_detail' => [Rule::requiredIf($request->input('source') === 'other'), 'nullable', 'string', 'max:255'],
            'lost_reason' => [Rule::requiredIf($request->input('stage') === 'lost'), 'nullable', Rule::in(array_keys(OpportunityCatalog::LOST_REASONS))],
            'lost_reason_detail' => [Rule::requiredIf($request->input('stage') === 'lost' && $request->input('lost_reason') === 'other'), 'nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['source_detail'] = ($data['source'] ?? null) === 'other' ? ($data['source_detail'] ?? null) : null;
        if ($data['stage'] !== 'lost') {
            $data['lost_reason'] = null;
            $data['lost_reason_detail'] = null;
        } elseif ($data['lost_reason'] !== 'other') {
            $data['lost_reason_detail'] = null;
        }

        if ($data['commercial_kind'] === 'new_business') {
            $data['parent_opportunity_id'] = null;
        }
        if ($data['commercial_kind'] !== 'recurring') {
            $data['recurrence_interval'] = null;
        }
        if (! in_array($data['commercial_kind'], ['renewal', 'recurring'], true)) {
            $data['renewal_due_on'] = null;
        }

        return $data;
    }

    /** @return array{phone: ?string, attributes: array{first_name: ?string, last_name: ?string, company_name: ?string, email: ?string, preferred_contact_channel: string}, consent: array{status: string, channel: string, notes: ?string}} */
    private function validatedCustomerInput(Request $request): array
    {
        $data = $request->validate([
            'customer_phone' => [
                Rule::requiredIf(! $request->filled('person_id')),
                'nullable',
                'string',
                'max:40',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! PhoneNormalizer::isPlausibleMobile((string) $value)) {
                        $fail(__('Enter a valid mobile number.'));
                    }
                },
            ],
            'customer_first_name' => ['nullable', 'string', 'max:255'],
            'customer_last_name' => ['nullable', 'string', 'max:255'],
            'customer_company_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'customer_preferred_contact_channel' => ['nullable', Rule::in(['phone', 'whatsapp', 'email', 'sms'])],
            'customer_consent_status' => ['nullable', Rule::in(['not_recorded', 'granted', 'denied'])],
            'customer_consent_channel' => [
                Rule::requiredIf(in_array($request->input('customer_consent_status'), ['granted', 'denied'], true)),
                'nullable',
                Rule::in(['phone', 'whatsapp', 'email', 'sms']),
            ],
            'customer_consent_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $phone = filled($data['customer_phone'] ?? null) ? trim((string) $data['customer_phone']) : null;
        if ($phone && ! $request->filled('person_id')) {
            $existing = Person::query()
                ->where('category', 'customer')
                ->where('phone_normalized', PhoneNormalizer::normalize($phone))
                ->exists();

            if (! $existing && blank($data['customer_first_name'] ?? null) && blank($data['customer_company_name'] ?? null)) {
                throw ValidationException::withMessages([
                    'customer_first_name' => __('Enter a customer name or company name to create the customer.'),
                ]);
            }
        }

        return [
            'phone' => $phone,
            'attributes' => [
                'first_name' => $data['customer_first_name'] ?? null,
                'last_name' => $data['customer_last_name'] ?? null,
                'company_name' => $data['customer_company_name'] ?? null,
                'email' => $data['customer_email'] ?? null,
                'preferred_contact_channel' => $data['customer_preferred_contact_channel'] ?? 'phone',
            ],
            'consent' => [
                'status' => $data['customer_consent_status'] ?? 'not_recorded',
                'channel' => $data['customer_consent_channel'] ?? $data['customer_preferred_contact_channel'] ?? 'phone',
                'notes' => $data['customer_consent_notes'] ?? null,
            ],
        ];
    }

    /** @return array{type: string, title: string, priority: string, due_at: string}|null */
    private function validatedNextAction(Request $request, string $stage): ?array
    {
        if (in_array($stage, ['won', 'lost'], true)) {
            return null;
        }

        $data = $request->validate([
            'next_action_type' => ['nullable', Rule::in(['task', 'visit', 'reminder', 'call', 'email', 'meeting'])],
            'next_action_title' => ['nullable', 'string', 'max:255'],
            'next_action_priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'next_action_due_at' => ['nullable', 'date', ...($request->user()->hasRole('admin') ? [] : ['after:now'])],
        ]);
        $dueAt = now()->addDay();
        while ($dueAt->isWeekend()) {
            $dueAt = $dueAt->addDay();
        }

        return [
            'type' => $data['next_action_type'] ?? 'call',
            'title' => filled($data['next_action_title'] ?? null)
                ? trim((string) $data['next_action_title'])
                : __('Contact and qualify customer'),
            'priority' => $data['next_action_priority'] ?? 'normal',
            'due_at' => $data['next_action_due_at'] ?? $dueAt->setTime(9, 0)->toDateTimeString(),
        ];
    }

    /** @param  array{status: string, channel: string, notes: ?string}  $consent */
    private function recordCustomerConsent(Person $customer, array $consent, Request $request): void
    {
        if ($consent['status'] === 'not_recorded') {
            return;
        }

        $customer->consents()->create([
            'recorded_by' => $request->user()->id,
            'channel' => $consent['channel'],
            'status' => $consent['status'],
            'lawful_basis' => 'consent',
            'source' => 'opportunity_form',
            'captured_at' => now(),
            'notes' => $consent['notes'],
        ]);
        $customer->update([
            'preferred_contact_channel' => $consent['channel'],
            'do_not_contact' => $consent['status'] === 'denied',
        ]);
    }

    private function ensureInstallationBelongsToCustomer(array $data): void
    {
        if (empty($data['installation_id'])) {
            return;
        }

        $installation = Installation::findOrFail($data['installation_id']);
        abort_if((int) $installation->person_id !== (int) $data['person_id'], 422, __('The installation does not belong to this customer.'));
    }

    private function ensureCommercialOrigin(array $data, ?Opportunity $opportunity = null): void
    {
        if (empty($data['parent_opportunity_id'])) {
            return;
        }

        abort_if($opportunity && (int) $opportunity->id === (int) $data['parent_opportunity_id'], 422, __('An opportunity cannot originate from itself.'));

        $parent = Opportunity::findOrFail($data['parent_opportunity_id']);
        abort_if((int) $parent->person_id !== (int) $data['person_id'], 422, __('The originating opportunity must belong to the same customer.'));
        abort_if($parent->stage !== 'won', 422, __('Only a won opportunity can originate a renewal or additional sale.'));
    }

    private function applyFilters(Builder $query, Request $request, int $staleAfterDays): void
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'status' => ['nullable', Rule::in(['active', 'all', 'won', 'lost'])]]);
        $search = trim((string) $request->input('search'));

        $query
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('code', "%{$search}%")
                ->orWhereLike('title', "%{$search}%")
                ->orWhereHas('installation', fn ($installation) => $installation->whereLike('project_reference', "%{$search}%")->orWhereLike('site_name', "%{$search}%"))
                ->orWhereHas('customer', fn ($customer) => $customer
                    ->whereLike('company_name', "%{$search}%")
                    ->orWhereLike('first_name', "%{$search}%")
                    ->orWhereLike('last_name', "%{$search}%")->orWhereLike('email', "%{$search}%")->orWhereLike('phone', "%{$search}%"))))
            ->when($request->input('status') === 'active', fn ($query) => $query->whereNotIn('stage', ['won', 'lost']))
            ->when(in_array($request->input('status'), ['won', 'lost']), fn ($query) => $query->where('stage', $request->input('status')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('to')))
            ->when($request->filled('stage'), fn ($query) => $query->where('stage', (string) $request->input('stage')))
            ->when($request->filled('owner'), fn ($query) => $query->where(
                'owner_user_id',
                $request->input('owner') === 'me' ? $request->user()->id : $request->integer('owner'),
            ))
            ->when($request->filled('source'), fn ($query) => $query->where('source', (string) $request->input('source')))
            ->when($request->filled('commercial_kind'), fn ($query) => $query->where('commercial_kind', (string) $request->input('commercial_kind')))
            ->when($request->input('attention') === 'overdue', fn ($query) => $query
                ->whereNotIn('stage', ['won', 'lost'])
                ->whereDate('expected_close_on', '<', today()))
            ->when($request->input('attention') === 'no_action', fn ($query) => $query
                ->whereNotIn('stage', ['won', 'lost'])
                ->whereDoesntHave('activities', fn ($activities) => $activities
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereNotNull('due_at')))
            ->when($request->input('attention') === 'stale', fn ($query) => $query
                ->whereNotIn('stage', ['won', 'lost'])
                ->whereRaw(
                    'coalesce((select max(changed_at) from opportunity_stage_histories where opportunity_id = opportunities.id), opportunities.created_at) < ?',
                    [now()->subDays($staleAfterDays)],
                ))
            ->when($request->input('attention') === 'response_sla', fn ($query) => $query
                ->whereNotIn('stage', ['won', 'lost'])
                ->whereNull('first_responded_at')
                ->whereNotNull('first_response_due_at')
                ->where('first_response_due_at', '<', now()))
            ->when($request->input('attention') === 'renewal_due', fn ($query) => $query
                ->whereNotIn('stage', ['won', 'lost'])
                ->whereIn('commercial_kind', ['renewal', 'recurring'])
                ->whereDate('renewal_due_on', '<=', today()->addDays(30)));
    }

    private function closedInPeriod(Opportunity $opportunity, string $stage, ?CarbonInterface $periodStart): bool
    {
        $changedAt = $opportunity->stageHistory->firstWhere('to_stage', $stage)?->changed_at;

        return $changedAt && (! $periodStart || $changedAt->greaterThanOrEqualTo($periodStart));
    }

    private function averageDaysByStage($opportunities): array
    {
        $durations = array_fill_keys(array_keys(OpportunityCatalog::STAGES), []);

        foreach ($opportunities as $opportunity) {
            $history = $opportunity->stageHistory->sortBy('changed_at')->values();
            foreach ($history as $index => $change) {
                $end = $history->get($index + 1)?->changed_at ?? now();
                $durations[$change->to_stage][] = $change->changed_at->diffInHours($end) / 24;
            }
        }

        return collect($durations)->map(fn (array $days) => count($days) ? round(collect($days)->average(), 1) : null)->all();
    }

    private function stageEnteredAt(Opportunity $opportunity): CarbonInterface
    {
        return $opportunity->stageHistory->first()?->changed_at ?? $opportunity->created_at;
    }

    private function forecast($opportunities): array
    {
        return $opportunities
            ->groupBy(fn (Opportunity $opportunity) => ($opportunity->expected_close_on?->format('Y-m') ?? 'unscheduled').'|'.($opportunity->owner_user_id ?? 'unassigned'))
            ->map(function ($items): array {
                /** @var Opportunity $first */
                $first = $items->first();
                $amount = (float) $items->sum('estimated_value');
                $weighted = (float) $items->sum(fn (Opportunity $item) => (float) $item->estimated_value * $item->probability / 100);

                return [
                    'month' => $first->expected_close_on?->format('Y-m'),
                    'owner_id' => $first->owner_user_id,
                    'owner_name' => $first->owner?->name ?? __('Unassigned'),
                    'count' => $items->count(),
                    'amount' => round($amount, 2),
                    'weighted_amount' => round($weighted, 2),
                    'average_probability' => $amount > 0 ? round($weighted / $amount * 100, 1) : 0,
                    'opportunities' => $items->map(fn (Opportunity $item) => [
                        'id' => $item->id,
                        'code' => $item->code,
                        'title' => $item->title,
                        'amount' => round((float) $item->estimated_value, 2),
                        'probability' => $item->probability,
                        'weighted_amount' => round((float) $item->estimated_value * $item->probability / 100, 2),
                    ])->values()->all(),
                ];
            })
            ->sortBy(fn (array $row) => ($row['month'] ?? '9999-99').'|'.$row['owner_name'])
            ->values()
            ->all();
    }
}
