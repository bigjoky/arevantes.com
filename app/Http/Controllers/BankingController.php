<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionPosting;
use App\Models\BankTransfer;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\TaxReturnPayment;
use App\Services\Accounting\JournalPoster;
use App\Services\Banking\StatementImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BankingController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = BankAccount::withSum('transactions as transaction_total', 'amount')->orderByDesc('is_active')->orderBy('name')->get()->map(fn ($account) => [...$account->toArray(), 'balance' => round((float) $account->opening_balance + (float) ($account->transaction_total ?? 0), 2)]);
        $account = BankAccount::with('ledgerAccount')->find($request->integer('account')) ?: BankAccount::with('ledgerAccount')->where('is_active', true)->first();
        $transactions = $account ? $account->transactions()->with(['reconciliation', 'posting.counterpart:id,code,name', 'outgoingTransfer', 'incomingTransfer'])->latest('booked_on')->latest('id')->limit(250)->get()->map(fn ($transaction) => [...$transaction->toArray(), 'transfer' => $transaction->outgoingTransfer ?? $transaction->incomingTransfer, 'candidates' => $transaction->status === 'pending' ? $this->candidates($transaction) : []]) : collect();

        return Inertia::render('banking/index', ['accounts' => $accounts, 'selectedAccount' => $account, 'transactions' => $transactions, 'imports' => $account ? $account->statementImports()->latest()->limit(12)->get() : [], 'control' => $account ? $this->bankControl($account) : null, 'ledgerAccounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->whereNotIn('code', ['430', '400', '410', '472', '477'])->orderBy('code')->get(['id', 'code', 'name', 'type']), 'summary' => ['pending' => $transactions->where('status', 'pending')->count(), 'reconciled' => $transactions->whereIn('status', ['reconciled', 'accounted'])->count(), 'pendingAmount' => round((float) $transactions->where('status', 'pending')->sum('amount'), 2)]]);
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

        return back()->with('toast', ['type' => 'success', 'message' => __('Imported :created transactions; :duplicates duplicates skipped.', ['created' => $result['created'], 'duplicates' => $result['duplicates']])]);
    }

    public function reconcile(Request $request, BankTransaction $transaction, JournalPoster $poster): RedirectResponse
    {
        abort_unless($transaction->status === 'pending', 422);
        $data = $request->validate(['payment' => ['required', 'string', 'regex:/^(customer|supplier|tax|transfer):[0-9]+$/']]);
        [$type, $id] = explode(':', $data['payment']);
        if ($type === 'transfer') {
            $this->storeTransfer($transaction, BankTransaction::findOrFail($id), $request->user()->id, $poster);

            return back()->with('toast', ['type' => 'success', 'message' => __('Transfer between own accounts matched.')]);
        }
        $payment = match ($type) {
            'customer' => SalesDocumentPayment::findOrFail($id), 'supplier' => SupplierPayment::findOrFail($id), 'tax' => TaxReturnPayment::with('taxReturn')->findOrFail($id)
        };
        $inbound = $type === 'customer' || ($type === 'tax' && (float) $payment->taxReturn->result < 0);
        abort_if($payment->reconciliation()->exists() || abs((float) $transaction->amount) !== (float) $payment->amount || ($inbound !== ((float) $transaction->amount > 0)), 422, __('The payment does not match this bank transaction.'));
        $this->storeReconciliation($transaction, $type, $payment->id, $request->user()->id, 'manual', $poster);

        return back()->with('toast', ['type' => 'success', 'message' => __('Transaction reconciled.')]);
    }

    public function auto(Request $request, BankAccount $account, JournalPoster $poster): RedirectResponse
    {
        $matched = 0;
        $account->transactions()->where('status', 'pending')->orderBy('booked_on')->each(function ($transaction) use ($request, $poster, &$matched) {
            $candidates = $this->candidates($transaction);
            if (count($candidates) === 1) {
                [$type, $id] = explode(':', $candidates[0]['value']);
                $type === 'transfer' ? $this->storeTransfer($transaction, BankTransaction::findOrFail($id), $request->user()->id, $poster) : $this->storeReconciliation($transaction, $type, (int) $id, $request->user()->id, 'automatic', $poster);
                $matched++;
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __(':count transactions reconciled automatically.', ['count' => $matched])]);
    }

    public function undo(Request $request, BankReconciliation $reconciliation, JournalPoster $poster): RedirectResponse
    {
        DB::transaction(function () use ($request, $reconciliation, $poster) {
            if ($reconciliation->journalEntry) {
                $reconciliation->journalEntry->load('lines.account:id,code');
                $lines = $reconciliation->journalEntry->lines->map(fn ($line) => ['account_code' => $line->account->code, 'debit' => $line->credit, 'credit' => $line->debit])->all();
                $poster->post(now()->toDateString(), __('Reversal of :entry', ['entry' => $reconciliation->journalEntry->number]), $lines, $request->user()->id, 'reversal', null, 'reversal', $reconciliation->journalEntry);
            } $reconciliation->transaction()->update(['status' => 'pending']);
            $reconciliation->delete();
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

    private function candidates(BankTransaction $transaction): array
    {
        $from = $transaction->booked_on->copy()->subDays(5);
        $to = $transaction->booked_on->copy()->addDays(5);
        $amount = abs((float) $transaction->amount);
        $inbound = (float) $transaction->amount > 0;
        $payments = $inbound
            ? SalesDocumentPayment::with('document:id,number')->whereDoesntHave('reconciliation')->where('amount', $amount)->whereBetween('paid_at', [$from, $to])->get()->map(fn ($payment) => ['type' => 'customer', 'payment' => $payment, 'number' => $payment->document?->number, 'date' => $payment->paid_at])
            : SupplierPayment::with(['expense:id,number', 'purchaseDocument:id,number'])->whereDoesntHave('reconciliation')->where('amount', $amount)->whereBetween('paid_at', [$from, $to])->get()->map(fn ($payment) => ['type' => 'supplier', 'payment' => $payment, 'number' => $payment->expense?->number ?? $payment->purchaseDocument?->number, 'date' => $payment->paid_at]);
        $tax = TaxReturnPayment::with('taxReturn:id,year,quarter,result')->whereDoesntHave('reconciliation')->where('amount', $amount)->whereBetween('paid_on', [$from, $to])->whereHas('taxReturn', fn ($query) => $query->where('result', $inbound ? '<' : '>', 0))->get()->map(fn ($payment) => ['type' => 'tax', 'payment' => $payment, 'number' => 'VAT '.$payment->taxReturn->year.' Q'.$payment->taxReturn->quarter, 'date' => $payment->paid_on]);
        $transfers = BankTransaction::with('account:id,name')->where('status', 'pending')->where('bank_account_id', '!=', $transaction->bank_account_id)->where('amount', -(float) $transaction->amount)->whereBetween('booked_on', [$from, $to])->get()->map(fn ($other) => ['value' => 'transfer:'.$other->id, 'label' => __('Own transfer').' · '.$other->account->name.' · '.$other->booked_on->format('d/m/Y').' · '.number_format(abs((float) $other->amount), 2, ',', '.').' EUR', 'reference_match' => filled($transaction->reference) && filled($other->reference) && Str::lower($transaction->reference) === Str::lower($other->reference)]);

        return $payments->concat($tax)->map(function ($item) use ($transaction) {
            $payment = $item['payment'];

            return ['value' => $item['type'].':'.$payment->id, 'label' => $item['number'].' · '.$item['date']->format('d/m/Y').' · '.number_format((float) $payment->amount, 2, ',', '.').' EUR', 'reference_match' => filled($transaction->reference) && filled($payment->reference) && str_contains(Str::lower($payment->reference), Str::lower($transaction->reference))];
        })->concat($transfers)->sortByDesc('reference_match')->values()->all();
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
