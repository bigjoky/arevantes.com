<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\FiscalYearClosing;
use App\Services\Accounting\JournalPoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function index(Request $request): Response
    {
        $year = $request->integer('year', now()->year);
        $entries = JournalEntry::with(['lines.account:id,code,name', 'reversal:id,reverses_entry_id'])->whereYear('entry_date', $year)->latest('entry_date')->latest('id')->limit(250)->get();
        $accounts = LedgerAccount::where('is_postable', true)->withSum(['lines as debit' => fn ($query) => $query->whereHas('entry', fn ($entry) => $entry->whereYear('entry_date', $year))], 'debit')->withSum(['lines as credit' => fn ($query) => $query->whereHas('entry', fn ($entry) => $entry->whereYear('entry_date', $year))], 'credit')->orderBy('code')->get()->map(function ($account) {
            $debit = round((float) $account->debit, 2);
            $credit = round((float) $account->credit, 2);
            $naturalBalance = in_array($account->type, ['liability', 'equity', 'income'], true) ? $credit - $debit : $debit - $credit;

            return [...$account->toArray(), 'debit' => $debit, 'credit' => $credit, 'balance' => round($naturalBalance, 2)];
        });
        $totals = JournalLine::whereHas('entry', fn ($entry) => $entry->whereYear('entry_date', $year))->selectRaw('coalesce(sum(debit), 0) as debit, coalesce(sum(credit), 0) as credit')->first();
        $selectedAccount = $request->filled('account') ? LedgerAccount::where('code', $request->string('account'))->first() : null;
        $ledger = collect();

        if ($selectedAccount) {
            $running = 0.0;
            $creditNature = in_array($selectedAccount->type, ['liability', 'equity', 'income'], true);
            $ledger = $selectedAccount->lines()->with('entry:id,entry_date,number,description')->whereHas('entry', fn ($entry) => $entry->whereYear('entry_date', $year))->get()->sortBy(fn ($line) => $line->entry->entry_date->format('Y-m-d').'-'.str_pad((string) $line->journal_entry_id, 12, '0', STR_PAD_LEFT))->values()->map(function ($line) use (&$running, $creditNature) {
                $running += $creditNature ? (float) $line->credit - (float) $line->debit : (float) $line->debit - (float) $line->credit;

                return [...$line->toArray(), 'running_balance' => round($running, 2)];
            });
        }

        $income = round((float) $accounts->where('type', 'income')->sum('balance'), 2);
        $expenses = round((float) $accounts->where('type', 'expense')->sum('balance'), 2);

        return Inertia::render('accounting/index', [
            'year' => $year,
            'fiscalYear' => FiscalYear::where('year', $year)->first(),
            'entries' => $entries,
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'ledger' => $ledger,
            'reports' => [
                'profitAndLoss' => ['income' => $income, 'expenses' => $expenses, 'result' => round($income - $expenses, 2)],
                'balanceSheet' => ['assets' => round((float) $accounts->where('type', 'asset')->sum('balance'), 2), 'liabilities' => round((float) $accounts->where('type', 'liability')->sum('balance'), 2), 'equity' => round((float) $accounts->where('type', 'equity')->sum('balance'), 2), 'result' => round($income - $expenses, 2)],
            ],
            'summary' => ['entries' => JournalEntry::whereYear('entry_date', $year)->count(), 'debit' => round((float) $totals->debit, 2), 'credit' => round((float) $totals->credit, 2)],
            'expenseCategories' => ExpenseCategory::orderBy('name')->get(['id', 'code', 'name', 'expense_account_code', 'is_active']),
            'taxRates' => TaxRate::orderByDesc('rate')->get(['id', 'name', 'rate', 'kind', 'output_account_code', 'input_account_code', 'is_active']),
            'thirdParties' => $this->thirdParties($accounts),
        ]);
    }

    public function sync(Request $request, AccountingSynchronizer $synchronizer): RedirectResponse
    {
        $created = $synchronizer->sync($request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __(':count accounting entries generated.', ['count' => $created])]);
    }

    public function store(Request $request, JournalPoster $poster): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'], 'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2', 'max:50'],
            'lines.*.account_code' => ['required', 'string', 'exists:ledger_accounts,code'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'], 'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);
        $poster->post($data['entry_date'], $data['description'], $data['lines'], $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Manual journal entry posted.')]);
    }

    public function reverse(Request $request, JournalEntry $entry, JournalPoster $poster): RedirectResponse
    {
        $data = $request->validate(['entry_date' => ['required', 'date'], 'description' => ['nullable', 'string', 'max:255']]);
        $entry->load('lines.account:id,code');
        $lines = $entry->lines->map(fn ($line) => ['account_code' => $line->account->code, 'description' => $line->description, 'debit' => $line->credit, 'credit' => $line->debit])->all();
        $poster->post($data['entry_date'], $data['description'] ?? __('Reversal of :entry', ['entry' => $entry->number]), $lines, $request->user()->id, 'reversal', null, 'reversal', $entry);

        return back()->with('toast', ['type' => 'success', 'message' => __('Reversal entry posted.')]);
    }

    public function close(Request $request, FiscalYear $fiscalYear, FiscalYearClosing $closing): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', Rule::in([(string) $fiscalYear->year])]]);
        $closing->close($fiscalYear, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Fiscal year closed with its closing entry.')]);
    }

    public function opening(Request $request, FiscalYear $fiscalYear, FiscalYearClosing $closing): RedirectResponse
    {
        $closing->openNext($fiscalYear, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Opening entry generated.')]);
    }

    public function updateExpenseCategory(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $data = $request->validate(['expense_account_code' => ['required', Rule::exists('ledger_accounts', 'code')->where(fn ($query) => $query->where('type', 'expense')->where('is_postable', true)->where('is_active', true))]]);
        $category->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Expense accounting mapping updated.')]);
    }

    public function updateTaxRate(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $data = $request->validate(['output_account_code' => ['required', Rule::exists('ledger_accounts', 'code')->where(fn ($query) => $query->where('is_postable', true)->where('is_active', true))], 'input_account_code' => ['required', Rule::exists('ledger_accounts', 'code')->where(fn ($query) => $query->where('is_postable', true)->where('is_active', true))]]);
        $taxRate->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('VAT accounting mapping updated.')]);
    }

    private function thirdParties($accounts): array
    {
        $balances = $accounts->keyBy('code')->map->balance;
        $sales = SalesDocument::where('type', 'invoice')->whereNotNull('person_id')->whereIn('status', ['issued', 'partial', 'overdue'])->get()->groupBy('person_id');
        $purchases = PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])->get()->groupBy('supplier_id');
        $expenses = Expense::whereNotNull('supplier_id')->whereIn('status', ['pending', 'partial', 'overdue'])->get()->groupBy('supplier_id');
        $customers = Person::where('category', 'customer')->whereNotNull('receivable_account_code')->get()->map(function ($person) use ($sales, $balances) {
            $items = $sales->get($person->id, collect());

            return $this->partyRow($person->id, $person->company_name ?: trim($person->first_name.' '.$person->last_name), $person->receivable_account_code, (float) ($balances[$person->receivable_account_code] ?? 0), $items, 'customer');
        })->filter(fn ($row) => $row['outstanding'] != 0 || $row['ledgerBalance'] != 0)->values();
        $suppliers = Supplier::whereNotNull('payable_account_code')->get()->map(function ($supplier) use ($purchases, $expenses, $balances) {
            $items = $purchases->get($supplier->id, collect())->concat($expenses->get($supplier->id, collect()));

            return $this->partyRow($supplier->id, $supplier->trade_name ?: $supplier->legal_name, $supplier->payable_account_code, (float) ($balances[$supplier->payable_account_code] ?? 0), $items, 'supplier');
        })->filter(fn ($row) => $row['outstanding'] != 0 || $row['ledgerBalance'] != 0)->values();

        return compact('customers', 'suppliers');
    }

    private function partyRow(int $id, string $name, string $code, float $ledgerBalance, $items, string $kind): array
    {
        $buckets = ['current' => 0.0, 'days30' => 0.0, 'days60' => 0.0, 'days90' => 0.0, 'older' => 0.0];
        foreach ($items as $item) {
            $amount = round((float) $item->amount - (float) $item->paid_amount, 2);
            $days = $item->due_date ? $item->due_date->diffInDays(today(), false) : 0;
            $bucket = ! $item->due_date || $days <= 0 ? 'current' : ($days <= 30 ? 'days30' : ($days <= 60 ? 'days60' : ($days <= 90 ? 'days90' : 'older')));
            $buckets[$bucket] += $amount;
        }

        return ['id' => $id, 'kind' => $kind, 'name' => $name, 'accountCode' => $code, 'ledgerBalance' => round($ledgerBalance, 2), 'outstanding' => round(array_sum($buckets), 2), ...array_map(fn ($value) => round($value, 2), $buckets)];
    }
}
