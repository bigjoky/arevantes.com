<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\TaxRate;
use App\Models\TaxReturn;
use App\Services\Tax\CompanyCensus;
use App\Services\Tax\FiscalWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FiscalWorkspaceController extends Controller
{
    public function index(Request $request, FiscalWorkspace $workspace, CompanyCensus $census): Response
    {
        abort_if(CompanyProfile::count() > 1, 409, __('Fiscal company separation is not configured.'));
        $filters = $request->validate(['year' => 'nullable|integer|between:2020,2100', 'quarter' => 'nullable|integer|between:1,4', 'tab' => 'nullable|in:vat,other,withholding,regimes,main,annual,informative,filings,years,retention,closing', 'search' => 'nullable|string|max:100', 'state' => 'nullable|in:active,inactive,draft,approved,prepared,filed', 'model' => 'nullable|in:303,200', 'page' => 'nullable|integer|min:1', 'documents_page' => 'nullable|integer|min:1', 'rates_page' => 'nullable|integer|min:1']);
        $year = $request->integer('year', today()->year);
        $quarter = $request->integer('quarter') ?: null;
        $area = match ($request->route()->getName()) {
            'fiscal.taxes' => 'taxes', 'fiscal.models' => 'models', 'fiscal.periods' => 'periods', default => 'dashboard'
        };
        $fy = FiscalYear::where('year', $year)->first();
        $from = $quarter ? CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1)->toDateString() : ($fy?->starts_on?->toDateString() ?? "$year-01-01");
        $to = $quarter ? CarbonImmutable::parse($from)->endOfQuarter()->toDateString() : ($fy?->ends_on?->toDateString() ?? "$year-12-31");
        $company = CompanyProfile::first();
        $check = $company ? $census->check($company, $year) : ['current' => false, 'supported' => false, 'settings' => null, 'version_id' => null, 'status' => null, 'author' => null, 'source_reference' => null];
        $documents = $workspace->documents($from, $to);
        $totals = $workspace->totals($documents);
        $rates = TaxRate::query()->select(['id', 'name', 'rate', 'kind', 'is_active', 'is_default']);
        if ($request->filled('search')) {
            $rates->where('name', 'like', '%'.$filters['search'].'%');
        }
        if (in_array($filters['state'] ?? '', ['active', 'inactive'], true)) {
            $rates->where('is_active', $filters['state'] === 'active');
        }
        $tab = $filters['tab'] ?? 'vat';
        if ($tab !== 'other') {
            $rates->whereIn('kind', $tab === 'withholding' ? ['withholding'] : ['vat', 'exempt']);
        }
        if ($tab === 'other') {
            $rates->whereNotIn('kind', ['vat', 'withholding', 'exempt']);
        }
        $returns = $workspace->declarations($year);
        if ($request->filled('model')) {
            $returns->where('model', $filters['model']);
        }
        if (in_array($filters['state'] ?? '', ['draft', 'approved', 'prepared', 'filed'], true)) {
            $returns->where('status', $filters['state']);
        }
        if ($request->filled('search')) {
            $returns->where(fn ($q) => $q->where('model', 'like', '%'.$filters['search'].'%')->orWhere('filing_reference', 'like', '%'.$filters['search'].'%'));
        }
        if ($quarter) {
            $returns->where('quarter', $quarter);
        }
        if ($tab === 'annual') {
            $returns->where('model', '200');
        } elseif ($tab === 'informative') {
            $returns->whereRaw('1=0');
        }
        $declarationStates = (clone $returns)->selectRaw('status, count(*) as total')->groupBy('status')->get()->map(fn ($r) => ['name' => $r->status, 'value' => (int) $r->total]);
        $declarations = $returns->orderByDesc('period_to')->orderByDesc('id')->paginate(10)->withQueryString();
        $declarations->setCollection(collect($workspace->declarationRows($declarations->items())));
        $sourceRows = clone $documents;
        if ($request->filled('search')) {
            $sourceRows->where('number', 'like', '%'.$filters['search'].'%');
        }
        $sources = $sourceRows->orderByDesc('date')->orderBy('source')->orderByDesc('id')->paginate(10, ['*'], 'documents_page')->withQueryString()->through(fn ($r) => ['id' => $r->id, 'source' => $r->source, 'date' => $r->date, 'number' => $r->number, 'direction' => $r->direction, 'base' => FiscalWorkspace::decimal($r->base), 'vat' => FiscalWorkspace::decimal($r->vat), 'withholding' => FiscalWorkspace::decimal($r->withholding), 'href' => '/'.$r->source.'/'.$r->id]);
        $periods = [];
        if ($fy && $fy->starts_on->toDateString() === "$year-01-01" && $fy->ends_on->toDateString() === "$year-12-31" && $check['current'] && ($check['settings']['model303'] ?? null) === 'yes' && ($check['settings']['periodicity'] ?? null) === 'quarterly') {
            $saved = TaxReturn::where('year', $year)->where('tax_type', 'vat')->get(['quarter', 'status'])->keyBy('quarter');
            for ($q = 1; $q <= 4; $q++) {
                $start = CarbonImmutable::create($year, ($q - 1) * 3 + 1, 1);
                $periods[] = ['name' => $q.'T', 'from' => $start->toDateString(), 'to' => $start->endOfQuarter()->toDateString(), 'status' => $saved->get($q)?->status ?? 'not_started', 'deadline' => null, 'href' => '/tax?year='.$year.'&quarter='.$q];
            }
        }

        return Inertia::render('fiscal/workspace', ['area' => $area, 'filters' => [...$filters, 'year' => $year], 'period' => ['from' => $from, 'to' => $to, 'quarter' => $quarter], 'company' => $company?->only(['legal_name', 'tax_id']), 'census' => $check, 'metrics' => $totals, 'series' => $workspace->series($documents), 'rates' => $rates->orderByDesc('is_active')->orderByDesc('rate')->paginate(10, ['*'], 'rates_page')->withQueryString(), 'declarations' => $declarations, 'declarationStates' => $declarationStates, 'documents' => $sources, 'years' => FiscalYear::orderByDesc('year')->get(['id', 'year', 'starts_on', 'ends_on', 'status', 'closed_at', 'reopened_at'])->map(fn ($y) => ['id' => $y->id, 'year' => $y->year, 'starts_on' => $y->starts_on->toDateString(), 'ends_on' => $y->ends_on->toDateString(), 'status' => $y->status, 'closed_at' => $y->closed_at?->toIso8601String(), 'reopened_at' => $y->reopened_at?->toIso8601String()]), 'periods' => $periods, 'modelCatalog' => [['code' => '303', 'name' => 'IVA · Autoliquidación', 'applicable' => $check['current'] ? match ($check['settings']['model303'] ?? 'unknown') {
            'yes' => true, 'no' => false, default => null
        } : null, 'supported' => $check['supported'], 'href' => Gate::allows('manage-tax') ? '/tax/models/303?year='.$year.'&quarter='.($quarter ?? min(4, (int) ceil(today()->month / 3))) : null], ['code' => '200', 'name' => 'Impuesto sobre Sociedades', 'applicable' => null, 'supported' => false, 'href' => '/corporate-tax?year='.$year]], 'excludedForeignExpenses' => Expense::whereBetween('expense_date', [$from, $to])->where('currency_code', '<>', 'EUR')->count(), 'calculationVersion' => FiscalWorkspace::VERSION, 'capabilities' => ['manageTax' => Gate::allows('manage-tax'), 'manageCensus' => $request->user()->hasRole('admin') && Gate::allows('manage-tax'), 'manageYears' => Gate::allows('manage-admin'), 'multiCompany' => false, 'deadlines' => false, 'independentFiscalClose' => false, 'deductibilitySummary' => false, 'taxValidity' => false, 'createWithholdingRate' => false, 'specialTreatments' => false], 'officialCalendar' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/calendario-contribuyente/calendario-contribuyente-'.$year.'.html']);
    }

    /** Append a catalog entry. Never rewrite percentages on historical rates. */
    public function storeRate(Request $request): RedirectResponse
    {
        abort_if(CompanyProfile::count() > 1, 409, __('Fiscal company separation is not configured.'));
        $data = $request->validate(['name' => 'required|string|max:100', 'rate' => 'required|numeric|between:0,100|decimal:0,2', 'kind' => 'required|in:vat']);
        DB::transaction(fn () => TaxRate::create([...$data, 'rate' => FiscalWorkspace::decimal($data['rate']), 'is_active' => true, 'is_default' => false]));

        return back()->with('toast', ['type' => 'success', 'message' => __('Tax rate created.')]);
    }
}
