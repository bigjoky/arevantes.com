<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CollectionAction;
use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\FinancialAlertAction;
use App\Models\FinancialBudget;
use App\Models\FinancialBudgetPeriod;
use App\Models\FinancialBudgetVersion;
use App\Models\FinancialForecastItem;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Finance\BudgetCsvImporter;
use App\Services\Finance\FinancialAlertService;
use App\Services\Finance\OutstandingBalanceCalculator;
use App\Services\Finance\ProjectProfitability;
use App\Services\Finance\TreasuryForecast;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(
        private OutstandingBalanceCalculator $balances,
        private FinancialAlertService $financialAlerts,
    ) {}

    public function index(Request $request, TreasuryForecast $forecast, ProjectProfitability $profitability): Response
    {
        $year = $this->year($request);
        $scenario = $request->validate(['scenario' => ['nullable', Rule::in(['conservative', 'probable', 'optimistic'])]])['scenario'] ?? 'probable';
        $customerPayments = SalesDocumentPayment::with('document:id,number,person_id,installation_id')->whereYear('paid_at', $year)->get();
        $supplierPayments = SupplierPayment::with(['expense:id,number', 'purchaseDocument:id,number'])->whereYear('paid_at', $year)->get();
        $sales = SalesDocument::whereIn('type', ['invoice', 'credit_note'])->whereNotNull('locked_at')->whereYear('issue_date', $year)->get();
        $purchases = PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNot('status', 'review_required')->whereYear('issue_date', $year)->get();
        $expenses = Expense::whereYear('expense_date', $year)->get();
        $budgets = FinancialBudget::where('year', $year)->orderBy('month')->orderBy('category')->get();
        $forecastItems = FinancialForecastItem::with(['installation:id,project_reference,site_name', 'settler:id,name'])
            ->where(function ($query) use ($year) {
                $query->whereYear('due_on', $year)->orWhereYear('settled_on', $year);
            })
            ->latest('due_on')
            ->limit(100)
            ->get();
        $budgetPeriod = FinancialBudgetPeriod::with(['approver:id,name', 'versions.approver:id,name'])->where('year', $year)->first();
        $receivables = SalesDocument::with(['customer:id,first_name,last_name,company_name', 'collectionAction.assignee:id,name', 'collectionAction.events.creator:id,name'])->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'overdue'])->get();
        $payableInvoices = PurchaseDocument::with('supplier:id,legal_name,trade_name')->withSum('creditNotes as credited_total', 'amount')->where('type', 'supplier_invoice')->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])->get();
        $payableExpenses = Expense::with('supplier:id,legal_name,trade_name')->whereIn('status', ['pending', 'partial', 'overdue'])->get();
        $activeBatchItems = $this->activePaymentBatchItems();
        $payables = $this->payables($payableInvoices, $payableExpenses, $activeBatchItems);
        $revenue = (float) $sales->sum(fn ($document) => (float) $document->subtotal - (float) $document->discount_amount);
        $costs = (float) $purchases->sum(fn ($document) => (float) $document->subtotal + (float) $document->shipping_cost)
            + (float) $expenses->sum('subtotal');
        $operatingResult = $revenue - $costs;
        $cashFlow = collect(range(1, 12))->map(function ($month) use ($year, $customerPayments, $supplierPayments, $receivables, $payableInvoices, $payableExpenses, $budgets) {
            $in = (float) $customerPayments->filter(fn ($payment) => $payment->paid_at->month === $month)->sum('amount');
            $out = (float) $supplierPayments->filter(fn ($payment) => $payment->paid_at->month === $month)->sum('amount');
            $budgetIn = (float) $budgets->where('month', $month)->where('category', 'cash_in')->sum('amount');
            $budgetOut = (float) $budgets->where('month', $month)->where('category', 'cash_out')->sum('amount');
            $forecastIn = (float) $receivables
                ->filter(fn ($document) => $this->forecastMonth($document->due_date, $year) === $month)
                ->sum(fn ($document) => $this->balances->receivable($document));
            $forecastOut = (float) $payableInvoices
                ->filter(fn ($document) => $this->forecastMonth($document->due_date, $year) === $month)
                ->sum(fn ($document) => $this->balances->purchase($document))
                + (float) $payableExpenses
                    ->filter(fn ($expense) => $this->forecastMonth($expense->due_date, $year) === $month)
                    ->sum(fn ($expense) => $this->balances->expense($expense));

            return [
                'month' => now()->month($month)->translatedFormat('M'),
                'in' => round($in, 2),
                'out' => round($out, 2),
                'net' => round($in - $out, 2),
                'forecastIn' => round($forecastIn, 2),
                'forecastOut' => round($forecastOut, 2),
                'budgetIn' => round($budgetIn, 2),
                'budgetOut' => round($budgetOut, 2),
                'budgetNet' => round($budgetIn - $budgetOut, 2),
            ];
        });
        $performance = collect(range(1, 12))->map(function ($month) use ($sales, $purchases, $expenses, $budgets) {
            $revenue = (float) $sales->filter(fn ($document) => $document->issue_date->month === $month)->sum(fn ($document) => (float) $document->subtotal - (float) $document->discount_amount);
            $costs = (float) $purchases->filter(fn ($document) => $document->issue_date->month === $month)->sum(fn ($document) => (float) $document->subtotal + (float) $document->shipping_cost)
                + (float) $expenses->filter(fn ($expense) => $expense->expense_date->month === $month)->sum('subtotal');
            $budgetRevenue = (float) $budgets->where('month', $month)->where('category', 'revenue')->sum('amount');
            $budgetCosts = (float) $budgets->where('month', $month)->where('category', 'costs')->sum('amount');

            return [
                'month' => now()->month($month)->translatedFormat('M'),
                'revenue' => round($revenue, 2),
                'costs' => round($costs, 2),
                'result' => round($revenue - $costs, 2),
                'budgetRevenue' => round($budgetRevenue, 2),
                'budgetCosts' => round($budgetCosts, 2),
                'budgetResult' => round($budgetRevenue - $budgetCosts, 2),
            ];
        });
        $company = CompanyProfile::firstOrFail();
        $treasuryForecast = $forecast->build($year, $scenario);
        $budgetAlerts = $this->financialAlerts->evaluate($year, $performance, $cashFlow, $treasuryForecast, $company);
        $receivableRows = $receivables->map(function ($item) {
            $promiseBreached = $item->collectionAction?->status === 'promised'
                && $item->collectionAction->promised_on?->isBefore(today());

            return [
                'id' => $item->id,
                'number' => $item->number,
                'party' => $item->customer?->company_name ?: trim(($item->customer?->first_name ?? '').' '.($item->customer?->last_name ?? '')),
                'due_date' => $item->due_date?->toDateString(),
                'days_overdue' => $this->daysOverdue($item->due_date),
                'status' => $item->status,
                'outstanding' => $this->balances->receivable($item),
                'promise_breached' => $promiseBreached,
                'collection_action' => $item->collectionAction ? [
                    'status' => $item->collectionAction->status,
                    'resolution_reason' => $item->collectionAction->resolution_reason,
                    'assigned_to' => $item->collectionAction->assigned_to,
                    'assignee' => $item->collectionAction->assignee?->name,
                    'contacted_on' => $item->collectionAction->contacted_on?->toDateString(),
                    'promised_on' => $item->collectionAction->promised_on?->toDateString(),
                    'promised_amount' => $item->collectionAction->promised_amount ? (float) $item->collectionAction->promised_amount : null,
                    'next_action_on' => $item->collectionAction->next_action_on?->toDateString(),
                    'resolved_at' => $item->collectionAction->resolved_at?->toIso8601String(),
                    'notes' => $item->collectionAction->notes,
                    'events' => $item->collectionAction->events->take(8)->map(fn ($event) => [
                        'id' => $event->id,
                        'event_type' => $event->event_type,
                        'status_from' => $event->status_from,
                        'status_to' => $event->status_to,
                        'promised_on' => $event->promised_on?->toDateString(),
                        'promised_amount' => $event->promised_amount ? (float) $event->promised_amount : null,
                        'next_action_on' => $event->next_action_on?->toDateString(),
                        'notes' => $event->notes,
                        'occurred_at' => $event->occurred_at->toIso8601String(),
                        'creator' => $event->creator?->name,
                    ])->values(),
                ] : null,
            ];
        })->sortByDesc('days_overdue')->values();
        $receivableTotal = (float) $receivableRows->sum('outstanding');
        $payableTotal = (float) $payables->sum('outstanding');
        $overdueReceivable = (float) $receivableRows->where('days_overdue', '>', 0)->sum('outstanding');
        $overduePayable = (float) $payables->where('days_overdue', '>', 0)->sum('outstanding');
        $bankPosition = BankAccount::where('is_active', true)
            ->withSum('transactions as transaction_total', 'amount')
            ->withSum(['transactions as pending_amount' => fn ($query) => $query->where('status', 'pending')], 'amount')
            ->withCount(['transactions as pending_transactions' => fn ($query) => $query->where('status', 'pending')])
            ->withMax('transactions as last_transaction_on', 'booked_on')
            ->orderBy('name')
            ->get()
            ->map(function (BankAccount $account): array {
                $latestImport = $account->statementImports()->whereNull('rolled_back_at')->latest()->first();

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'bankName' => $account->bank_name,
                    'currency' => $account->currency,
                    'balance' => round((float) $account->opening_balance + (float) ($account->transaction_total ?? 0), 2),
                    'pendingTransactions' => (int) $account->pending_transactions,
                    'pendingAmount' => round((float) ($account->pending_amount ?? 0), 2),
                    'lastTransactionOn' => $account->last_transaction_on ? substr((string) $account->last_transaction_on, 0, 10) : null,
                    'lastImportAt' => $latestImport?->created_at?->toIso8601String(),
                    'lastStatementTo' => $latestImport?->period_to?->toDateString(),
                    'statementControl' => $latestImport?->control_valid === null ? 'unavailable' : ($latestImport->control_valid ? 'valid' : 'mismatch'),
                ];
            });

        return Inertia::render('finance/index', [
            'year' => $year,
            'summary' => [
                'cashIn' => round((float) $customerPayments->sum('amount'), 2),
                'cashOut' => round((float) $supplierPayments->sum('amount'), 2),
                'netCash' => round((float) $customerPayments->sum('amount') - (float) $supplierPayments->sum('amount'), 2),
                'receivable' => round($receivableTotal, 2),
                'payable' => round($payableTotal, 2),
                'overdueReceivable' => round($overdueReceivable, 2),
                'overduePayable' => round($overduePayable, 2),
                'revenue' => round($revenue, 2),
                'costs' => round($costs, 2),
                'operatingResult' => round($operatingResult, 2),
                'operatingMargin' => $revenue !== 0.0 ? round($operatingResult / $revenue * 100, 1) : null,
            ],
            'cashFlow' => $cashFlow,
            'performance' => $performance,
            'vat' => ['output' => round((float) $sales->sum('tax_amount'), 2), 'input' => round((float) $purchases->sum('tax_amount') + (float) $expenses->sum('tax_amount'), 2)],
            'aging' => [
                'receivable' => $this->aging($receivableRows),
                'payable' => $this->aging($payables),
            ],
            'receivables' => $receivableRows,
            'payables' => $payables,
            'profitability' => $profitability->forYear($year),
            'treasuryForecast' => $treasuryForecast,
            'forecastItems' => $forecastItems,
            'forecastAccuracy' => $this->forecastAccuracy($forecastItems
                ->where('status', 'settled')
                ->filter(fn (FinancialForecastItem $item) => $item->settled_on?->year === $year)
                ->values()),
            'budgets' => $budgets,
            'budgetPeriod' => [
                'status' => $budgetPeriod?->status ?? 'draft',
                'currentRevision' => $budgetPeriod?->current_revision ?? 0,
                'approvedBy' => $budgetPeriod?->approver?->name,
                'approvedAt' => $budgetPeriod?->approved_at?->toIso8601String(),
                'reopenedAt' => $budgetPeriod?->reopened_at?->toIso8601String(),
                'reopenReason' => $budgetPeriod?->reopen_reason,
                'configuredValues' => $budgets->count(),
                'requiredValues' => 48,
                'versions' => $budgetPeriod?->versions->map(function (FinancialBudgetVersion $version) {
                    $values = collect($version->values);
                    $total = fn (string $category) => (float) $values
                        ->where('category', $category)
                        ->sum(fn (array $value) => (float) $value['amount']);

                    return [
                        'id' => $version->id,
                        'revision' => $version->revision,
                        'approvedBy' => $version->approver?->name,
                        'approvedAt' => $version->approved_at->toIso8601String(),
                        'approvalNotes' => $version->approval_notes,
                        'operatingResult' => round($total('revenue') - $total('costs'), 2),
                        'cashNet' => round($total('cash_in') - $total('cash_out'), 2),
                    ];
                })->values() ?? [],
            ],
            'budgetSummary' => $this->budgetSummary($year, $revenue, $costs, (float) $customerPayments->sum('amount'), (float) $supplierPayments->sum('amount')),
            'budgetAlerts' => $budgetAlerts,
            'alertSettings' => [
                'operatingVariancePercent' => (float) $company->operating_variance_alert_percent,
                'cashVariancePercent' => (float) $company->cash_variance_alert_percent,
                'liquidityBufferAmount' => (float) $company->liquidity_buffer_amount,
            ],
            'comparison' => $this->comparison($year, $revenue, $costs),
            'kpis' => $this->kpis($year, $revenue, $costs, $receivableTotal, $payableTotal, $overdueReceivable, $overduePayable, $receivableRows->where('promise_breached', true)->count()),
            'paymentBatches' => $this->paymentBatchRows(),
            'bankPosition' => $bankPosition,
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'iban']),
            'financeUsers' => User::whereIn('role', ['admin', 'commercial'])->orderBy('name')->get(['id', 'name']),
            'can' => [
                'manageTreasury' => Gate::allows('manage-treasury'),
                'manageAccounting' => Gate::allows('manage-accounting'),
                'approvePayments' => Gate::allows('approve-payments'),
                'approveBudgets' => Gate::allows('approve-budgets'),
            ],
        ]);
    }

    public function storeForecast(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'category' => ['required', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_on' => ['required', 'date'],
            'probability' => ['required', 'integer', 'between:0,100'],
            'recurrence' => ['nullable', Rule::in(['monthly', 'quarterly', 'annual'])],
            'installation_id' => ['nullable', 'exists:installations,id'],
        ]);
        FinancialForecastItem::create([
            ...$data,
            'baseline_amount' => $data['amount'],
            'baseline_due_on' => $data['due_on'],
            'status' => 'planned',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Forecast item added.')]);
    }

    public function updateForecast(Request $request, FinancialForecastItem $forecastItem): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['planned', 'settled', 'cancelled'])],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'due_on' => ['nullable', 'date'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'actual_amount' => ['required_if:status,settled', 'nullable', 'numeric', 'gt:0'],
            'settled_on' => ['required_if:status,settled', 'nullable', 'date', 'before_or_equal:today'],
            'settlement_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($data['status'] === 'settled') {
            $data['settled_by'] = $request->user()->id;
        } else {
            $data['actual_amount'] = null;
            $data['settled_on'] = null;
            $data['settlement_notes'] = null;
            $data['settled_by'] = null;
        }
        $forecastItem->update(array_filter($data, fn ($value, string $key) => $value !== null || in_array($key, ['actual_amount', 'settled_on', 'settlement_notes', 'settled_by'], true), ARRAY_FILTER_USE_BOTH));

        return back()->with('toast', ['type' => 'success', 'message' => __('Forecast item updated.')]);
    }

    public function destroyForecast(FinancialForecastItem $forecastItem): RedirectResponse
    {
        if ($forecastItem->status === 'settled') {
            throw ValidationException::withMessages([
                'forecast_item' => __('Settled forecast items are retained for accuracy reporting.'),
            ]);
        }
        $forecastItem->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Forecast item removed.')]);
    }

    public function storeBudget(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2020,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'category' => ['required', Rule::in(['revenue', 'costs', 'cash_in', 'cash_out'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->assertBudgetEditable((int) $data['year']);
        FinancialBudget::updateOrCreate(
            collect($data)->only(['year', 'month', 'category'])->all(),
            [...collect($data)->only(['amount', 'notes'])->all(), 'created_by' => $request->user()->id],
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('Budget updated.')]);
    }

    public function copyBudget(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_year' => ['required', 'integer', 'between:2020,2100', 'different:target_year'],
            'target_year' => ['required', 'integer', 'between:2020,2100'],
            'adjustment_percent' => ['required', 'numeric', 'between:-100,1000'],
            'overwrite' => ['nullable', 'boolean'],
        ]);
        $source = FinancialBudget::where('year', $data['source_year'])->get();
        if ($source->isEmpty()) {
            throw ValidationException::withMessages(['source_year' => __('The source year has no budget values.')]);
        }
        $this->assertBudgetEditable((int) $data['target_year']);

        $copied = DB::transaction(function () use ($request, $data, $source) {
            $copied = 0;
            foreach ($source as $budget) {
                $target = FinancialBudget::firstOrNew([
                    'year' => $data['target_year'],
                    'month' => $budget->month,
                    'category' => $budget->category,
                ]);
                if ($target->exists && ! ($data['overwrite'] ?? false)) {
                    continue;
                }
                $target->fill([
                    'amount' => round((float) $budget->amount * (1 + (float) $data['adjustment_percent'] / 100), 2),
                    'notes' => __('Copied from :year with :adjustment% adjustment.', ['year' => $data['source_year'], 'adjustment' => $data['adjustment_percent']]),
                    'created_by' => $request->user()->id,
                ])->save();
                $copied++;
            }

            return $copied;
        });

        return back()->with('toast', ['type' => 'success', 'message' => __(':count budget values copied.', ['count' => $copied])]);
    }

    public function importBudgets(Request $request, BudgetCsvImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2020,2100'],
            'budget_file' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/octet-stream', 'max:2048'],
            'overwrite' => ['nullable', 'boolean'],
        ]);
        $this->assertBudgetEditable((int) $data['year']);
        $result = $importer->import(
            $request->file('budget_file')->getRealPath(),
            (int) $data['year'],
            (bool) ($data['overwrite'] ?? false),
            $request->user()->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => __(':imported budget values imported; :skipped existing values skipped.', $result),
        ]);
    }

    public function destroyBudget(FinancialBudget $financialBudget): RedirectResponse
    {
        $this->assertBudgetEditable($financialBudget->year);
        $financialBudget->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Budget value removed.')]);
    }

    public function approveBudget(Request $request, int $year): RedirectResponse
    {
        abort_unless($year >= 2020 && $year <= 2100, 404);
        $data = $request->validate([
            'confirmation' => ['required', 'string', Rule::in([(string) $year])],
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($request, $year, $data) {
            FinancialBudgetPeriod::firstOrCreate(
                ['year' => $year],
                ['status' => 'draft', 'current_revision' => 0],
            );
            $period = FinancialBudgetPeriod::query()->where('year', $year)->lockForUpdate()->firstOrFail();
            abort_unless($period->status === 'draft', 422);
            $budgets = FinancialBudget::query()
                ->where('year', $year)
                ->orderBy('month')
                ->orderBy('category')
                ->lockForUpdate()
                ->get();
            if ($budgets->count() !== 48) {
                throw ValidationException::withMessages([
                    'budget' => __('Complete all 48 monthly budget values before approval.'),
                ]);
            }

            $approvedAt = now();
            $revision = $period->current_revision + 1;
            $period->versions()->create([
                'revision' => $revision,
                'approved_by' => $request->user()->id,
                'approved_at' => $approvedAt,
                'approval_notes' => $data['approval_notes'] ?? null,
                'values' => $budgets->map(fn (FinancialBudget $budget) => [
                    'month' => $budget->month,
                    'category' => $budget->category,
                    'amount' => $budget->amount,
                    'notes' => $budget->notes,
                ])->all(),
            ]);
            $period->update([
                'status' => 'approved',
                'current_revision' => $revision,
                'approved_by' => $request->user()->id,
                'approved_at' => $approvedAt,
                'approval_notes' => $data['approval_notes'] ?? null,
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Annual budget revision approved and locked.')]);
    }

    public function reopenBudget(Request $request, int $year): RedirectResponse
    {
        abort_unless($year >= 2020 && $year <= 2100, 404);
        $data = $request->validate([
            'reopen_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $year, $data) {
            $period = FinancialBudgetPeriod::query()->where('year', $year)->lockForUpdate()->firstOrFail();
            abort_unless($period->status === 'approved', 422);
            $period->update([
                'status' => 'draft',
                'reopened_by' => $request->user()->id,
                'reopened_at' => now(),
                'reopen_reason' => $data['reopen_reason'],
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Annual budget reopened for editing.')]);
    }

    public function exportBudgetVersion(int $year, FinancialBudgetVersion $financialBudgetVersion): StreamedResponse
    {
        abort_unless(
            $financialBudgetVersion->period()->where('year', $year)->exists(),
            404,
        );

        return response()->streamDownload(function () use ($year, $financialBudgetVersion) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['year', 'month', 'category', 'amount', 'notes']);
            collect($financialBudgetVersion->values)
                ->sortBy(fn (array $value) => sprintf('%02d-%s', $value['month'], $value['category']))
                ->each(fn (array $value) => fputcsv($out, [
                    $year,
                    $value['month'],
                    $value['category'],
                    number_format((float) $value['amount'], 2, '.', ''),
                    $value['notes'],
                ]));
            fclose($out);
        }, "finance-budget-{$year}-r{$financialBudgetVersion->revision}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function updateAlertSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'operating_variance_alert_percent' => ['required', 'numeric', 'between:0.1,1000'],
            'cash_variance_alert_percent' => ['required', 'numeric', 'between:0.1,1000'],
            'liquidity_buffer_amount' => ['required', 'numeric', 'min:0'],
        ]);
        CompanyProfile::firstOrFail()->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Financial alert thresholds updated.')]);
    }

    public function updateFinancialAlert(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2020,2100'],
            'alert_key' => ['required', 'string', 'max:40'],
            'status' => ['required', Rule::in(['open', 'in_review', 'resolved'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $alert = $this->financialAlerts->forYear((int) $data['year'])
            ->firstWhere('key', $data['alert_key']);
        if (! $alert) {
            throw ValidationException::withMessages([
                'alert_key' => __('This financial alert is no longer active.'),
            ]);
        }

        $action = FinancialAlertAction::firstOrNew([
            'year' => $data['year'],
            'alert_key' => $data['alert_key'],
        ]);
        $action->fill([
            'type' => $alert['type'],
            'month' => $alert['month'],
            'status' => $data['status'],
            'assigned_to' => $data['assigned_to'] ?? null,
            'notes' => $data['notes'],
            'resolved_by' => $data['status'] === 'resolved' ? $request->user()->id : null,
            'resolved_at' => $data['status'] === 'resolved' ? now() : null,
        ]);
        if (! $action->exists) {
            $action->created_by = $request->user()->id;
        }
        $action->save();

        return back()->with('toast', ['type' => 'success', 'message' => __('Financial alert follow-up updated.')]);
    }

    public function storeCollectionAction(Request $request, SalesDocument $salesDocument): RedirectResponse
    {
        abort_unless($salesDocument->type === 'invoice', 422);
        $data = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
            'status' => ['required', Rule::in(['open', 'contacted', 'promised', 'disputed', 'resolved'])],
            'contacted_on' => ['nullable', 'date'],
            'promised_on' => ['nullable', 'date'],
            'promised_amount' => ['nullable', 'numeric', 'gt:0'],
            'next_action_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $action = CollectionAction::firstOrNew(['sales_document_id' => $salesDocument->id]);
        $wasResolved = $action->exists && $action->status === 'resolved';
        $action->fill($data);
        if ($data['status'] === 'resolved') {
            $action->resolution_reason = $wasResolved ? $action->resolution_reason : 'manual';
            $action->resolved_at = $wasResolved ? $action->resolved_at : now();
            $action->next_action_on = null;
        } else {
            $action->resolution_reason = null;
            $action->resolved_at = null;
        }
        $action->save();

        return back()->with('toast', ['type' => 'success', 'message' => __('Collection action saved.')]);
    }

    public function storePaymentBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('is_active', true)],
            'planned_on' => ['required', 'date'],
            'payables' => ['required', 'array', 'min:1', 'max:100'],
            'payables.*' => ['required', 'string', 'regex:/^(invoice|expense):[0-9]+$/'],
        ]);
        $batch = DB::transaction(function () use ($request, $data) {
            PaymentBatch::query()->lockForUpdate()->get(['id']);
            $sequence = PaymentBatch::lockForUpdate()->whereYear('planned_on', date('Y', strtotime($data['planned_on'])))->count() + 1;
            $batch = PaymentBatch::create([
                'number' => 'PAY-'.date('Y', strtotime($data['planned_on'])).'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'planned_on' => $data['planned_on'],
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            foreach (array_unique($data['payables']) as $selection) {
                [$type, $id] = explode(':', $selection);
                $alreadyBatched = PaymentBatchItem::query()
                    ->where('payable_type', $type)
                    ->where('payable_id', $id)
                    ->whereHas('batch', fn ($query) => $query->whereIn('status', ['draft', 'approved', 'exported']))
                    ->exists();
                if ($alreadyBatched) {
                    throw ValidationException::withMessages(['payables' => __('A selected document already belongs to an active payment batch.')]);
                }
                $payable = $type === 'invoice'
                    ? PurchaseDocument::withSum('creditNotes as credited_total', 'amount')->findOrFail($id)
                    : Expense::findOrFail($id);
                $amount = $type === 'invoice' ? $this->balances->purchase($payable) : $this->balances->expense($payable);
                if ($amount <= 0) {
                    throw ValidationException::withMessages(['payables' => __('A selected document has no outstanding balance.')]);
                }
                $batch->items()->create(['payable_type' => $type, 'payable_id' => $payable->id, 'amount' => $amount]);
            }

            return $batch;
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment batch :number prepared.', ['number' => $batch->number])]);
    }

    public function approvePaymentBatch(Request $request, PaymentBatch $paymentBatch): RedirectResponse
    {
        DB::transaction(function () use ($request, $paymentBatch) {
            $batch = PaymentBatch::query()->lockForUpdate()->findOrFail($paymentBatch->id);
            abort_unless($batch->status === 'draft', 422);
            if ($batch->created_by === $request->user()->id) {
                throw ValidationException::withMessages([
                    'payment_batch' => __('The person who prepared a payment batch cannot approve it.'),
                ]);
            }
            $this->validatePaymentBatch($batch, true);
            $batch->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment batch approved.')]);
    }

    public function cancelPaymentBatch(PaymentBatch $paymentBatch): RedirectResponse
    {
        abort_unless(in_array($paymentBatch->status, ['draft', 'approved'], true), 422);
        $paymentBatch->update(['status' => 'cancelled']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment batch cancelled.')]);
    }

    public function exportPaymentBatch(PaymentBatch $paymentBatch): StreamedResponse
    {
        abort_unless(in_array($paymentBatch->status, ['approved', 'exported'], true), 422);
        $paymentBatch->load(['account', 'items']);
        $company = CompanyProfile::firstOrFail();
        $debtorIban = $this->paymentAccountIban($paymentBatch, $company);
        $payments = $this->paymentBatchTransfers($paymentBatch);
        $xml = $this->sepaXml($paymentBatch, $company, $debtorIban, $payments);
        if ($paymentBatch->status === 'approved') {
            $paymentBatch->update(['status' => 'exported', 'exported_at' => now()]);
        }

        return response()->streamDownload(fn () => print $xml, $paymentBatch->number.'.xml', ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function executePaymentBatch(Request $request, PaymentBatch $paymentBatch, AccountingSynchronizer $accounting): RedirectResponse
    {
        $data = $request->validate(['paid_on' => ['required', 'date', 'before_or_equal:today']]);

        DB::transaction(function () use ($request, $paymentBatch, $accounting, $data) {
            $batch = PaymentBatch::query()->lockForUpdate()->findOrFail($paymentBatch->id);
            abort_unless($batch->status === 'exported', 422);
            $items = $batch->items()->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['payment_batch' => __('A payment batch must contain at least one payable document.')]);
            }

            foreach ($items as $item) {
                if ($item->supplier_payment_id) {
                    throw ValidationException::withMessages(['payment_batch' => __('This payment batch item has already been executed.')]);
                }
                $payable = $item->payable_type === 'invoice'
                    ? PurchaseDocument::query()->lockForUpdate()->findOrFail($item->payable_id)
                    : Expense::query()->lockForUpdate()->findOrFail($item->payable_id);
                if ($payable instanceof PurchaseDocument && ($payable->type !== 'supplier_invoice' || $payable->matching_status === 'review_required')) {
                    throw ValidationException::withMessages(['payment_batch' => __('A supplier invoice in this batch can no longer be paid.')]);
                }
                $outstanding = $payable instanceof PurchaseDocument
                    ? $this->balances->purchase($payable)
                    : $this->balances->expense($payable);
                if (abs($outstanding - (float) $item->amount) > 0.005) {
                    throw ValidationException::withMessages(['payment_batch' => __('A document balance changed after export. Review the batch before registering payments.')]);
                }

                $payment = SupplierPayment::create([
                    'purchase_document_id' => $payable instanceof PurchaseDocument ? $payable->id : null,
                    'expense_id' => $payable instanceof Expense ? $payable->id : null,
                    'created_by' => $request->user()->id,
                    'paid_at' => $data['paid_on'],
                    'amount' => $item->amount,
                    'method' => 'bank_transfer',
                    'reference' => $batch->number,
                    'notes' => __('Registered from executed payment batch :batch.', ['batch' => $batch->number]),
                ]);
                $accounting->syncSupplierPayment($payment, $request->user()->id);
                $this->refreshPayableStatus($payable);
                $item->update(['supplier_payment_id' => $payment->id]);
            }

            $batch->update([
                'status' => 'paid',
                'executed_by' => $request->user()->id,
                'executed_at' => now(),
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment batch executed and supplier payments registered.')]);
    }

    public function export(Request $request): StreamedResponse
    {
        $year = $this->year($request);
        $type = $request->validate(['type' => ['required', 'in:cash,vat,receivables,payables,budget,budget_template']])['type'];

        return response()->streamDownload(function () use ($year, $type) {
            $out = fopen('php://output', 'w');
            if ($type === 'cash') {
                fputcsv($out, ['date', 'direction', 'document', 'method', 'reference', 'amount']);
                SalesDocumentPayment::with('document:id,number')->whereYear('paid_at', $year)->orderBy('paid_at')->each(fn ($p) => fputcsv($out, [$p->paid_at->format('Y-m-d'), 'in', $p->document?->number, $p->method, $p->reference, $p->amount]));
                SupplierPayment::with(['expense:id,number', 'purchaseDocument:id,number'])->whereYear('paid_at', $year)->orderBy('paid_at')->each(fn ($p) => fputcsv($out, [$p->paid_at->format('Y-m-d'), 'out', $p->expense?->number ?? $p->purchaseDocument?->number, $p->method, $p->reference, $p->amount]));
            } elseif ($type === 'vat') {
                fputcsv($out, ['date', 'direction', 'document', 'tax_base', 'vat', 'total']);
                SalesDocument::whereIn('type', ['invoice', 'credit_note'])->whereNotNull('locked_at')->whereYear('issue_date', $year)->orderBy('issue_date')->each(fn ($d) => fputcsv($out, [$d->issue_date->format('Y-m-d'), 'output', $d->number, $d->subtotal - $d->discount_amount, $d->tax_amount, $d->amount]));
                PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNot('status', 'review_required')->whereYear('issue_date', $year)->orderBy('issue_date')->each(fn ($d) => fputcsv($out, [$d->issue_date->format('Y-m-d'), 'input', $d->supplier_number ?: $d->number, $d->subtotal, $d->tax_amount, $d->amount]));
                Expense::whereYear('expense_date', $year)->orderBy('expense_date')->each(fn ($d) => fputcsv($out, [$d->expense_date->format('Y-m-d'), 'input', $d->document_number ?: $d->number, $d->subtotal, $d->tax_amount, $d->amount]));
            } elseif (in_array($type, ['budget', 'budget_template'], true)) {
                fputcsv($out, ['year', 'month', 'category', 'amount', 'notes']);
                $budgets = FinancialBudget::where('year', $year)->get()->keyBy(fn ($budget) => $budget->month.'-'.$budget->category);
                if ($type === 'budget') {
                    $budgets->sortBy(fn ($budget) => sprintf('%02d-%s', $budget->month, $budget->category))
                        ->each(fn ($budget) => fputcsv($out, [$budget->year, $budget->month, $budget->category, $budget->amount, $budget->notes]));
                } else {
                    foreach (range(1, 12) as $month) {
                        foreach (['revenue', 'costs', 'cash_in', 'cash_out'] as $category) {
                            $budget = $budgets->get($month.'-'.$category);
                            fputcsv($out, [$year, $month, $category, $budget?->amount ?? 0, $budget?->notes]);
                        }
                    }
                }
            } elseif ($type === 'receivables') {
                fputcsv($out, ['document', 'customer', 'due_date', 'days_overdue', 'status', 'outstanding']);
                SalesDocument::with('customer:id,first_name,last_name,company_name')->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'overdue'])->orderBy('due_date')->each(function ($document) use ($out) {
                    $party = $document->customer?->company_name ?: trim(($document->customer?->first_name ?? '').' '.($document->customer?->last_name ?? ''));
                    fputcsv($out, [$document->number, $party, $document->due_date?->format('Y-m-d'), $this->daysOverdue($document->due_date), $document->status, round((float) $document->amount - (float) $document->paid_amount, 2)]);
                });
            } else {
                fputcsv($out, ['document', 'supplier', 'due_date', 'days_overdue', 'status', 'outstanding']);
                $invoices = PurchaseDocument::with('supplier:id,legal_name,trade_name')->withSum('creditNotes as credited_total', 'amount')->where('type', 'supplier_invoice')->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])->get();
                $expenses = Expense::with('supplier:id,legal_name,trade_name')->whereIn('status', ['pending', 'partial', 'overdue'])->get();
                $this->payables($invoices, $expenses)->each(fn ($item) => fputcsv($out, [$item['number'], $item['party'], $item['due_date'], $item['days_overdue'], $item['status'], $item['outstanding']]));
            }
            fclose($out);
        }, "finance-{$type}-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function year(Request $request): int
    {
        $validated = $request->validate(['year' => ['nullable', 'integer', 'between:2020,2100']]);

        return (int) ($validated['year'] ?? now()->year);
    }

    /** @param Collection<int, FinancialForecastItem> $items */
    private function forecastAccuracy(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [
                'settledCount' => 0,
                'plannedTotal' => 0,
                'actualTotal' => 0,
                'amountVariance' => 0,
                'meanAbsolutePercentageError' => null,
                'averageDaysVariance' => null,
            ];
        }

        $plannedTotal = (float) $items->sum(fn (FinancialForecastItem $item) => (float) ($item->baseline_amount ?? $item->amount));
        $actualTotal = (float) $items->sum(fn (FinancialForecastItem $item) => (float) $item->actual_amount);
        $percentageErrors = $items->map(function (FinancialForecastItem $item) {
            $baseline = (float) ($item->baseline_amount ?? $item->amount);

            return $baseline > 0 ? abs((float) $item->actual_amount - $baseline) / $baseline * 100 : null;
        })->filter(fn (?float $value) => $value !== null);
        $dayVariances = $items->map(fn (FinancialForecastItem $item) => ($item->baseline_due_on ?? $item->due_on)->diffInDays($item->settled_on, false));

        return [
            'settledCount' => $items->count(),
            'plannedTotal' => round($plannedTotal, 2),
            'actualTotal' => round($actualTotal, 2),
            'amountVariance' => round($actualTotal - $plannedTotal, 2),
            'meanAbsolutePercentageError' => $percentageErrors->isEmpty() ? null : round((float) $percentageErrors->average(), 1),
            'averageDaysVariance' => round((float) $dayVariances->average(), 1),
        ];
    }

    private function assertBudgetEditable(int $year): void
    {
        if (FinancialBudgetPeriod::where('year', $year)->where('status', 'approved')->exists()) {
            throw ValidationException::withMessages([
                'budget' => __('The approved annual budget is locked. Reopen it before making changes.'),
            ]);
        }
    }

    private function payables(Collection $invoices, Collection $expenses, ?Collection $activeBatchItems = null): Collection
    {
        $activeBatchItems ??= collect();

        return $invoices->map(fn ($item) => [
            'kind' => 'invoice',
            'id' => $item->id,
            'number' => $item->number,
            'party' => $item->supplier?->trade_name ?: $item->supplier?->legal_name,
            'due_date' => $item->due_date?->toDateString(),
            'days_overdue' => $this->daysOverdue($item->due_date),
            'status' => $item->status,
            'outstanding' => $this->balances->purchase($item),
            'payment_batch' => $this->paymentBatchReference($activeBatchItems->get('invoice:'.$item->id)),
        ])->concat($expenses->map(fn ($item) => [
            'kind' => 'expense',
            'id' => $item->id,
            'number' => $item->number,
            'party' => $item->supplier?->trade_name ?: ($item->supplier?->legal_name ?: $item->merchant_name),
            'due_date' => $item->due_date?->toDateString(),
            'days_overdue' => $this->daysOverdue($item->due_date),
            'status' => $item->status,
            'outstanding' => $this->balances->expense($item),
            'payment_batch' => $this->paymentBatchReference($activeBatchItems->get('expense:'.$item->id)),
        ]))->sortByDesc('days_overdue')->values();
    }

    /** @return Collection<string, PaymentBatchItem> */
    private function activePaymentBatchItems(): Collection
    {
        return PaymentBatchItem::with('batch:id,number,status')
            ->whereHas('batch', fn ($query) => $query->whereIn('status', ['draft', 'approved', 'exported']))
            ->get()
            ->keyBy(fn (PaymentBatchItem $item) => $item->payable_type.':'.$item->payable_id);
    }

    private function paymentBatchReference(?PaymentBatchItem $item): ?array
    {
        return $item?->batch ? [
            'id' => $item->batch->id,
            'number' => $item->batch->number,
            'status' => $item->batch->status,
        ] : null;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function paymentBatchRows(): Collection
    {
        $batches = PaymentBatch::with(['account:id,name,iban', 'executor:id,name', 'items.supplierPayment.reconciliation:id,supplier_payment_id'])
            ->latest('planned_on')
            ->limit(25)
            ->get();
        $items = $batches->flatMap->items;
        $invoices = PurchaseDocument::with('supplier:id,legal_name,trade_name')
            ->whereIn('id', $items->where('payable_type', 'invoice')->pluck('payable_id'))
            ->get()
            ->keyBy('id');
        $expenses = Expense::with('supplier:id,legal_name,trade_name')
            ->whereIn('id', $items->where('payable_type', 'expense')->pluck('payable_id'))
            ->get()
            ->keyBy('id');

        return $batches->map(function (PaymentBatch $batch) use ($invoices, $expenses) {
            $documents = $batch->items->map(function (PaymentBatchItem $item) use ($invoices, $expenses) {
                $document = $item->payable_type === 'invoice'
                    ? $invoices->get($item->payable_id)
                    : $expenses->get($item->payable_id);

                return [
                    'id' => $item->id,
                    'kind' => $item->payable_type,
                    'payable_id' => $item->payable_id,
                    'number' => $document?->supplier_number ?? $document?->document_number ?? $document?->number ?? __('Missing document'),
                    'party' => $document?->supplier?->trade_name ?: ($document?->supplier?->legal_name ?: ($document?->merchant_name ?? __('Supplier'))),
                    'amount' => (float) $item->amount,
                    'payment_registered' => (bool) $item->supplier_payment_id,
                    'reconciled' => (bool) $item->supplierPayment?->reconciliation,
                ];
            });

            return [
                ...$batch->toArray(),
                'items' => $documents,
                'item_count' => $documents->count(),
                'total' => round((float) $documents->sum('amount'), 2),
            ];
        });
    }

    private function forecastMonth(?CarbonInterface $dueDate, int $year): ?int
    {
        if (! $dueDate || $year < now()->year) {
            return null;
        }
        if ($year === now()->year && $dueDate->isBefore(today())) {
            return now()->month;
        }

        return $dueDate->year === $year ? $dueDate->month : null;
    }

    private function daysOverdue(?CarbonInterface $dueDate): int
    {
        return $dueDate?->isBefore(today()) ? (int) $dueDate->diffInDays(today()) : 0;
    }

    private function aging(Collection $items): array
    {
        $buckets = ['current' => 0.0, 'days_1_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'over_90' => 0.0];
        foreach ($items as $item) {
            $days = (int) ($item['days_overdue'] ?? 0);
            $bucket = match (true) {
                $days === 0 => 'current',
                $days <= 30 => 'days_1_30',
                $days <= 60 => 'days_31_60',
                $days <= 90 => 'days_61_90',
                default => 'over_90',
            };
            $buckets[$bucket] += (float) $item['outstanding'];
        }

        return array_map(fn ($amount) => round($amount, 2), $buckets);
    }

    private function budgetSummary(int $year, float $revenue, float $costs, float $cashIn, float $cashOut): array
    {
        $budget = FinancialBudget::where('year', $year)->selectRaw('category, sum(amount) total')->groupBy('category')->pluck('total', 'category');
        $budgetRevenue = (float) ($budget['revenue'] ?? 0);
        $budgetCosts = (float) ($budget['costs'] ?? 0);
        $budgetCashIn = (float) ($budget['cash_in'] ?? 0);
        $budgetCashOut = (float) ($budget['cash_out'] ?? 0);

        return [
            'revenue' => $budgetRevenue,
            'costs' => $budgetCosts,
            'result' => round($budgetRevenue - $budgetCosts, 2),
            'actualResult' => round($revenue - $costs, 2),
            'variance' => round(($revenue - $costs) - ($budgetRevenue - $budgetCosts), 2),
            'cashIn' => $budgetCashIn,
            'cashOut' => $budgetCashOut,
            'cashNet' => round($budgetCashIn - $budgetCashOut, 2),
            'actualCashNet' => round($cashIn - $cashOut, 2),
            'cashVariance' => round(($cashIn - $cashOut) - ($budgetCashIn - $budgetCashOut), 2),
        ];
    }

    private function comparison(int $year, float $revenue, float $costs): array
    {
        $previousRevenue = (float) SalesDocument::whereIn('type', ['invoice', 'credit_note'])->whereNotNull('locked_at')->whereYear('issue_date', $year - 1)->selectRaw('coalesce(sum(subtotal - discount_amount), 0) total')->value('total');
        $previousCosts = (float) PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNot('status', 'review_required')->whereYear('issue_date', $year - 1)->selectRaw('coalesce(sum(subtotal + shipping_cost), 0) total')->value('total')
            + (float) Expense::whereYear('expense_date', $year - 1)->sum('subtotal');

        return [
            'previousYear' => $year - 1,
            'revenue' => round($previousRevenue, 2),
            'costs' => round($previousCosts, 2),
            'result' => round($previousRevenue - $previousCosts, 2),
            'revenueChange' => $previousRevenue !== 0.0 ? round(($revenue - $previousRevenue) / abs($previousRevenue) * 100, 1) : null,
            'resultChange' => ($previousRevenue - $previousCosts) !== 0.0 ? round((($revenue - $costs) - ($previousRevenue - $previousCosts)) / abs($previousRevenue - $previousCosts) * 100, 1) : null,
        ];
    }

    private function kpis(int $year, float $revenue, float $costs, float $receivable, float $payable, float $overdueReceivable, float $overduePayable, int $brokenPromises): array
    {
        return [
            'dso' => $revenue > 0 ? round($receivable / $revenue * 365, 1) : null,
            'dpo' => $costs > 0 ? round($payable / $costs * 365, 1) : null,
            'overdueReceivableRatio' => $receivable > 0 ? round($overdueReceivable / $receivable * 100, 1) : 0,
            'overduePayableRatio' => $payable > 0 ? round($overduePayable / $payable * 100, 1) : 0,
            'cashConversion' => $revenue > 0 ? round(((float) SalesDocumentPayment::whereYear('paid_at', $year)->sum('amount')) / $revenue * 100, 1) : null,
            'brokenPromises' => $brokenPromises,
        ];
    }

    private function refreshPayableStatus(PurchaseDocument|Expense $payable): void
    {
        $paid = (float) $payable->payments()->sum('amount');
        $creditAmount = $payable instanceof PurchaseDocument ? abs((float) $payable->creditNotes()->sum('amount')) : 0;
        $netAmount = (float) $payable->amount - $creditAmount;
        $status = $paid >= $netAmount
            ? 'paid'
            : ($paid > 0 ? 'partial' : ($payable->due_date?->isPast() ? 'overdue' : ($payable instanceof PurchaseDocument ? 'registered' : 'pending')));
        $payable->update(['paid_amount' => $paid, 'status' => $status]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function validatePaymentBatch(PaymentBatch $batch, bool $requireRecipientIban): Collection
    {
        $batch->load(['account', 'items']);
        if ($batch->items->isEmpty()) {
            throw ValidationException::withMessages(['payment_batch' => __('A payment batch must contain at least one payable document.')]);
        }

        $this->paymentAccountIban($batch, CompanyProfile::firstOrFail());

        $transfers = $batch->items->map(function (PaymentBatchItem $item) use ($requireRecipientIban) {
            $document = $item->payable_type === 'invoice'
                ? PurchaseDocument::with('supplier')->withSum('creditNotes as credited_total', 'amount')->findOrFail($item->payable_id)
                : Expense::with('supplier')->findOrFail($item->payable_id);
            $amount = $item->payable_type === 'invoice'
                ? $this->balances->purchase($document)
                : $this->balances->expense($document);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['payment_batch' => __('A payment batch document no longer has an outstanding balance.')]);
            }
            if ($requireRecipientIban && ! $document->supplier?->iban) {
                throw ValidationException::withMessages(['payment_batch' => __('Every payment recipient must have an IBAN.')]);
            }
            $transfer = $this->paymentTransfer($document, $amount);
            if ($requireRecipientIban && ! $this->validIban($transfer['iban'])) {
                throw ValidationException::withMessages(['payment_batch' => __('A payment recipient has an invalid IBAN.')]);
            }
            $item->update([
                'amount' => $amount,
                'recipient_name' => $transfer['name'],
                'recipient_iban' => $this->normalizeIban($transfer['iban']),
                'recipient_bic' => $transfer['bic'],
                'payment_reference' => $transfer['reference'],
            ]);

            return $transfer;
        });

        if ((float) $transfers->sum('amount') <= 0) {
            throw ValidationException::withMessages(['payment_batch' => __('A payment batch must have a positive total.')]);
        }

        return $transfers;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function paymentBatchTransfers(PaymentBatch $batch): Collection
    {
        if ($batch->items->isEmpty()) {
            throw ValidationException::withMessages(['payment_batch' => __('A payment batch must contain at least one payable document.')]);
        }

        return $batch->items->map(function (PaymentBatchItem $item) {
            if (! $item->recipient_name || ! $item->recipient_iban || ! $item->payment_reference) {
                throw ValidationException::withMessages(['payment_batch' => __('The approved recipient snapshot is incomplete. Cancel and prepare the batch again.')]);
            }

            return [
                'name' => $item->recipient_name,
                'iban' => $item->recipient_iban,
                'bic' => $item->recipient_bic,
                'reference' => $item->payment_reference,
                'amount' => (float) $item->amount,
            ];
        });
    }

    /** @return array<string, mixed> */
    private function paymentTransfer(PurchaseDocument|Expense $document, float $amount): array
    {
        return [
            'name' => $document->supplier->trade_name ?: $document->supplier->legal_name,
            'iban' => $document->supplier->iban,
            'bic' => $document->supplier->bic,
            'reference' => $document->supplier_number ?? $document->document_number ?? $document->number,
            'amount' => $amount,
        ];
    }

    private function paymentAccountIban(PaymentBatch $batch, CompanyProfile $company): string
    {
        if ($batch->account && ! $batch->account->is_active) {
            throw ValidationException::withMessages(['payment_batch' => __('The selected payment account is inactive.')]);
        }
        $iban = $this->normalizeIban($batch->account?->iban ?: $company->iban);
        if (! $iban) {
            throw ValidationException::withMessages(['payment_batch' => __('Configure the paying account IBAN before approving the batch.')]);
        }
        if (! $this->validIban($iban)) {
            throw ValidationException::withMessages(['payment_batch' => __('The paying account has an invalid IBAN.')]);
        }

        return $iban;
    }

    private function normalizeIban(mixed $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $iban));
    }

    private function validIban(mixed $iban): bool
    {
        $iban = $this->normalizeIban($iban);
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }

    private function sepaXml(PaymentBatch $batch, CompanyProfile $company, string $debtorIban, Collection $payments): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $transactions = $payments->map(fn ($payment, $index) => '<CdtTrfTxInf><PmtId><EndToEndId>'.$escape($batch->number.'-'.($index + 1)).'</EndToEndId></PmtId><Amt><InstdAmt Ccy="EUR">'.number_format($payment['amount'], 2, '.', '').'</InstdAmt></Amt>'.($payment['bic'] ? '<CdtrAgt><FinInstnId><BIC>'.$escape($payment['bic']).'</BIC></FinInstnId></CdtrAgt>' : '').'<Cdtr><Nm>'.$escape($payment['name']).'</Nm></Cdtr><CdtrAcct><Id><IBAN>'.$escape(preg_replace('/\s+/', '', $payment['iban'])).'</IBAN></Id></CdtrAcct><RmtInf><Ustrd>'.$escape($payment['reference']).'</Ustrd></RmtInf></CdtTrfTxInf>')->implode('');
        $total = number_format((float) $payments->sum('amount'), 2, '.', '');

        return '<?xml version="1.0" encoding="UTF-8"?><Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03"><CstmrCdtTrfInitn><GrpHdr><MsgId>'.$escape($batch->number).'</MsgId><CreDtTm>'.now()->toIso8601String().'</CreDtTm><NbOfTxs>'.$payments->count().'</NbOfTxs><CtrlSum>'.$total.'</CtrlSum><InitgPty><Nm>'.$escape($company->legal_name).'</Nm></InitgPty></GrpHdr><PmtInf><PmtInfId>'.$escape($batch->number).'</PmtInfId><PmtMtd>TRF</PmtMtd><BtchBookg>true</BtchBookg><NbOfTxs>'.$payments->count().'</NbOfTxs><CtrlSum>'.$total.'</CtrlSum><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl></PmtTpInf><ReqdExctnDt>'.$batch->planned_on->toDateString().'</ReqdExctnDt><Dbtr><Nm>'.$escape($company->legal_name).'</Nm></Dbtr><DbtrAcct><Id><IBAN>'.$escape($debtorIban).'</IBAN></Id></DbtrAcct><DbtrAgt><FinInstnId><BIC>NOTPROVIDED</BIC></FinInstnId></DbtrAgt><ChrgBr>SLEV</ChrgBr>'.$transactions.'</PmtInf></CstmrCdtTrfInitn></Document>';
    }
}
