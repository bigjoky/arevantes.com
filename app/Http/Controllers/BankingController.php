<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankFinancialProduct;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationRule;
use App\Models\BankReconciliationRun;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionPosting;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\TaxReturnPayment;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\JournalPoster;
use App\Services\Accounting\LedgerAccountResolver;
use App\Services\Banking\BankFinancialProductManager;
use App\Services\Banking\BankReconciliationRuleEngine;
use App\Services\Banking\BankTransactionAnalyzer;
use App\Services\Banking\BankTransactionPoster;
use App\Services\Banking\CardSettlementSchedule;
use App\Services\Banking\StatementImporter;
use App\Services\Finance\OutstandingBalanceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BankingController extends Controller
{
    public function __construct(
        private OutstandingBalanceCalculator $balances,
        private CardSettlementSchedule $cardSettlements,
        private BankTransactionAnalyzer $transactionAnalyzer,
        private BankReconciliationRuleEngine $ruleEngine,
    ) {}

    public function index(Request $request): Response
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'status' => in_array($request->string('status')->toString(), ['pending', 'reconciled', 'accounted'], true) ? $request->string('status')->toString() : '',
            'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '',
            'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '',
        ];
        $accounts = BankAccount::withSum('transactions as transaction_total', 'amount')->orderByDesc('is_active')->orderBy('name')->get()->map(fn ($account) => [...$account->toArray(), 'balance' => round((float) $account->opening_balance + (float) ($account->transaction_total ?? 0), 2)]);
        $account = BankAccount::with('ledgerAccount')->find($request->integer('account')) ?: BankAccount::with('ledgerAccount')->where('is_active', true)->first();
        $historicalAmounts = $account
            ? $account->transactions()->whereDate('booked_on', '>=', today()->subDays(180))->pluck('amount')
            : collect();
        $medianAmount = $this->transactionAnalyzer->medianAbsoluteAmount($historicalAmounts);
        $transactions = ($account
            ? $account->transactions()->with(['reconciliation', 'posting.counterpart:id,code,name', 'outgoingTransfer', 'incomingTransfer'])
            : BankTransaction::query()->whereRaw('1 = 0'))
            ->when($filters['search'], fn ($query, $search) => $query->where(fn ($query) => $query->where('description', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%")))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'], fn ($query, $from) => $query->whereDate('booked_on', '>=', $from))
            ->when($filters['to'], fn ($query, $to) => $query->whereDate('booked_on', '<=', $to))
            ->latest('booked_on')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
        $candidatePool = $this->candidatePool($transactions->getCollection());
        $activeRules = $account ? $this->ruleEngine->rules($account) : collect();
        $transactions->through(function ($transaction) use ($medianAmount, $candidatePool, $activeRules) {
            $candidates = $transaction->status === 'pending' ? $this->candidates($transaction, false, $candidatePool) : [];

            return [
                ...$transaction->toArray(),
                'transfer' => $transaction->outgoingTransfer ?? $transaction->incomingTransfer,
                'candidates' => $candidates,
                'rule_suggestion' => $transaction->status === 'pending' ? $this->ruleEngine->suggest($transaction, $activeRules) : null,
                ...$this->transactionAnalyzer->analyze($transaction, $candidates, $medianAmount),
            ];
        });

        $summary = $account ? [
            'pending' => $account->transactions()->where('status', 'pending')->count(),
            'reconciled' => $account->transactions()->whereIn('status', ['reconciled', 'accounted'])->count(),
            'pendingAmount' => round((float) $account->transactions()->where('status', 'pending')->sum('amount'), 2),
            'stale' => $account->transactions()->where('status', 'pending')->whereDate('booked_on', '<', today()->subDays(7))->count(),
            'missingReference' => $account->transactions()->where('status', 'pending')->where(fn ($query) => $query->whereNull('reference')->orWhere('reference', ''))->count(),
            'unusualAmount' => $medianAmount > 0 ? $account->transactions()->where('status', 'pending')->whereRaw('abs(amount) >= ?', [max(1000, $medianAmount * 3)])->count() : 0,
            'medianAmount' => $medianAmount,
        ] : ['pending' => 0, 'reconciled' => 0, 'pendingAmount' => 0, 'stale' => 0, 'missingReference' => 0, 'unusualAmount' => 0, 'medianAmount' => 0];

        $products = BankFinancialProduct::with(['bankAccount:id,name', 'ledgerAccount:id,code,name', 'sharedCreditFacility:id,type,name,credit_limit,used_amount,currency,bank_account_id,billing_day,payment_day', 'sharedCreditFacility.bankAccount:id,name', 'linkedCards:id,name,shared_credit_facility_id,used_amount'])
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function (BankFinancialProduct $product) {
                $creditPool = $product->sharedCreditFacility ?? $product;
                $settlement = $creditPool->type === 'credit_facility' && ($product->shared_credit_facility_id || $creditPool->linkedCards->isNotEmpty())
                    ? $this->cardSettlements->next($creditPool)
                    : null;

                return [
                    ...$product->toArray(),
                    'available_amount' => round((float) $creditPool->credit_limit - (float) $creditPool->used_amount, 2),
                    'utilization' => (float) $creditPool->credit_limit > 0 ? round((float) $creditPool->used_amount / (float) $creditPool->credit_limit * 100, 1) : 0,
                    'expires_soon' => $product->expires_on?->between(today(), today()->addDays(60)) ?? false,
                    'expired' => $product->expires_on?->isBefore(today()) ?? false,
                    'next_closing_on' => $settlement ? $settlement['closingOn']->toDateString() : null,
                    'next_payment_on' => $settlement ? $settlement['paymentOn']->toDateString() : null,
                    'payment_bank_account' => $settlement ? $creditPool->bankAccount?->only(['id', 'name']) : null,
                ];
            });
        $productSummary = $products->where('is_active', true)->whereNull('shared_credit_facility_id')->groupBy('currency')->map(fn (Collection $currencyProducts, string $currency) => [
            'currency' => $currency,
            'limit' => round((float) $currencyProducts->sum(fn ($product) => (float) $product['credit_limit']), 2),
            'used' => round((float) $currencyProducts->sum(fn ($product) => (float) $product['used_amount']), 2),
            'available' => round((float) $currencyProducts->sum(fn ($product) => (float) $product['available_amount']), 2),
        ])->values();

        $imports = $account ? $account->statementImports()
            ->with(['user:id,name', 'rolledBackBy:id,name'])
            ->withCount([
                'transactions',
                'transactions as pending_transactions_count' => fn ($query) => $query->where('status', 'pending'),
                'transactions as processed_transactions_count' => fn ($query) => $query->where(fn ($query) => $query
                    ->whereHas('reconciliation')
                    ->orWhereHas('posting')
                    ->orWhereHas('outgoingTransfer')
                    ->orWhereHas('incomingTransfer')),
            ])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (BankStatementImport $import) => [
                ...$import->toArray(),
                'source_account' => ($source = data_get($import->control_data, 'account'))
                    ? $source['entity'].'-'.$source['office'].'-'.substr($source['account'], -4)
                    : null,
                'can_rollback' => ! $import->rolled_back_at
                    && $import->created_transactions > 0
                    && $import->transactions_count === $import->created_transactions
                    && $import->pending_transactions_count === $import->transactions_count
                    && $import->processed_transactions_count === 0,
            ]) : [];

        $rules = $account ? BankReconciliationRule::with(['ledgerAccount:id,code,name', 'versions.changer:id,name'])->whereBelongsTo($account, 'account')->orderBy('priority')->orderBy('name')->get() : [];
        $ruleRuns = $account ? BankReconciliationRun::with(['triggeredBy:id,name', 'reversedBy:id,name', 'items.posting.transaction:id,status'])->whereBelongsTo($account, 'account')->latest()->limit(5)->get()->map(function (BankReconciliationRun $run) {
            $applied = $run->items->where('outcome', 'applied');
            $stateIsReversible = $applied->isNotEmpty() && $applied->every(fn ($item) => $item->posting && ($item->posting->reversed_at ? $item->posting->transaction?->status === 'pending' : $item->posting->transaction?->status === 'accounted'));

            return [...$run->toArray(), 'posting_count' => $applied->count(), 'can_reverse' => ! $run->reversed_at && $stateIsReversible];
        }) : [];

        return Inertia::render('banking/index', ['accounts' => $accounts, 'selectedAccount' => $account, 'transactions' => $transactions, 'imports' => $imports, 'control' => $account ? $this->bankControl($account) : null, 'ledgerAccounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->whereNotIn('code', ['430', '400', '410', '472', '477'])->orderBy('code')->get(['id', 'code', 'name', 'type']), 'liabilityAccounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->where('type', 'liability')->orderBy('code')->get(['id', 'code', 'name']), 'products' => $products, 'productSummary' => $productSummary, 'summary' => $summary, 'filters' => $filters, 'reconciliationRules' => $rules, 'rulePreview' => $account ? $this->ruleEngine->preview($account, $activeRules) : null, 'ruleRuns' => $ruleRuns]);
    }

    public function storeRule(Request $request, BankAccount $account): RedirectResponse
    {
        $data = $this->validatedRule($request, null, $account);
        DB::transaction(function () use ($data, $account, $request) {
            $rule = BankReconciliationRule::create([...$data, 'bank_account_id' => $account->id, 'current_version' => 1, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $rule->versions()->create(['version' => 1, 'snapshot' => $this->ruleSnapshot($rule), 'change_reason' => __('Initial rule created.'), 'changed_by' => $request->user()->id]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Reconciliation rule created. Review its preview before applying it.')]);
    }

    public function updateRule(Request $request, BankReconciliationRule $rule): RedirectResponse
    {
        $reason = $request->validate(['change_reason' => ['required', 'string', 'min:10', 'max:500']])['change_reason'];
        $data = $this->validatedRule($request, $rule, $rule->account);
        DB::transaction(function () use ($rule, $data, $reason, $request) {
            $rule = BankReconciliationRule::lockForUpdate()->findOrFail($rule->id);
            $version = $rule->current_version + 1;
            $rule->update([...$data, 'current_version' => $version, 'updated_by' => $request->user()->id]);
            $rule->versions()->create(['version' => $version, 'snapshot' => $this->ruleSnapshot($rule), 'change_reason' => $reason, 'changed_by' => $request->user()->id]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Reconciliation rule updated and versioned.')]);
    }

    public function destroyRule(Request $request, BankReconciliationRule $rule): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']])['reason'];
        DB::transaction(function () use ($rule, $request, $reason) {
            $rule = BankReconciliationRule::lockForUpdate()->findOrFail($rule->id);
            if (! $rule->is_active) {
                throw ValidationException::withMessages(['reason' => __('This rule is already inactive.')]);
            }
            $version = $rule->current_version + 1;
            $rule->update(['is_active' => false, 'current_version' => $version, 'updated_by' => $request->user()->id]);
            $rule->versions()->create(['version' => $version, 'snapshot' => $this->ruleSnapshot($rule), 'change_reason' => $reason, 'changed_by' => $request->user()->id]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Reconciliation rule removed from future runs.')]);
    }

    public function applyRules(Request $request, BankAccount $account): RedirectResponse
    {
        $data = $request->validate(['minimum_confidence' => ['required', 'integer', 'between:80,100'], 'confirmation' => ['required', 'string']]);
        $preview = $this->ruleEngine->preview($account);
        if ($data['confirmation'] !== 'APPLY-'.$preview['matched']) {
            throw ValidationException::withMessages(['confirmation' => __('The rule preview changed. Refresh the page and confirm the current matched count.')]);
        }
        $run = $this->ruleEngine->apply($account, $request->user()->id, (int) $data['minimum_confidence']);

        return back()->with('toast', ['type' => $run->failed_count || data_get($run->summary, 'conflicts') ? 'warning' : 'success', 'message' => __('Rule run completed: :matched applied, :failed failed, :conflicts conflicts and :skipped skipped.', ['matched' => $run->matched_count, 'failed' => $run->failed_count, 'conflicts' => data_get($run->summary, 'conflicts', 0), 'skipped' => $run->skipped_count])]);
    }

    public function reverseRuleRun(Request $request, BankReconciliationRun $run): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500'], 'confirmation' => ['required', 'string']]);
        $postingCount = $run->items()->where('outcome', 'applied')->count();
        if ($data['confirmation'] !== 'REVERSE-'.$run->id.'-'.$postingCount) {
            throw ValidationException::withMessages(['confirmation' => __('The run contents changed. Refresh the page and confirm the current posting count.')]);
        }
        $this->ruleEngine->reverse($run, $request->user()->id, $data['reason']);

        return back()->with('toast', ['type' => 'success', 'message' => __('The complete rule run was reversed and its movements returned to pending review.')]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'bank_name' => ['nullable', 'string', 'max:255'], 'iban' => ['nullable', 'string', 'max:34', 'unique:bank_accounts'], 'currency' => ['required', 'string', 'size:3'], 'opening_balance' => ['required', 'numeric'], 'is_active' => ['boolean']]);
        $account = DB::transaction(function () use ($data) {
            $account = BankAccount::create([...$data, 'ledger_account_id' => LedgerAccount::where('code', '572')->value('id')]);
            $ledger = LedgerAccount::create(['code' => '572'.str_pad((string) $account->id, 4, '0', STR_PAD_LEFT), 'name' => $account->name, 'type' => 'asset', 'is_active' => true]);
            $account->update(['ledger_account_id' => $ledger->id]);

            return $account;
        });

        return to_route('banking.index', ['account' => $account->id])->with('toast', ['type' => 'success', 'message' => __('Bank account created.')]);
    }

    public function storeProduct(Request $request, BankFinancialProductManager $manager): RedirectResponse
    {
        $manager->create($this->validatedProduct($request), $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Bank financial product created.')]);
    }

    public function updateProduct(Request $request, BankFinancialProduct $product, BankFinancialProductManager $manager): RedirectResponse
    {
        $manager->update($product, $this->validatedProduct($request));

        return back()->with('toast', ['type' => 'success', 'message' => __('Bank financial product updated.')]);
    }

    public function storeSharedFacility(Request $request, BankFinancialProductManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contract_reference' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['required', 'numeric', 'gt:0'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'billing_day' => ['required', 'integer', 'between:1,31'],
            'payment_day' => ['required', 'integer', 'between:1,31'],
            'card_ids' => ['required', 'array', 'min:2'],
            'card_ids.*' => ['required', 'integer', 'distinct', 'exists:bank_financial_products,id'],
        ]);
        $manager->createSharedFacility($data, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Shared credit facility created and cards linked.')]);
    }

    public function import(Request $request, BankAccount $account, StatementImporter $importer): RedirectResponse
    {
        $data = $request->validate(['statement' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/octet-stream', 'max:10240']]);
        $file = $data['statement'];
        $result = DB::transaction(function () use ($account, $file, $importer, $request): array {
            $result = $importer->import($account, $file);
            $metadata = $result['metadata'] ?? [];
            $statementImport = BankStatementImport::create(['bank_account_id' => $account->id, 'imported_by' => $request->user()->id, 'filename' => $file->getClientOriginalName(), 'file_hash' => hash_file('sha256', $file->getRealPath()), 'format' => $result['format'], 'period_from' => $metadata['period_from'] ?? null, 'period_to' => $metadata['period_to'] ?? null, 'opening_balance' => $metadata['opening_balance'] ?? null, 'closing_balance' => $metadata['closing_balance'] ?? null, 'created_transactions' => $result['created'], 'duplicate_transactions' => $result['duplicates'], 'control_valid' => $metadata['control_valid'] ?? null, 'control_data' => $metadata['control_data'] ?? null]);
            if ($result['created_ids'] !== []) {
                BankTransaction::whereIn('id', $result['created_ids'])->update(['bank_statement_import_id' => $statementImport->id]);
            }

            return $result;
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Imported :created transactions; :duplicates were already imported.', ['created' => $result['created'], 'duplicates' => $result['duplicates']])]);
    }

    public function previewImport(Request $request, BankAccount $account, StatementImporter $importer): JsonResponse
    {
        $data = $request->validate(['statement' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/octet-stream', 'max:10240']]);
        $result = $importer->preview($account, $data['statement']);
        $metadata = $result['metadata'] ?? [];

        return response()->json([
            'filename' => $data['statement']->getClientOriginalName(),
            'format' => $result['format'],
            'total' => $result['created'] + $result['duplicates'],
            'created' => $result['created'],
            'duplicates' => $result['duplicates'],
            'period_from' => $metadata['period_from'] ?? null,
            'period_to' => $metadata['period_to'] ?? null,
            'opening_balance' => $metadata['opening_balance'] ?? null,
            'closing_balance' => $metadata['closing_balance'] ?? null,
            'control_valid' => $metadata['control_valid'] ?? null,
            'source_account' => data_get($metadata, 'control_data.account'),
            'sample' => $result['sample'],
        ]);
    }

    public function rollbackImport(Request $request, BankStatementImport $statementImport): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        DB::transaction(function () use ($request, $statementImport, $data) {
            $statementImport = BankStatementImport::lockForUpdate()->findOrFail($statementImport->id);
            if ($statementImport->rolled_back_at) {
                throw ValidationException::withMessages(['reason' => __('This statement import was already rolled back.')]);
            }

            $transactions = $statementImport->transactions()->lockForUpdate()->get();
            if ($statementImport->created_transactions === 0 || $transactions->count() !== $statementImport->created_transactions) {
                throw ValidationException::withMessages(['reason' => __('This import cannot be rolled back because its movements are not fully traceable.')]);
            }
            if ($transactions->contains(fn (BankTransaction $transaction) => $transaction->status !== 'pending')) {
                throw ValidationException::withMessages(['reason' => __('Undo reconciliations and accounting postings before rolling back this import.')]);
            }
            $transactionIds = $transactions->pluck('id');
            $hasHistory = BankReconciliation::whereIn('bank_transaction_id', $transactionIds)->exists()
                || BankTransactionPosting::whereIn('bank_transaction_id', $transactionIds)->exists()
                || BankTransfer::whereIn('outgoing_transaction_id', $transactionIds)->orWhereIn('incoming_transaction_id', $transactionIds)->exists();
            if ($hasHistory) {
                throw ValidationException::withMessages(['reason' => __('This import has reconciliation or accounting history and cannot be removed.')]);
            }

            $statementImport->transactions()->delete();
            $statementImport->update([
                'rolled_back_by' => $request->user()->id,
                'rolled_back_at' => now(),
                'rollback_reason' => $data['reason'],
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Statement import rolled back; its pending movements were removed.')]);
    }

    private function validatedProduct(Request $request): array
    {
        if ($request->input('shared_credit_facility_id') === 'independent') {
            $request->merge(['shared_credit_facility_id' => null]);
        }
        $data = $request->validate([
            'type' => ['required', Rule::in(['credit_card', 'credit_facility'])],
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'shared_credit_facility_id' => ['nullable', 'integer', 'exists:bank_financial_products,id'],
            'ledger_account_id' => ['required', Rule::exists('ledger_accounts', 'id')->where(fn ($query) => $query->where('type', 'liability')->where('is_active', true)->where('is_postable', true))],
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'identifier_last_four' => ['nullable', 'digits:4'],
            'contract_reference' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'gt:0'],
            'used_amount' => ['required', 'numeric', 'min:0'],
            'annual_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'annual_fee' => ['nullable', 'numeric', 'min:0'],
            'billing_day' => ['nullable', 'integer', 'between:1,31'],
            'payment_day' => ['nullable', 'integer', 'between:1,31'],
            'starts_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['currency'] = Str::upper($data['currency']);
        if ($data['type'] === 'credit_card') {
            $data['contract_reference'] = null;
        } else {
            $data['identifier_last_four'] = null;
        }

        return $data;
    }

    private function validatedRule(Request $request, ?BankReconciliationRule $rule = null, ?BankAccount $account = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('bank_reconciliation_rules')->where('bank_account_id', $rule?->bank_account_id ?? $request->route('account')?->id)->ignore($rule)],
            'ledger_account_id' => ['required', Rule::exists('ledger_accounts', 'id')->where(fn ($query) => $query->where('is_active', true)->where('is_postable', true))],
            'priority' => ['required', 'integer', 'between:1,1000'],
            'direction' => ['required', Rule::in(['any', 'in', 'out'])],
            'match_field' => ['required', Rule::in(['description', 'reference', 'either'])],
            'match_operator' => ['required', Rule::in(['contains', 'starts_with', 'exact'])],
            'pattern' => ['required', 'string', 'min:3', 'max:255'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'confidence' => ['required', 'integer', 'between:80,100'],
            'is_active' => ['required', 'boolean'],
        ]);
        if (isset($data['min_amount'], $data['max_amount']) && (float) $data['min_amount'] > (float) $data['max_amount']) {
            throw ValidationException::withMessages(['max_amount' => __('Maximum amount must be greater than or equal to minimum amount.')]);
        }
        if ($account?->ledger_account_id === (int) $data['ledger_account_id']) {
            throw ValidationException::withMessages(['ledger_account_id' => __('A rule cannot post against the same bank ledger account.')]);
        }

        return $data;
    }

    private function ruleSnapshot(BankReconciliationRule $rule): array
    {
        return $rule->only(['name', 'ledger_account_id', 'priority', 'direction', 'match_field', 'match_operator', 'pattern', 'min_amount', 'max_amount', 'confidence', 'is_active']);
    }

    public function reconcile(Request $request, BankTransaction $transaction, JournalPoster $poster, AccountingSynchronizer $accounting): RedirectResponse
    {
        abort_unless($transaction->status === 'pending', 422);
        $data = $request->validate(['payment' => ['required', 'string', 'regex:/^(customer|supplier|tax|transfer|customer_invoice|supplier_invoice|expense):[0-9]+$/']]);
        $transfer = $this->matchSelection($transaction, $data['payment'], $request->user()->id, 'manual', $poster, $accounting);

        return back()->with('toast', ['type' => 'success', 'message' => $transfer ? __('Transfer between own accounts matched.') : __('Transaction reconciled.')]);
    }

    public function auto(Request $request, BankAccount $account, JournalPoster $poster, AccountingSynchronizer $accounting): RedirectResponse
    {
        $data = $request->validate(['minimum_confidence' => ['nullable', 'integer', 'between:80,100']]);
        $minimumConfidence = (int) ($data['minimum_confidence'] ?? 95);
        $matched = 0;
        $reviewed = 0;
        $account->transactions()->where('status', 'pending')->orderBy('booked_on')->each(function ($transaction) use ($request, $poster, $accounting, $minimumConfidence, &$matched, &$reviewed) {
            $candidates = $this->candidates($transaction, true);
            $candidate = $this->transactionAnalyzer->automaticCandidate($candidates, $minimumConfidence);
            if ($candidate) {
                $this->matchSelection($transaction, $candidate['value'], $request->user()->id, 'automatic', $poster, $accounting);
                $matched++;
            } elseif ($candidates !== []) {
                $reviewed++;
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __(':count transactions reconciled automatically; :reviewed left for review.', ['count' => $matched, 'reviewed' => $reviewed])]);
    }

    public function undo(Request $request, BankReconciliation $reconciliation, JournalPoster $poster): RedirectResponse
    {
        DB::transaction(function () use ($request, $reconciliation, $poster) {
            $reconciliation->load([
                'customerPayment.document',
                'customerPayment.accountingEntry.lines.account:id,code',
                'supplierPayment.expense',
                'supplierPayment.purchaseDocument.creditNotes',
                'supplierPayment.accountingEntry.lines.account:id,code',
            ]);
            if ($reconciliation->journalEntry) {
                $this->reverseEntry($reconciliation->journalEntry, $request->user()->id, $poster);
            }
            $transactionId = $reconciliation->bank_transaction_id;
            $generatedPayment = collect([$reconciliation->customerPayment, $reconciliation->supplierPayment])
                ->first(fn ($payment) => (int) $payment?->origin_bank_transaction_id === (int) $transactionId);
            if ($generatedPayment?->accountingEntry) {
                $this->reverseEntry($generatedPayment->accountingEntry, $request->user()->id, $poster);
            }
            $reconciliation->transaction()->update(['status' => 'pending']);
            $reconciliation->delete();
            if ($generatedPayment) {
                $customerDocument = $generatedPayment instanceof SalesDocumentPayment ? $generatedPayment->document : null;
                $expense = $generatedPayment instanceof SupplierPayment ? $generatedPayment->expense : null;
                $supplierDocument = $generatedPayment instanceof SupplierPayment ? $generatedPayment->purchaseDocument : null;
                $generatedPayment->delete();
                $this->refreshDocumentPaymentStatus($customerDocument, $expense, $supplierDocument);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Reconciliation removed.')]);
    }

    public function postTransaction(Request $request, BankTransaction $transaction, JournalPoster $poster, LedgerAccountResolver $accounts): RedirectResponse
    {
        abort_unless($transaction->status === 'pending', 422);
        if (! $request->has('allocations') && $request->filled('counterpart_account')) {
            $request->merge(['allocations' => [['account_code' => $request->input('counterpart_account'), 'amount' => abs((float) $transaction->amount)]]]);
        }
        $data = $request->validate([
            'allocations' => ['required', 'array', 'min:1', 'max:20'],
            'allocations.*.account_code' => ['required', 'string', 'max:12'],
            'allocations.*.account_name' => ['nullable', 'string', 'max:255'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);
        $transaction->load('account.ledgerAccount');
        $bankCode = $transaction->account->ledgerAccount?->code ?? '572';
        $amount = abs((float) $transaction->amount);
        $allocated = round((float) collect($data['allocations'])->sum('amount'), 2);
        if (abs($allocated - $amount) > .005) {
            throw ValidationException::withMessages(['allocations' => __('The allocation total must exactly match the bank movement amount.')]);
        }
        $incoming = (float) $transaction->amount > 0;
        DB::transaction(function () use ($request, $transaction, $poster, $accounts, $data, $bankCode, $amount, $incoming) {
            $allocations = collect($data['allocations'])->values()->map(function (array $allocation, int $index) use ($accounts, $bankCode) {
                $account = $accounts->resolveOrCreate($allocation['account_code'], $allocation['account_name'] ?? null, "allocations.{$index}.account_code");
                abort_if($account->code === $bankCode, 422, __('Select a different counterpart account.'));

                return ['account' => $account, 'amount' => round((float) $allocation['amount'], 2)];
            });
            $counterpartLines = $allocations->map(fn (array $allocation) => [
                'account_code' => $allocation['account']->code,
                'description' => $allocation['account']->name,
                'debit' => $incoming ? 0 : $allocation['amount'],
                'credit' => $incoming ? $allocation['amount'] : 0,
            ])->all();
            $bankLine = ['account_code' => $bankCode, 'debit' => $incoming ? $amount : 0, 'credit' => $incoming ? 0 : $amount];
            $lines = $incoming ? [$bankLine, ...$counterpartLines] : [...$counterpartLines, $bankLine];
            $posting = BankTransactionPosting::create([
                'bank_transaction_id' => $transaction->id,
                'counterpart_account_id' => $allocations->count() === 1 ? $allocations->first()['account']->id : null,
                'allocation_data' => $allocations->map(fn (array $allocation) => ['account_id' => $allocation['account']->id, 'code' => $allocation['account']->code, 'name' => $allocation['account']->name, 'amount' => $allocation['amount']])->all(),
                'posted_by' => $request->user()->id,
            ]);
            $entry = $poster->post($transaction->booked_on->toDateString(), $transaction->description, $lines, $request->user()->id, 'bank_posting', $posting, 'bank_posting');
            $posting->update(['journal_entry_id' => $entry->id]);
            $transaction->update(['status' => 'accounted']);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Bank movement posted to accounting.')]);
    }

    public function bulkPostTransactions(Request $request, BankAccount $account, BankTransactionPoster $poster, LedgerAccountResolver $accounts): RedirectResponse
    {
        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1', 'max:100'],
            'transaction_ids.*' => ['required', 'integer', 'distinct'],
            'account_code' => ['required', 'string', 'max:12'],
            'account_name' => ['nullable', 'string', 'max:255'],
        ]);

        $posted = DB::transaction(function () use ($account, $accounts, $data, $poster, $request): int {
            $transactions = BankTransaction::where('bank_account_id', $account->id)
                ->whereIn('id', $data['transaction_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($transactions->count() !== count($data['transaction_ids']) || $transactions->contains(fn (BankTransaction $transaction) => $transaction->status !== 'pending')) {
                throw ValidationException::withMessages(['transaction_ids' => __('One or more selected movements no longer belong to this account or are no longer pending.')]);
            }

            $counterpart = $accounts->resolveOrCreate($data['account_code'], $data['account_name'] ?? null, 'account_code');
            $bankCode = $account->ledgerAccount?->code ?? '572';
            if ($counterpart->code === $bankCode) {
                throw ValidationException::withMessages(['account_code' => __('Select a different counterpart account.')]);
            }

            $transactions->each(fn (BankTransaction $transaction) => $poster->post($transaction, $counterpart, $request->user()->id));

            return $transactions->count();
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Posted :count bank movements to accounting.', ['count' => $posted])]);
    }

    public function reversePosting(Request $request, BankTransactionPosting $posting, BankTransactionPoster $transactionPoster): RedirectResponse
    {
        $transactionPoster->reverse($posting, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Bank accounting posting reversed.')]);
    }

    public function reverseTransfer(Request $request, BankTransfer $transfer, JournalPoster $poster): RedirectResponse
    {
        abort_if($transfer->reversed_at, 422);
        $transfer->load('journalEntry.lines.account:id,code');
        $lines = $transfer->journalEntry->lines->map(fn ($line) => ['account_code' => $line->account->code, 'debit' => $line->credit, 'credit' => $line->debit])->all();
        DB::transaction(function () use ($request, $transfer, $poster, $lines) {
            $entry = $poster->post(now()->toDateString(), __('Reversal of :entry', ['entry' => $transfer->journalEntry->number]), $lines, $request->user()->id, 'reversal', null, 'reversal', $transfer->journalEntry);
            $transfer->update(['reversal_entry_id' => $entry->id, 'reversed_at' => now()]);
            BankTransaction::whereIn('id', [$transfer->outgoing_transaction_id, $transfer->incoming_transaction_id])->update(['status' => 'pending']);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Bank transfer match reversed.')]);
    }

    private function candidates(BankTransaction $transaction, bool $automatic = false, ?array $pool = null): array
    {
        $from = $transaction->booked_on->copy()->subDays(5);
        $to = $transaction->booked_on->copy()->addDays(5);
        $amount = abs((float) $transaction->amount);
        $inbound = (float) $transaction->amount > 0;
        $paymentSource = $pool
            ? ($inbound ? $pool['customerPayments'] : $pool['supplierPayments'])->filter(fn ($payment) => round((float) $payment->amount, 2) === round($amount, 2) && $payment->paid_at->betweenIncluded($from, $to))
            : ($inbound
                ? SalesDocumentPayment::with('document:id,number')->whereDoesntHave('reconciliation')->where('amount', $amount)->whereBetween('paid_at', [$from, $to])->get()
                : SupplierPayment::with(['expense:id,number', 'purchaseDocument:id,number'])->whereDoesntHave('reconciliation')->where('amount', $amount)->whereBetween('paid_at', [$from, $to])->get());
        $payments = $paymentSource->map(fn ($payment) => ['type' => $inbound ? 'customer' : 'supplier', 'payment' => $payment, 'number' => $inbound ? $payment->document?->number : ($payment->expense?->number ?? $payment->purchaseDocument?->number), 'date' => $payment->paid_at]);
        $taxSource = $pool
            ? $pool['taxPayments']->filter(fn ($payment) => round((float) $payment->amount, 2) === round($amount, 2) && $payment->paid_on->betweenIncluded($from, $to) && ($inbound ? (float) $payment->taxReturn->result < 0 : (float) $payment->taxReturn->result > 0))
            : TaxReturnPayment::with('taxReturn:id,year,quarter,result')->whereDoesntHave('reconciliation')->where('amount', $amount)->whereBetween('paid_on', [$from, $to])->whereHas('taxReturn', fn ($query) => $query->where('result', $inbound ? '<' : '>', 0))->get();
        $tax = $taxSource->map(fn ($payment) => ['type' => 'tax', 'payment' => $payment, 'number' => 'VAT '.$payment->taxReturn->year.' Q'.$payment->taxReturn->quarter, 'date' => $payment->paid_on]);
        $transferSource = $pool
            ? $pool['transfers']->filter(fn ($other) => $other->bank_account_id !== $transaction->bank_account_id && round((float) $other->amount, 2) === round(-(float) $transaction->amount, 2) && $other->booked_on->betweenIncluded($from, $to))
            : BankTransaction::with('account:id,name')->where('status', 'pending')->where('bank_account_id', '!=', $transaction->bank_account_id)->where('amount', -(float) $transaction->amount)->whereBetween('booked_on', [$from, $to])->get();
        $transfers = $transferSource->map(function ($other) use ($transaction) {
            $referenceMatch = filled($transaction->reference) && filled($other->reference) && Str::lower($transaction->reference) === Str::lower($other->reference);

            return ['value' => 'transfer:'.$other->id, 'label' => __('Own transfer').' · '.$other->account->name.' · '.$other->booked_on->format('d/m/Y').' · '.number_format(abs((float) $other->amount), 2, ',', '.').' EUR', 'reference_match' => $referenceMatch, 'confidence' => $referenceMatch ? 100 : 80];
        });

        $recorded = $payments->concat($tax)->map(function ($item) use ($transaction) {
            $payment = $item['payment'];

            $referenceMatch = filled($transaction->reference) && filled($payment->reference) && str_contains(Str::lower($payment->reference), Str::lower($transaction->reference));

            return ['value' => $item['type'].':'.$payment->id, 'label' => $item['number'].' · '.$item['date']->format('d/m/Y').' · '.number_format((float) $payment->amount, 2, ',', '.').' EUR', 'reference_match' => $referenceMatch, 'creates_payment' => false, 'confidence' => $referenceMatch ? 98 : 82];
        })->concat($transfers->map(fn ($transfer) => [...$transfer, 'creates_payment' => false]));
        $direct = $this->openDocumentCandidates($transaction, $pool);

        return $recorded
            ->concat($automatic ? $direct->where('reference_match', true) : $direct)
            ->sortByDesc('confidence')
            ->values()
            ->all();
    }

    private function openDocumentCandidates(BankTransaction $transaction, ?array $pool = null): Collection
    {
        $amount = abs((float) $transaction->amount);
        $inbound = (float) $transaction->amount > 0;

        if ($inbound) {
            return ($pool ? $pool['salesDocuments'] : SalesDocument::with('customer:id,first_name,last_name,company_name')
                ->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'overdue'])->latest('issue_date')->limit(100)->get())
                ->filter(function (SalesDocument $document) use ($transaction, $amount): bool {
                    $outstanding = $this->balances->receivable($document);

                    return $outstanding >= $amount && ($outstanding === round($amount, 2) || $this->documentReferenceMatches($transaction, $document->number));
                })
                ->map(function (SalesDocument $document) use ($transaction, $amount): array {
                    $party = $document->customer?->company_name ?: trim(($document->customer?->first_name ?? '').' '.($document->customer?->last_name ?? ''));
                    $referenceMatch = $this->documentReferenceMatches($transaction, $document->number);
                    $exactAmount = $this->balances->receivable($document) === round($amount, 2);

                    return [
                        'value' => 'customer_invoice:'.$document->id,
                        'label' => __('Create receipt').' · '.$document->number.' · '.($party ?: __('Customer')).' · '.number_format($this->balances->receivable($document), 2, ',', '.').' EUR',
                        'reference_match' => $referenceMatch,
                        'creates_payment' => true,
                        'confidence' => $referenceMatch ? ($exactAmount ? 99 : 92) : 70,
                    ];
                });
        }

        $invoices = ($pool ? $pool['purchaseDocuments'] : PurchaseDocument::with('supplier:id,legal_name,trade_name')
            ->withSum('creditNotes as credited_total', 'amount')
            ->where('type', 'supplier_invoice')
            ->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])
            ->where(fn ($query) => $query->whereNull('matching_status')->orWhereNot('matching_status', 'review_required'))
            ->latest('issue_date')
            ->limit(100)
            ->get())
            ->filter(function (PurchaseDocument $document) use ($transaction, $amount): bool {
                $outstanding = $this->balances->purchase($document);

                return $outstanding >= $amount && ($outstanding === round($amount, 2) || $this->documentReferenceMatches($transaction, $document->supplier_number, $document->number));
            })
            ->map(function (PurchaseDocument $document) use ($transaction, $amount): array {
                $party = $document->supplier?->trade_name ?: $document->supplier?->legal_name;
                $outstanding = $this->balances->purchase($document);
                $referenceMatch = $this->documentReferenceMatches($transaction, $document->supplier_number, $document->number);

                return [
                    'value' => 'supplier_invoice:'.$document->id,
                    'label' => __('Create payment').' · '.($document->supplier_number ?: $document->number).' · '.($party ?: __('Supplier')).' · '.number_format($outstanding, 2, ',', '.').' EUR',
                    'reference_match' => $referenceMatch,
                    'creates_payment' => true,
                    'confidence' => $referenceMatch ? ($outstanding === round($amount, 2) ? 99 : 92) : 70,
                ];
            });
        $expenses = ($pool ? $pool['expenses'] : Expense::with('supplier:id,legal_name,trade_name')
            ->whereIn('status', ['pending', 'partial', 'overdue'])->latest('expense_date')->limit(100)->get())
            ->filter(function (Expense $expense) use ($transaction, $amount): bool {
                $outstanding = $this->balances->expense($expense);

                return $outstanding >= $amount && ($outstanding === round($amount, 2) || $this->documentReferenceMatches($transaction, $expense->document_number, $expense->number));
            })
            ->map(function (Expense $expense) use ($transaction, $amount): array {
                $party = $expense->supplier?->trade_name ?: ($expense->supplier?->legal_name ?: $expense->merchant_name);
                $outstanding = $this->balances->expense($expense);
                $referenceMatch = $this->documentReferenceMatches($transaction, $expense->document_number, $expense->number);

                return [
                    'value' => 'expense:'.$expense->id,
                    'label' => __('Create payment').' · '.($expense->document_number ?: $expense->number).' · '.($party ?: __('Expense')).' · '.number_format($outstanding, 2, ',', '.').' EUR',
                    'reference_match' => $referenceMatch,
                    'creates_payment' => true,
                    'confidence' => $referenceMatch ? ($outstanding === round($amount, 2) ? 99 : 92) : 70,
                ];
            });

        return $invoices->concat($expenses);
    }

    private function candidatePool(Collection $transactions): array
    {
        $pending = $transactions->where('status', 'pending');
        if ($pending->isEmpty()) {
            return [];
        }
        $from = $pending->min('booked_on')->copy()->subDays(5);
        $to = $pending->max('booked_on')->copy()->addDays(5);

        return [
            'customerPayments' => SalesDocumentPayment::with('document:id,number')->whereDoesntHave('reconciliation')->whereBetween('paid_at', [$from, $to])->get(),
            'supplierPayments' => SupplierPayment::with(['expense:id,number', 'purchaseDocument:id,number'])->whereDoesntHave('reconciliation')->whereBetween('paid_at', [$from, $to])->get(),
            'taxPayments' => TaxReturnPayment::with('taxReturn:id,year,quarter,result')->whereDoesntHave('reconciliation')->whereBetween('paid_on', [$from, $to])->get(),
            'transfers' => BankTransaction::with('account:id,name')->where('status', 'pending')->whereBetween('booked_on', [$from, $to])->get(),
            'salesDocuments' => SalesDocument::with('customer:id,first_name,last_name,company_name')->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'overdue'])->latest('issue_date')->limit(100)->get(),
            'purchaseDocuments' => PurchaseDocument::with('supplier:id,legal_name,trade_name')->withSum('creditNotes as credited_total', 'amount')->where('type', 'supplier_invoice')->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])->where(fn ($query) => $query->whereNull('matching_status')->orWhereNot('matching_status', 'review_required'))->latest('issue_date')->limit(100)->get(),
            'expenses' => Expense::with('supplier:id,legal_name,trade_name')->whereIn('status', ['pending', 'partial', 'overdue'])->latest('expense_date')->limit(100)->get(),
        ];
    }

    private function documentReferenceMatches(BankTransaction $transaction, ?string ...$references): bool
    {
        $haystack = Str::lower((string) $transaction->reference.' '.(string) $transaction->description);
        $normalizedHaystack = preg_replace('/[^a-z0-9]/i', '', $haystack);

        foreach (array_filter($references) as $reference) {
            $normalizedReference = preg_replace('/[^a-z0-9]/i', '', Str::lower($reference));
            if (strlen($normalizedReference) >= 4 && str_contains($normalizedHaystack, $normalizedReference)) {
                return true;
            }
        }

        return false;
    }

    private function matchSelection(BankTransaction $transaction, string $selection, int $userId, string $method, JournalPoster $poster, AccountingSynchronizer $accounting): bool
    {
        return DB::transaction(function () use ($transaction, $selection, $userId, $method, $poster, $accounting): bool {
            $transaction = BankTransaction::lockForUpdate()->findOrFail($transaction->id);
            abort_unless($transaction->status === 'pending', 422);
            [$type, $id] = explode(':', $selection);
            if ($type === 'transfer') {
                $this->storeTransfer($transaction, BankTransaction::findOrFail($id), $userId, $poster);

                return true;
            }
            if (in_array($type, ['customer_invoice', 'supplier_invoice', 'expense'], true)) {
                [$type, $id] = $this->createPaymentFromTransaction($transaction, $type, (int) $id, $userId, $accounting);
            }
            $payment = match ($type) {
                'customer' => SalesDocumentPayment::findOrFail($id),
                'supplier' => SupplierPayment::findOrFail($id),
                'tax' => TaxReturnPayment::with('taxReturn')->findOrFail($id),
            };
            $inbound = $type === 'customer' || ($type === 'tax' && (float) $payment->taxReturn->result < 0);
            abort_if($payment->reconciliation()->exists() || abs((float) $transaction->amount) !== (float) $payment->amount || ($inbound !== ((float) $transaction->amount > 0)), 422, __('The payment does not match this bank transaction.'));
            $this->storeReconciliation($transaction, $type, $payment->id, $userId, $method, $poster);

            return false;
        });
    }

    private function createPaymentFromTransaction(BankTransaction $transaction, string $type, int $documentId, int $userId, AccountingSynchronizer $accounting): array
    {
        $amount = abs((float) $transaction->amount);
        if ($type === 'customer_invoice') {
            abort_unless((float) $transaction->amount > 0, 422, __('Customer receipts require an incoming bank movement.'));
            $document = SalesDocument::lockForUpdate()->findOrFail($documentId);
            $outstanding = $this->balances->receivable($document);
            $this->ensureOutstandingAmount($amount, $outstanding);
            $payment = $document->payments()->create(['created_by' => $userId, 'origin_bank_transaction_id' => $transaction->id, 'paid_at' => $transaction->booked_on, 'amount' => $amount, 'method' => 'bank_transfer', 'reference' => $transaction->reference, 'notes' => __('Created from bank reconciliation.')]);
            $accounting->syncCustomerPayment($payment, $userId);
            $paid = (float) $document->payments()->sum('amount');
            $document->update(['paid_amount' => $paid, 'status' => $paid >= (float) $document->amount ? 'paid' : 'partial']);

            return ['customer', $payment->id];
        }

        abort_unless((float) $transaction->amount < 0, 422, __('Supplier payments require an outgoing bank movement.'));
        if ($type === 'supplier_invoice') {
            $document = PurchaseDocument::lockForUpdate()->findOrFail($documentId);
            abort_unless($document->type === 'supplier_invoice' && $document->matching_status !== 'review_required', 422, __('This supplier invoice cannot be paid.'));
            $creditAmount = abs((float) $document->creditNotes()->sum('amount'));
            $outstanding = $this->balances->purchase($document);
            $this->ensureOutstandingAmount($amount, $outstanding);
            $payment = SupplierPayment::create(['purchase_document_id' => $document->id, 'created_by' => $userId, 'origin_bank_transaction_id' => $transaction->id, 'paid_at' => $transaction->booked_on, 'amount' => $amount, 'method' => 'bank_transfer', 'reference' => $transaction->reference, 'notes' => __('Created from bank reconciliation.')]);
            $accounting->syncSupplierPayment($payment, $userId);
            $paid = (float) $document->payments()->sum('amount');
            $document->update(['paid_amount' => $paid, 'status' => $paid >= (float) $document->amount - $creditAmount ? 'paid' : 'partial']);

            return ['supplier', $payment->id];
        }

        $expense = Expense::lockForUpdate()->findOrFail($documentId);
        $outstanding = $this->balances->expense($expense);
        $this->ensureOutstandingAmount($amount, $outstanding);
        $payment = SupplierPayment::create(['expense_id' => $expense->id, 'created_by' => $userId, 'origin_bank_transaction_id' => $transaction->id, 'paid_at' => $transaction->booked_on, 'amount' => $amount, 'method' => 'bank_transfer', 'reference' => $transaction->reference, 'notes' => __('Created from bank reconciliation.')]);
        $accounting->syncSupplierPayment($payment, $userId);
        $paid = (float) $expense->payments()->sum('amount');
        $expense->update(['paid_amount' => $paid, 'status' => $paid >= (float) $expense->amount ? 'paid' : 'partial']);

        return ['supplier', $payment->id];
    }

    private function ensureOutstandingAmount(float $amount, float $outstanding): void
    {
        if ($outstanding <= 0 || $amount > $outstanding) {
            throw ValidationException::withMessages(['payment' => __('The bank amount exceeds the outstanding document balance.')]);
        }
    }

    private function refreshDocumentPaymentStatus(?SalesDocument $customerDocument, ?Expense $expense, ?PurchaseDocument $supplierDocument): void
    {
        if ($customerDocument) {
            $paid = (float) $customerDocument->payments()->sum('amount');
            $status = $paid >= (float) $customerDocument->amount ? 'paid' : ($paid > 0 ? 'partial' : ($customerDocument->due_date?->isPast() ? 'overdue' : 'issued'));
            $customerDocument->update(['paid_amount' => $paid, 'status' => $status]);

            return;
        }

        $payable = $expense ?? $supplierDocument;
        if (! $payable) {
            return;
        }
        $paid = (float) $payable->payments()->sum('amount');
        $creditAmount = $supplierDocument ? abs((float) $supplierDocument->creditNotes()->sum('amount')) : 0;
        $netAmount = (float) $payable->amount - $creditAmount;
        $status = $paid >= $netAmount ? 'paid' : ($paid > 0 ? 'partial' : ($payable->due_date?->isPast() ? 'overdue' : ($supplierDocument ? 'registered' : 'pending')));
        $payable->update(['paid_amount' => $paid, 'status' => $status]);
    }

    private function reverseEntry(JournalEntry $entry, int $userId, JournalPoster $poster): void
    {
        $entry->loadMissing('lines.account:id,code');
        $lines = $entry->lines->map(fn ($line) => ['account_code' => $line->account->code, 'debit' => $line->credit, 'credit' => $line->debit])->all();
        $poster->post(now()->toDateString(), __('Reversal of :entry', ['entry' => $entry->number]), $lines, $userId, 'reversal', null, 'reversal', $entry);
    }

    private function storeReconciliation(BankTransaction $transaction, string $type, int $paymentId, int $userId, string $method, JournalPoster $poster): void
    {
        DB::transaction(function () use ($transaction, $type, $paymentId, $userId, $method, $poster) {
            $transaction->load('account.ledgerAccount');
            $reconciliation = BankReconciliation::create(['bank_transaction_id' => $transaction->id, 'sales_document_payment_id' => $type === 'customer' ? $paymentId : null, 'supplier_payment_id' => $type === 'supplier' ? $paymentId : null, 'tax_return_payment_id' => $type === 'tax' ? $paymentId : null, 'reconciled_by' => $userId, 'method' => $method, 'reconciled_at' => now()]);
            $code = $transaction->account->ledgerAccount?->code;
            if ($code && $code !== '572') {
                $amount = abs((float) $transaction->amount);
                $inbound = (float) $transaction->amount > 0;
                $lines = $inbound ? [['account_code' => $code, 'debit' => $amount, 'credit' => 0], ['account_code' => '572', 'debit' => 0, 'credit' => $amount]] : [['account_code' => '572', 'debit' => $amount, 'credit' => 0], ['account_code' => $code, 'debit' => 0, 'credit' => $amount]];
                $entry = $poster->post($transaction->booked_on->toDateString(), __('Bank reconciliation :description', ['description' => $transaction->description]), $lines, $userId, 'bank_reclassification', $reconciliation, 'bank_reclassification');
                $reconciliation->update(['journal_entry_id' => $entry->id]);
            } $transaction->update(['status' => 'reconciled']);
        });
    }

    private function bankControl(BankAccount $account): array
    {
        $latest = $account->statementImports()->whereNull('rolled_back_at')->whereNotNull('closing_balance')->latest('period_to')->latest('id')->first();
        $to = $latest?->period_to ?? now();
        $from = $latest?->period_from;
        $statementMovements = $from ? (float) $account->transactions()->whereBetween('booked_on', [$from, $to])->sum('amount') : (float) $account->transactions()->whereDate('booked_on', '<=', $to)->sum('amount');
        $calculatedStatement = $latest ? (float) $latest->opening_balance + $statementMovements : (float) $account->opening_balance + $statementMovements;
        $ledger = $account->ledger_account_id ? JournalLine::where('ledger_account_id', $account->ledger_account_id)->whereHas('entry', fn ($query) => $query->whereDate('entry_date', '<=', $to))->selectRaw('coalesce(sum(debit),0) - coalesce(sum(credit),0) balance')->value('balance') : 0;
        $expected = $latest ? (float) $latest->closing_balance : $calculatedStatement;

        return ['periodTo' => $to->toDateString(), 'statementBalance' => round($expected, 2), 'calculatedBankBalance' => round($calculatedStatement, 2), 'ledgerBalance' => round((float) $ledger, 2), 'statementDifference' => round($calculatedStatement - $expected, 2), 'accountingDifference' => round($expected - (float) $ledger, 2), 'matchesStatement' => abs($calculatedStatement - $expected) < 0.01, 'matchesAccounting' => abs($expected - (float) $ledger) < 0.01];
    }

    private function storeTransfer(BankTransaction $transaction, BankTransaction $other, int $userId, JournalPoster $poster): void
    {
        abort_if($other->status !== 'pending' || $other->bank_account_id === $transaction->bank_account_id || round((float) $transaction->amount + (float) $other->amount, 2) !== 0.0, 422, __('The bank movements do not form a valid own-account transfer.'));
        $outgoing = (float) $transaction->amount < 0 ? $transaction : $other;
        $incoming = $outgoing->is($transaction) ? $other : $transaction;
        $outgoing->load('account.ledgerAccount');
        $incoming->load('account.ledgerAccount');
        abort_if(! $outgoing->account->ledgerAccount || ! $incoming->account->ledgerAccount || $outgoing->account->ledger_account_id === $incoming->account->ledger_account_id, 422, __('Both bank accounts require different accounting subaccounts.'));
        DB::transaction(function () use ($outgoing, $incoming, $userId, $poster) {
            $transfer = BankTransfer::create(['outgoing_transaction_id' => $outgoing->id, 'incoming_transaction_id' => $incoming->id, 'created_by' => $userId]);
            $amount = abs((float) $outgoing->amount);
            $entry = $poster->post($outgoing->booked_on->max($incoming->booked_on)->toDateString(), __('Transfer from :from to :to', ['from' => $outgoing->account->name, 'to' => $incoming->account->name]), [['account_code' => $incoming->account->ledgerAccount->code, 'debit' => $amount, 'credit' => 0], ['account_code' => $outgoing->account->ledgerAccount->code, 'debit' => 0, 'credit' => $amount]], $userId, 'bank_transfer', $transfer, 'bank_transfer');
            $transfer->update(['journal_entry_id' => $entry->id]);
            BankTransaction::whereIn('id', [$outgoing->id, $incoming->id])->update(['status' => 'reconciled']);
        });
    }
}
