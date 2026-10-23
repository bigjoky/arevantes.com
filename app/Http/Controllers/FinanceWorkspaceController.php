<?php

namespace App\Http\Controllers;

use App\Models\FinancialCategory;
use App\Models\FinancialForecastItem;
use App\Services\Billing\BillingWorkspace;
use App\Services\Finance\FinanceWorkspace;
use App\Services\Finance\TreasuryForecast;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FinanceWorkspaceController extends Controller
{
    public function index(Request $request, FinanceWorkspace $workspace, TreasuryForecast $forecast): Response
    {
        abort_unless(Gate::allows('view-finance'), 403);
        $filters = $request->validate(['area' => 'nullable|in:treasury,forecasts', 'tab' => 'nullable|in:movements,position,categories,analysis,overview,detail,scenarios', 'month' => 'nullable|date_format:Y-m', 'account' => 'nullable|integer|exists:bank_accounts,id', 'months' => 'nullable|integer|in:3,6,12', 'horizon' => 'nullable|integer|in:7,30,60,90', 'scenario' => 'nullable|in:probable,conservative,optimistic', 'search' => 'nullable|string|max:100', 'type' => 'nullable|in:in,out', 'source' => 'nullable|in:bank,unmatched', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'sort' => 'nullable|in:date,amount,description,account', 'direction' => 'nullable|in:asc,desc', 'page' => 'nullable|integer|min:1', 'due_page' => 'nullable|integer|min:1', 'manual_page' => 'nullable|integer|min:1']);
        $month = CarbonImmutable::parse(($filters['month'] ?? today()->format('Y-m')).'-01');
        $account = $request->integer('account') ?: null;
        $accounts = $workspace->accounts($account);
        $opening = collect($accounts)->where('is_active', true)->where('currency', 'EUR')->reduce(fn ($sum, $a) => $sum->plus($a['balance']), BigDecimal::zero());
        $obligations = $workspace->obligations();
        $cash = $workspace->bankMovements($account, true)->whereDate('t.booked_on', '>=', $month->toDateString())->whereDate('t.booked_on', '<=', $month->endOfMonth()->min(today())->toDateString());
        $totals = $workspace->totals($cash);
        $cashCategories = DB::query()->fromSub(clone $cash, 'categorized_cash')->selectRaw('category, count(*) as movements, sum(case when amount>0 then amount else 0 end) as incoming, sum(case when amount<0 then -amount else 0 end) as outgoing')->groupBy('category')->get()->map(fn ($row) => ['name' => $row->category, 'movements' => $row->movements, 'in' => BillingWorkspace::decimal($row->incoming), 'out' => BillingWorkspace::decimal($row->outgoing)]);

        $future = $forecast->build((int) today()->year, $filters['scenario'] ?? 'probable', exact: true);
        $horizon = $request->integer('horizon', 90);
        $horizonDays = $future['days']->take($horizon + 1);
        $incoming = $horizonDays->reduce(fn ($s, $d) => $s->plus($d['in']), BigDecimal::zero());
        $outgoing = $horizonDays->reduce(fn ($s, $d) => $s->plus($d['out']), BigDecimal::zero());
        $forecastAvailable = ! $account && collect($accounts)->where('is_active', true)->where('currency', 'EUR')->isNotEmpty();
        $metrics = ['registered' => BillingWorkspace::decimal($opening), 'available' => null, 'receivable' => BillingWorkspace::decimal((clone $obligations)->where('kind', 'customer')->sum('balance')), 'payable' => BillingWorkspace::decimal((clone $obligations)->where('kind', '<>', 'customer')->sum('balance')), 'flow' => $totals['net'], 'forecast30' => $forecastAvailable ? $future['days']->get(30)['closing'] : null, 'due7' => BillingWorkspace::decimal((clone $obligations)->whereBetween('due_date', [today()->toDateString(), today()->addDays(7)->toDateString()])->sum('balance'))];
        $movementsQuery = ($filters['source'] ?? 'bank') === 'unmatched' ? $workspace->unmatchedPayments() : DB::query()->fromSub($workspace->bankMovements($account), 'bank_rows');
        $movementsQuery->whereDate('date', '>=', $month->toDateString())->whereDate('date', '<=', $month->endOfMonth()->toDateString());
        if (($filters['source'] ?? 'bank') === 'unmatched' && $account) {
            $movementsQuery->whereRaw('1=0');
        }
        if ($request->filled('search')) {
            $term = '%'.$filters['search'].'%';
            $movementsQuery->where(fn ($q) => $q->where('description', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('account', 'like', $term));
        }
        if ($request->filled('from')) {
            $movementsQuery->whereDate('date', '>=', $filters['from']);
        }
        if ($request->filled('to')) {
            $movementsQuery->whereDate('date', '<=', $filters['to']);
        }
        if ($request->filled('type')) {
            $movementsQuery->where('amount', $filters['type'] === 'in' ? '>' : '<', 0);
        }
        $movements = $movementsQuery->orderBy($filters['sort'] ?? 'date', $filters['direction'] ?? 'desc')->orderByDesc('id')->paginate(10)->withQueryString()->through(fn ($r) => $workspace->movement($r));
        $due = (clone $obligations)->whereNotNull('due_date')->where('due_date', '<=', today()->addDays($horizon)->toDateString())->orderBy('due_date')->orderBy('kind')->orderBy('id')->paginate(10, ['*'], 'due_page')->withQueryString()->through(fn ($r) => app(BillingWorkspace::class)->invoice($r));
        $upcoming = fn ($side) => (clone $obligations)->where('kind', $side === 'in' ? '=' : '<>', 'customer')->whereNotNull('due_date')->orderBy('due_date')->limit(5)->get()->map(fn ($r) => app(BillingWorkspace::class)->invoice($r));
        $props = ['operationKey' => (string) Str::uuid(), 'filters' => $filters, 'metrics' => $metrics, 'accounts' => $accounts, 'allAccounts' => $workspace->accounts(), 'totals' => $totals, 'movements' => $movements, 'series' => $workspace->series($request->integer('months', 12), $account), 'receipts' => $upcoming('in'), 'payments' => $upcoming('out'), 'due' => $due, 'forecast' => [...$future, 'available' => $forecastAvailable, 'closing30' => $forecastAvailable ? $future['days']->get(30)['closing'] : null, 'closing90' => $forecastAvailable ? $future['days']->get(90)['closing'] : null, 'horizon' => $horizon, 'incoming' => BillingWorkspace::decimal($incoming), 'outgoing' => BillingWorkspace::decimal($outgoing), 'closing' => $forecastAvailable ? $horizonDays->last()['closing'] : null], 'manualForecasts' => FinancialForecastItem::with('financialCategory:id,name')->latest('due_on')->paginate(10, ['*'], 'manual_page')->withQueryString(), 'cashCategories' => $cashCategories, 'forecastPeriods' => $horizonDays->groupBy(fn ($d) => substr($d['date'], 0, 7))->map(fn ($days, $period) => ['period' => $period, 'in' => BillingWorkspace::decimal($days->reduce(fn ($sum, $d) => $sum->plus($d['in']), BigDecimal::zero())), 'out' => BillingWorkspace::decimal($days->reduce(fn ($sum, $d) => $sum->plus($d['out']), BigDecimal::zero())), 'closing' => $forecastAvailable ? $days->last()['closing'] : null])->values(), 'categories' => FinancialCategory::where('is_active', true)->whereIn('reporting_group', ['cash_in', 'cash_out'])->get(['id', 'name', 'code', 'reporting_group']), 'capabilities' => ['manage' => Gate::allows('manage-treasury'), 'multiCompany' => false, 'availableBalance' => false, 'historicalBalances' => false, 'manualMovement' => false]];

        return Inertia::render('finance/workspace', $props);
    }
}
