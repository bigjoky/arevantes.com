<?php

namespace App\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\SavedCrmFilter;
use App\Models\User;
use App\Services\Crm\OpportunitySalesCycle;
use App\Support\Crm\OpportunityCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    private const STAGES = ['new', 'qualified', 'survey', 'proposal', 'negotiation', 'won', 'lost'];

    public function index(Request $request): Response
    {
        $query = Opportunity::query()
            ->when($request->boolean('archived'), fn ($query) => $query->onlyTrashed())
            ->with([
                'customer:id,first_name,last_name,company_name',
                'installation:id,person_id,project_reference,site_name',
                'owner:id,name',
                'quote:id,opportunity_id,number,status',
                'nextActivity.assignedUser:id,name',
                'stageHistory.changedBy:id,name',
            ]);
        $this->applyFilters($query, $request);
        $opportunities = $query->latest()->get();

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

        return Inertia::render('opportunities/index', [
            'opportunities' => $opportunities->map(fn (Opportunity $item) => [
                ...$item->toArray(),
                'expected_close_on' => $item->expected_close_on?->toDateString(),
                'available_stages' => $salesCycle->availableTransitions($item),
            ]),
            'customers' => Person::where('category', 'customer')->orderBy('company_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name']),
            'installations' => Installation::orderBy('project_reference')->get(['id', 'person_id', 'project_reference', 'site_name']),
            'users' => User::whereIn('role', ['admin', 'commercial'])->orderBy('name')->get(['id', 'name']),
            'sourceOptions' => OpportunityCatalog::SOURCES,
            'lostReasonOptions' => OpportunityCatalog::LOST_REASONS,
            'filters' => [
                ...$request->only(['search', 'stage', 'owner', 'source', 'attention', 'archived']),
                'period' => $period,
            ],
            'metrics' => [
                'pipeline_value' => round((float) $open->sum('estimated_value'), 2),
                'weighted_forecast' => round((float) $open->sum(fn (Opportunity $item) => (float) $item->estimated_value * $item->probability / 100), 2),
                'won_value' => round((float) $won->sum('estimated_value'), 2),
                'new_leads' => $opportunities->filter(fn (Opportunity $item) => ! $periodStart || $item->created_at->greaterThanOrEqualTo($periodStart))->count(),
                'won_count' => $won->count(),
                'lost_count' => $lost->count(),
                'conversion_rate' => $closedCount > 0 ? round($won->count() / $closedCount * 100, 1) : 0,
                'average_cycle_days' => $averageCycleDays !== null ? round($averageCycleDays, 1) : null,
                'overdue' => $open->filter(fn (Opportunity $item) => $item->expected_close_on?->isPast())->count(),
                'without_next_action' => $open->whereNull('nextActivity')->count(),
                'stale' => $open->filter(fn (Opportunity $item) => $item->updated_at->lessThan(now()->subDays(config('crm.stale_after_days'))))->count(),
                'funnel' => collect(self::STAGES)->mapWithKeys(fn (string $stage) => [$stage => $opportunities->where('stage', $stage)->count()]),
                'stage_days' => $this->averageDaysByStage($opportunities),
            ],
            'savedFilters' => SavedCrmFilter::query()->where('user_id', $request->user()->id)->where('scope', 'opportunities')->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, OpportunitySalesCycle $salesCycle): RedirectResponse
    {
        $data = $salesCycle->prepareActiveData($this->validated($request), $request->user());
        $this->ensureInstallationBelongsToCustomer($data);

        DB::transaction(function () use ($data, $request, $salesCycle) {
            $opportunity = Opportunity::create([...$data, 'created_by' => $request->user()->id]);
            $opportunity->update(['code' => 'OPP-'.now()->format('Y').'-'.str_pad((string) $opportunity->id, 5, '0', STR_PAD_LEFT)]);
            $salesCycle->ensureNextAction($opportunity, $request->user());
            $salesCycle->refreshScore($opportunity);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Opportunity created.')]);
    }

    public function update(Request $request, Opportunity $opportunity, OpportunitySalesCycle $salesCycle): RedirectResponse
    {
        $data = $salesCycle->prepareActiveData($this->validated($request), $request->user());
        $this->ensureInstallationBelongsToCustomer($data);
        abort_if(
            $opportunity->quote()->exists()
                && ((int) $opportunity->person_id !== (int) $data['person_id']
                    || (int) $opportunity->installation_id !== (int) ($data['installation_id'] ?? 0)),
            422,
            __('The customer or installation cannot be changed after preparing a quote.'),
        );
        DB::transaction(function () use ($opportunity, $data, $salesCycle, $request): void {
            $salesCycle->assertCanTransition($opportunity, $data['stage']);
            $opportunity->update($data);
            $salesCycle->synchronizeStage($opportunity, $request->user());
            $salesCycle->refreshScore($opportunity);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Opportunity updated.')]);
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

    public function restore(int $opportunity): RedirectResponse
    {
        Opportunity::onlyTrashed()->findOrFail($opportunity)->restore();

        return to_route('opportunities.index')->with('toast', ['type' => 'success', 'message' => __('Opportunity restored.')]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'person_id' => ['required', Rule::exists('people', 'id')->where('category', 'customer')],
            'installation_id' => ['nullable', 'exists:installations,id'],
            'owner_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['admin', 'commercial']))],
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['required', Rule::in(self::STAGES)],
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

        return $data;
    }

    private function ensureInstallationBelongsToCustomer(array $data): void
    {
        if (empty($data['installation_id'])) {
            return;
        }

        $installation = Installation::findOrFail($data['installation_id']);
        abort_if((int) $installation->person_id !== (int) $data['person_id'], 422, __('The installation does not belong to this customer.'));
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->input('search'));

        $query
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customer) => $customer
                    ->where('company_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"))))
            ->when($request->filled('stage'), fn ($query) => $query->where('stage', (string) $request->input('stage')))
            ->when($request->filled('owner'), fn ($query) => $query->where(
                'owner_user_id',
                $request->input('owner') === 'me' ? $request->user()->id : $request->integer('owner'),
            ))
            ->when($request->filled('source'), fn ($query) => $query->where('source', (string) $request->input('source')))
            ->when($request->input('attention') === 'overdue', fn ($query) => $query
                ->whereNotIn('stage', ['won', 'lost'])
                ->whereDate('expected_close_on', '<', today()))
            ->when($request->input('attention') === 'no_action', fn ($query) => $query
                ->whereNotIn('stage', ['won', 'lost'])
                ->whereDoesntHave('activities', fn ($activities) => $activities
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereNotNull('due_at')));
    }

    private function closedInPeriod(Opportunity $opportunity, string $stage, ?CarbonInterface $periodStart): bool
    {
        $changedAt = $opportunity->stageHistory->firstWhere('to_stage', $stage)?->changed_at;

        return $changedAt && (! $periodStart || $changedAt->greaterThanOrEqualTo($periodStart));
    }

    private function averageDaysByStage($opportunities): array
    {
        $durations = array_fill_keys(self::STAGES, []);

        foreach ($opportunities as $opportunity) {
            $history = $opportunity->stageHistory->sortBy('changed_at')->values();
            foreach ($history as $index => $change) {
                $end = $history->get($index + 1)?->changed_at ?? now();
                $durations[$change->to_stage][] = $change->changed_at->diffInHours($end) / 24;
            }
        }

        return collect($durations)->map(fn (array $days) => count($days) ? round(collect($days)->average(), 1) : null)->all();
    }
}
