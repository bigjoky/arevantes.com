<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
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
use App\Services\Banking\StatementImporter;
use App\Services\Finance\OutstandingBalanceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BankingController extends Controller
{
    public function __construct(private OutstandingBalanceCalculator $balances) {}

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
            ->withQueryString()
            ->through(fn ($transaction) => [...$transaction->toArray(), 'transfer' => $transaction->outgoingTransfer ?? $transaction->incomingTransfer, 'candidates' => $transaction->status === 'pending' ? $this->candidates($transaction) : []]);

        $summary = $account ? [
            'pending' => $account->transactions()->where('status', 'pending')->count(),
            'reconciled' => $account->transactions()->whereIn('status', ['reconciled', 'accounted'])->count(),
            'pendingAmount' => round((float) $account->transactions()->where('status', 'pending')->sum('amount'), 2),
        ] : ['pending' => 0, 'reconciled' => 0, 'pendingAmount' => 0];

        return Inertia::render('banking/index', ['accounts' => $accounts, 'selectedAccount' => $account, 'transactions' => $transactions, 'imports' => $account ? $account->statementImports()->latest()->limit(12)->get() : [], 'control' => $account ? $this->bankControl($account) : null, 'ledgerAccounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->whereNotIn('code', ['430', '400', '410', '472', '477'])->orderBy('code')->get(['id', 'code', 'name', 'type']), 'summary' => $summary, 'filters' => $filters]);
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

    public function import(Request $request, BankAccount $account, StatementImporter $importer): RedirectResponse
    {
        $data = $request->validate(['statement' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/octet-stream', 'max:10240']]);
        $file = $data['statement'];
        $result = $importer->import($account, $file);
        $metadata = $result['metadata'] ?? [];
        BankStatementImport::create(['bank_account_id' => $account->id, 'imported_by' => $request->user()->id, 'filename' => $file->getClientOriginalName(), 'file_hash' => hash_file('sha256', $file->getRealPath()), 'format' => $result['format'], 'period_from' => $metadata['period_from'] ?? null, 'period_to' => $metadata['period_to'] ?? null, 'opening_balance' => $metadata['opening_balance'] ?? null, 'closing_balance' => $metadata['closing_balance'] ?? null, 'created_transactions' => $result['created'], 'duplicate_transactions' => $result['duplicates'], 'control_valid' => $metadata['control_valid'] ?? null, 'control_data' => $metadata['control_data'] ?? null]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Imported :created transactions; :duplicates were already imported.', ['created' => $result['created'], 'duplicates' => $result['duplicates']])]);
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
        $matched = 0;
        $account->transactions()->where('status', 'pending')->orderBy('booked_on')->each(function ($transaction) use ($request, $poster, $accounting, &$matched) {
            $candidates = $this->candidates($transaction, true);
            if (count($candidates) === 1) {
                $this->matchSelection($transaction, $candidates[0]['value'], $request->user()->id, 'automatic', $poster, $accounting);
                $matched++;
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __(':count transactions reconciled automatically.', ['count' => $matched])]);
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

    public function postTransaction(Request $request, BankTransaction $transaction, JournalPoster $poster): RedirectResponse
    {
        abort_unless($transaction->status === 'pending', 422);
        $data = $request->validate(['counterpart_account' => ['required', 'exists:ledger_accounts,code']]);
        $transaction->load('account.ledgerAccount');
        $bankCode = $transaction->account->ledgerAccount?->code ?? '572';
        abort_if($data['counterpart_account'] === $bankCode, 422, __('Select a different counterpart account.'));
        $amount = abs((float) $transaction->amount);
        $incoming = (float) $transaction->amount > 0;
        $lines = $incoming ? [['account_code' => $bankCode, 'debit' => $amount, 'credit' => 0], ['account_code' => $data['counterpart_account'], 'debit' => 0, 'credit' => $amount]] : [['account_code' => $data['counterpart_account'], 'debit' => $amount, 'credit' => 0], ['account_code' => $bankCode, 'debit' => 0, 'credit' => $amount]];
        DB::transaction(function () use ($request, $transaction, $poster, $data, $lines) {
            $posting = BankTransactionPosting::create(['bank_transaction_id' => $transaction->id, 'counterpart_account_id' => LedgerAccount::where('code', $data['counterpart_account'])->value('id'), 'posted_by' => $request->user()->id]);
            $entry = $poster->post($transaction->booked_on->toDateString(), $transaction->description, $lines, $request->user()->id, 'bank_posting', $posting, 'bank_posting');
            $posting->update(['journal_entry_id' => $entry->id]);
            $transaction->update(['status' => 'accounted']);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Bank movement posted to accounting.')]);
    }

    public function reversePosting(Request $request, BankTransactionPosting $posting, JournalPoster $poster): RedirectResponse
    {
        abort_if($posting->reversed_at, 422);
        $posting->load('journalEntry.lines.account:id,code');
        $lines = $posting->journalEntry->lines->map(fn ($line) => ['account_code' => $line->account->code, 'debit' => $line->credit, 'credit' => $line->debit])->all();
        DB::transaction(function () use ($request, $posting, $poster, $lines) {
            $entry = $poster->post(now()->toDateString(), __('Reversal of :entry', ['entry' => $posting->journalEntry->number]), $lines, $request->user()->id, 'reversal', null, 'reversal', $posting->journalEntry);
            $posting->update(['reversal_entry_id' => $entry->id, 'reversed_at' => now()]);
            $posting->transaction()->update(['status' => 'pending']);
        });

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

    private function candidates(BankTransaction $transaction, bool $automatic = false): array
    {
        $from = $transaction->booked_on->copy()->subDays(5);
        $to = $transaction->booked_on->copy()->addDays(5);
        $amount = abs((float) $transaction->amount);
        $inbound = (float) $transaction->amount > 0;
        $payments = $inbound
            ? SalesDocumentPayment::with('document:id,number')->whereDoesntHave('reconciliation')->where('amount', $amount)->whereBetween('paid_at', [$from, $to])->get()->map(fn ($payment) => ['type' => 'customer', 'payment' => $payment, 'number' => $payment->document?->number, 'date' => $payment->paid_at])
            : SupplierPayment::with(['expense:id,number', 'purchaseDocument:id,number'])->whereDoesntHave('reconciliation')->where('amount', $amount)->whereBetween('paid_at', [$from, $to])->get()->map(fn ($payment) => ['type' => 'supplier', 'payment' => $payment, 'number' => $payment->expense?->number ?? $payment->purchaseDocument?->number, 'date' => $payment->paid_at]);
        $tax = TaxReturnPayment::with('taxReturn:id,year,quarter,result')->whereDoesntHave('reconciliation')->where('amount', $amount)->whereBetween('paid_on', [$from, $to])->whereHas('taxReturn', fn ($query) => $query->where('result', $inbound ? '<' : '>', 0))->get()->map(fn ($payment) => ['type' => 'tax', 'payment' => $payment, 'number' => 'VAT '.$payment->taxReturn->year.' Q'.$payment->taxReturn->quarter, 'date' => $payment->paid_on]);
        $transfers = BankTransaction::with('account:id,name')->where('status', 'pending')->where('bank_account_id', '!=', $transaction->bank_account_id)->where('amount', -(float) $transaction->amount)->whereBetween('booked_on', [$from, $to])->get()->map(function ($other) use ($transaction) {
            $referenceMatch = filled($transaction->reference) && filled($other->reference) && Str::lower($transaction->reference) === Str::lower($other->reference);

            return ['value' => 'transfer:'.$other->id, 'label' => __('Own transfer').' · '.$other->account->name.' · '.$other->booked_on->format('d/m/Y').' · '.number_format(abs((float) $other->amount), 2, ',', '.').' EUR', 'reference_match' => $referenceMatch, 'confidence' => $referenceMatch ? 100 : 80];
        });

        $recorded = $payments->concat($tax)->map(function ($item) use ($transaction) {
            $payment = $item['payment'];

            $referenceMatch = filled($transaction->reference) && filled($payment->reference) && str_contains(Str::lower($payment->reference), Str::lower($transaction->reference));

            return ['value' => $item['type'].':'.$payment->id, 'label' => $item['number'].' · '.$item['date']->format('d/m/Y').' · '.number_format((float) $payment->amount, 2, ',', '.').' EUR', 'reference_match' => $referenceMatch, 'creates_payment' => false, 'confidence' => $referenceMatch ? 98 : 82];
        })->concat($transfers->map(fn ($transfer) => [...$transfer, 'creates_payment' => false]));
        $direct = $this->openDocumentCandidates($transaction);

        return $recorded
            ->concat($automatic ? $direct->where('reference_match', true) : $direct)
            ->sortByDesc('confidence')
            ->values()
            ->all();
    }

    private function openDocumentCandidates(BankTransaction $transaction): Collection
    {
        $amount = abs((float) $transaction->amount);
        $inbound = (float) $transaction->amount > 0;

        if ($inbound) {
            return SalesDocument::with('customer:id,first_name,last_name,company_name')
                ->where('type', 'invoice')
                ->whereIn('status', ['issued', 'partial', 'overdue'])
                ->latest('issue_date')
                ->limit(100)
                ->get()
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

        $invoices = PurchaseDocument::with('supplier:id,legal_name,trade_name')
            ->withSum('creditNotes as credited_total', 'amount')
            ->where('type', 'supplier_invoice')
            ->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])
            ->where(fn ($query) => $query->whereNull('matching_status')->orWhereNot('matching_status', 'review_required'))
            ->latest('issue_date')
            ->limit(100)
            ->get()
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
        $expenses = Expense::with('supplier:id,legal_name,trade_name')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->latest('expense_date')
            ->limit(100)
            ->get()
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
        $latest = $account->statementImports()->whereNotNull('closing_balance')->latest('period_to')->latest('id')->first();
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
