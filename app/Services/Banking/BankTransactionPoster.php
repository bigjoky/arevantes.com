<?php

namespace App\Services\Banking;

use App\Models\BankReconciliationRule;
use App\Models\BankReconciliationRun;
use App\Models\BankTransaction;
use App\Models\BankTransactionPosting;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Services\Accounting\JournalPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankTransactionPoster
{
    public function __construct(private JournalPoster $poster) {}

    public function post(BankTransaction $transaction, LedgerAccount $counterpart, int $userId, ?BankReconciliationRule $rule = null, ?BankReconciliationRun $run = null): BankTransactionPosting
    {
        return DB::transaction(function () use ($transaction, $counterpart, $userId, $rule, $run) {
            $transaction = BankTransaction::with('account.ledgerAccount')->lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->status !== 'pending') {
                throw ValidationException::withMessages(['transaction' => __('The bank movement is no longer pending.')]);
            }
            if (! $counterpart->is_active || ! $counterpart->is_postable) {
                throw ValidationException::withMessages(['account' => __('The reconciliation rule account is inactive or not postable.')]);
            }
            $bankCode = $transaction->account->ledgerAccount?->code ?? '572';
            if ($counterpart->code === $bankCode) {
                throw ValidationException::withMessages(['account' => __('The rule counterpart must differ from the bank account.')]);
            }
            $amount = abs((float) $transaction->amount);
            $incoming = (float) $transaction->amount > 0;
            $counterpartLine = ['account_code' => $counterpart->code, 'description' => $counterpart->name, 'debit' => $incoming ? 0 : $amount, 'credit' => $incoming ? $amount : 0];
            $bankLine = ['account_code' => $bankCode, 'debit' => $incoming ? $amount : 0, 'credit' => $incoming ? 0 : $amount];
            $posting = BankTransactionPosting::create([
                'bank_transaction_id' => $transaction->id,
                'reconciliation_rule_id' => $rule?->id,
                'reconciliation_rule_version_id' => $rule?->latestVersion?->id,
                'reconciliation_run_id' => $run?->id,
                'counterpart_account_id' => $counterpart->id,
                'allocation_data' => [['account_id' => $counterpart->id, 'code' => $counterpart->code, 'name' => $counterpart->name, 'amount' => $amount]],
                'posted_by' => $userId,
            ]);
            $entry = $this->poster->post($transaction->booked_on->toDateString(), $transaction->description, $incoming ? [$bankLine, $counterpartLine] : [$counterpartLine, $bankLine], $userId, 'bank_posting', $posting, 'bank_posting');
            $posting->update(['journal_entry_id' => $entry->id]);
            $transaction->update(['status' => 'accounted']);

            return $posting;
        });
    }

    public function reverse(BankTransactionPosting $posting, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($posting, $userId) {
            $posting = BankTransactionPosting::lockForUpdate()->findOrFail($posting->id);
            if ($posting->reversed_at) {
                throw ValidationException::withMessages(['posting' => __('This bank accounting posting has already been reversed.')]);
            }
            $posting->load('journalEntry.lines.account:id,code');
            if (! $posting->journalEntry) {
                throw ValidationException::withMessages(['posting' => __('The original accounting entry is missing.')]);
            }
            $lines = $posting->journalEntry->lines->map(fn ($line) => [
                'account_code' => $line->account->code,
                'description' => $line->description,
                'debit' => $line->credit,
                'credit' => $line->debit,
            ])->all();
            $entry = $this->poster->post(
                now()->toDateString(),
                __('Reversal of :entry', ['entry' => $posting->journalEntry->number]),
                $lines,
                $userId,
                'reversal',
                null,
                'reversal',
                $posting->journalEntry,
            );
            $posting->update(['reversal_entry_id' => $entry->id, 'reversed_at' => now()]);
            $posting->transaction()->update(['status' => 'pending']);

            return $entry;
        });
    }
}
