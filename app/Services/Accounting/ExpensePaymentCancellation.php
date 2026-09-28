<?php

namespace App\Services\Accounting;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\SupplierPayment;
use App\Models\TaxReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpensePaymentCancellation
{
    public function __construct(private AccountingPeriodManager $periods, private JournalPoster $poster) {}

    public function cancel(Expense $expense, SupplierPayment $payment, User $user, string $reason, array $context = []): void
    {
        abort_unless($user->hasRole('admin') && $user->can('manage-purchase-payments'), 403);
        abort_unless($payment->expense_id === $expense->id && ! $payment->purchase_document_id, 404);

        DB::transaction(function () use ($expense, $payment, $user, $reason, $context): void {
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->reconciliation()->exists() || $payment->origin_bank_transaction_id) {
                throw ValidationException::withMessages(['reason' => __('Undo the bank reconciliation before cancelling this payment.')]);
            }
            $date = $payment->paid_at->toDateString();
            $year = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();
            if (! $year || $year->status !== 'open') {
                throw ValidationException::withMessages(['reason' => __('The payment belongs to a closed or unavailable fiscal year.')]);
            }
            $this->periods->assertOpen($date);
            $protected = TaxReturn::whereIn('status', ['prepared', 'filed'])
                ->where(function ($query) use ($date, $expense) {
                    foreach ([$date, $expense->expense_date->toDateString()] as $day) {
                        $query->orWhere(fn ($period) => $period->whereDate('period_from', '<=', $day)->whereDate('period_to', '>=', $day));
                    }
                })->lockForUpdate()->exists();
            if ($protected) {
                throw ValidationException::withMessages(['reason' => __('A prepared or filed tax return protects this payment. Review it with your accountant.')]);
            }
            $before = $payment->only(['id', 'expense_id', 'paid_at', 'amount', 'method', 'funding_source_type', 'funding_source_id', 'ledger_account_id', 'reference']);
            $entry = $payment->accountingEntry()->with('lines.account')->lockForUpdate()->first();
            $reversal = null;
            if ($entry) {
                $reversal = $this->poster->post($date, __('Reversal of :entry', ['entry' => $entry->number]),
                    $entry->lines->map(fn ($line) => ['account_code' => $line->account->code, 'debit' => $line->credit, 'credit' => $line->debit])->all(),
                    $user->id, 'reversal', $payment, 'payment_voided', $entry);
            }
            $payment->forceFill(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $reason, 'reversal_entry_id' => $reversal?->id])->save();
            $paid = round((float) $expense->payments()->sum('amount'), 2);
            $expense->update(['paid_amount' => $paid, 'status' => $paid >= (float) $expense->amount ? 'paid' : ($paid > 0 ? 'partial' : ($expense->due_date?->isPast() ? 'overdue' : 'pending'))]);
            AuditLog::create([
                ...$context, 'user_id' => $user->id, 'event' => 'expense_payment_voided',
                'auditable_type' => $expense->getMorphClass(), 'auditable_id' => $expense->id,
                'old_values' => $before,
                'new_values' => [...$before, 'voided_at' => $payment->voided_at->toIso8601String(), 'reason' => $reason, 'reversal_entry_id' => $reversal?->id, 'paid_amount' => $paid],
            ]);
        });
    }
}
