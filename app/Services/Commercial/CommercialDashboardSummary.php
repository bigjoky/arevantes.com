<?php

namespace App\Services\Commercial;

use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\OpportunityEvent;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteStatusHistory;
use App\Services\Analytics\AnalyticsFilter;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\CommercialConversionAnalytics;
use App\Services\Crm\OpportunitySalesCycle;
use App\Support\Crm\OpportunityCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Read-only composition of existing domains. No workflow or audit events are written on GET. */
class CommercialDashboardSummary
{
    public function __construct(private AnalyticsService $analytics, private CommercialConversionAnalytics $conversion) {}

    public function make(Request $request): array
    {
        $user = $request->user();
        $crm = Gate::forUser($user)->allows('manage-customers');
        $quotesAllowed = Gate::forUser($user)->allows('manage-quotes');
        $salesAllowed = Gate::forUser($user)->allows('manage-installations');
        abort_unless($crm || $quotesAllowed || $salesAllowed, 403);
        $period = $request->validate(['period' => ['nullable', Rule::in(['month', 'previous_month', 'quarter', 'year'])]])['period'] ?? 'month';
        $today = CarbonImmutable::today();
        [$from, $to] = match ($period) {
            'previous_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$today->startOfQuarter(), $today->endOfDay()],
            'year' => [$today->startOfYear(), $today->endOfDay()],
            default => [$today->startOfMonth(), $today->endOfDay()],
        };
        $filter = new AnalyticsFilter($from, $to, $period === 'previous_month' ? 'custom' : $period, null, null, 'sales', $user);
        $own = $filter->restrictsToOwnCommercialScope();
        $chartFrom = $to->subMonthsNoOverflow(11)->startOfMonth();
        $chartFilter = new AnalyticsFilter($chartFrom, $to, 'last_12_months', null, null, 'sales', $user);
        $quoteBase = fn () => Quote::query()->when($own, fn (Builder $query) => $query->where('created_by', $user->id));
        $opportunityBase = fn () => Opportunity::query()->when($own, fn (Builder $query) => $query->where('owner_user_id', $user->id));
        $quotePeriod = fn () => $quoteBase()->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()]);
        $pipeline = $crm ? $opportunityBase()->whereBetween('created_at', [$from, $to])
            ->selectRaw('stage, COUNT(*) count')->groupBy('stage')->pluck('count', 'stage') : collect();
        $conversion = $crm ? $this->conversion->forDashboard($filter) : null;
        $conversionTrend = $crm ? $this->conversion->forDashboard($chartFilter)['trend']->keyBy('period') : collect();
        [$previousFrom, $previousTo] = $filter->previousPeriod();
        $sales = $salesAllowed ? round($this->analytics->value('net_revenue', $filter, $from, $to), 2) : null;
        $target = ! $own && $salesAllowed && Gate::forUser($user)->allows('view-finance') ? $this->analytics->revenueTarget($filter) : null;
        $previousSales = $salesAllowed ? $this->analytics->value('net_revenue', $filter, $previousFrom, $previousTo) : null;
        $salesTrend = $salesAllowed ? $this->analytics->monthlyRevenue($chartFilter)->keyBy('period') : collect();
        $quoteMonth = $this->monthExpression('issue_date');
        $quoteTrend = $quotesAllowed ? $quoteBase()->whereBetween('issue_date', [$chartFrom->toDateString(), $to->toDateString()])
            ->selectRaw("{$quoteMonth} period, SUM(amount) value")->groupByRaw($quoteMonth)->get()->keyBy('period') : collect();
        $quoteSummary = $quotesAllowed ? $quotePeriod()->selectRaw('status, COUNT(*) count, SUM(amount) amount')->groupBy('status')->get()->keyBy('status') : collect();
        $pendingQuotes = $quotesAllowed ? round((float) $quotePeriod()->where('status', 'sent')
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today->toDateString()))->sum('amount'), 2) : null;
        $customers = null;
        if ($crm) {
            $base = Person::query()->where('category', 'customer')->when($own, fn ($query) => $query->whereHas('opportunities', fn ($opportunity) => $opportunity->where('owner_user_id', $user->id)));
            $customers = ['new' => (clone $base)->whereBetween('created_at', [$from, $to])->count(),
                'statuses' => (clone $base)->whereBetween('created_at', [$from, $to])->selectRaw('lifecycle_status, COUNT(*) count')->groupBy('lifecycle_status')->pluck('count', 'lifecycle_status')];
        }
        $activity = collect();
        if ($crm) {
            $activity = OpportunityEvent::query()->whereBetween('occurred_at', [$from, $to])
                ->whereHas('opportunity', fn ($query) => $query->when($own, fn ($query) => $query->where('owner_user_id', $user->id)))
                ->with(['opportunity:id,code,title', 'actor:id,name'])->latest('occurred_at')->latest('id')->limit(6)->get()->toBase()->map(fn ($event) => [
                    'id' => 'crm-'.$event->id, 'date' => $event->occurred_at->toIso8601String(), 'type' => 'CRM',
                    'description' => $event->summary, 'reference' => $event->opportunity?->code ?? $event->opportunity?->title,
                    'user' => $event->actor?->name, 'href' => '/opportunities/'.$event->opportunity_id,
                ]);
        }
        if ($quotesAllowed) {
            $activity = $activity->merge(QuoteStatusHistory::query()->whereBetween('changed_at', [$from, $to])
                ->whereHas('quote', fn ($query) => $query->when($own, fn ($query) => $query->where('created_by', $user->id)))
                ->with(['quote:id,number', 'changedBy:id,name'])->latest('changed_at')->latest('id')->limit(6)->get()->toBase()->map(fn ($event) => [
                    'id' => 'quote-'.$event->id, 'date' => $event->changed_at->toIso8601String(), 'type' => 'Presupuesto',
                    'description' => $event->to_status, 'reference' => $event->quote?->number,
                    'user' => $event->changedBy?->name, 'href' => '/quotes/'.$event->quote_id,
                ]));
        }
        $tasks = $crm ? CrmActivity::query()->whereIn('status', ['pending', 'in_progress'])
            ->whereBetween('due_at', [$from, $to])->whereNotNull('opportunity_id')
            ->whereHas('opportunity', fn ($query) => $query->when($own, fn ($query) => $query->where('owner_user_id', $user->id)))
            ->when($own, fn ($query) => $query->where(fn ($query) => $query->where('assigned_user_id', $user->id)->orWhereNull('assigned_user_id')))
            ->with('opportunity:id,code,title')->orderBy('due_at')->orderBy('id')->limit(6)->get()->map(fn ($task) => [
                'id' => $task->id, 'title' => $task->title, 'reference' => $task->opportunity?->code ?? $task->opportunity?->title,
                'due_at' => $task->due_at->toIso8601String(), 'overdue' => $task->due_at->lt(now()), 'href' => '/opportunities/'.$task->opportunity_id,
            ])->values() : null;
        $months = collect(range(0, 11))->map(function ($offset) use ($chartFrom, $salesTrend, $quoteTrend, $conversionTrend, $salesAllowed, $quotesAllowed, $crm) {
            $month = $chartFrom->addMonths($offset)->format('Y-m');
            $rate = $conversionTrend->get($month);

            return ['month' => $month, 'sales' => $salesAllowed ? round((float) ($salesTrend->get($month)?->value ?? 0), 2) : null,
                'quotes' => $quotesAllowed ? round((float) ($quoteTrend->get($month)?->value ?? 0), 2) : null,
                'conversion' => $crm && $rate ? $rate['win_rate'] : null];
        });

        return ['period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'today' => $today->toDateString(), 'scope' => $own ? 'own' : 'team',
            'permissions' => ['crm' => $crm, 'quotes' => $quotesAllowed, 'sales' => $salesAllowed],
            'kpis' => ['sales' => $sales, 'sales_change' => $previousSales && abs($previousSales) >= 0.01 ? round(($sales - $previousSales) / abs($previousSales) * 100, 1) : null,
                'pending_quotes' => $pendingQuotes, 'conversion' => $conversion && $conversion['summary']['closed'] > 0 ? $conversion['summary']['win_rate'] : null,
                'pipeline' => $crm ? (int) $pipeline->except(OpportunitySalesCycle::CLOSED_STAGES)->sum() : null],
            'target' => $target ? ['amount' => $target, 'achieved' => $sales] : null, 'monthly' => $months,
            'pipeline' => $crm ? collect(OpportunityCatalog::STAGES)->map(fn ($label, $stage) => ['stage' => $stage, 'label' => __($label), 'count' => (int) ($pipeline[$stage] ?? 0), 'closed' => in_array($stage, OpportunitySalesCycle::CLOSED_STAGES, true)])->values() : null,
            'quotes' => $quotesAllowed ? collect(['draft', 'sent', 'accepted', 'rejected', 'expired'])->map(fn ($status) => ['status' => $status, 'count' => (int) ($quoteSummary->get($status)?->count ?? 0), 'amount' => round((float) ($quoteSummary->get($status)?->amount ?? 0), 2)])->values() : null,
            'customers' => $customers, 'activity' => $activity->sortByDesc('date')->take(6)->values(), 'tasks' => $tasks,
        ];
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')", 'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m')", default => "strftime('%Y-%m', {$column})",
        };
    }
}
