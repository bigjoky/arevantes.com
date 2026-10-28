<?php

namespace App\Services\Accounting;

use App\Models\AuditLog;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Finance\OutstandingBalanceCalculator;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerPaymentCancellation
{
    public function __construct(private AccountingPeriodManager $periods, private JournalPoster $poster, private OutstandingBalanceCalculator $balances) {}

    public static function authorized(User $user): bool
    {
        return $user->hasRole('admin') && $user->can('manage-admin') && $user->can('manage-treasury') && $user->can('manage-installations');
    }

    public function cancel(SalesDocument $document, int $paymentId, User $user, string $reason, string $operationKey, string $revision, array $context = []): void
    {
        abort_unless(self::authorized($user), 403);
        if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 2000 || ! Str::isUuid($operationKey)) {
            throw ValidationException::withMessages(['reason' => __('Provide a meaningful cancellation reason and a valid operation key.')]);
        }
        try {
            DB::transaction(function () use ($document, $paymentId, $user, $reason, $operationKey, $revision, $context): void {
                // Same lock order as new receipts, bank matching and invoice issue.
                if ($document->quote_id) {
                    Quote::lockForUpdate()->findOrFail($document->quote_id);
                }
                $document = SalesDocument::lockForUpdate()->findOrFail($document->id);
                $payment = SalesDocumentPayment::withoutGlobalScope('active_customer_payment')->lockForUpdate()->findOrFail($paymentId);
                abort_unless($payment->sales_document_id === $document->id && $document->type === 'invoice', 404);
                if ($payment->voided_at) {
                    if ($payment->void_operation_key === $operationKey && $payment->void_reason === $reason && $payment->voided_by === $user->id) {
                        return; // Lost HTTP response/repeated confirmation, no additional effects.
                    }
                    throw ValidationException::withMessages(['reason' => __('This customer receipt has already been cancelled.')]);
                }
                if (! hash_equals($payment->cancellationRevision(), $revision)) {
                    throw ValidationException::withMessages(['reason' => __('The receipt changed. Reload its details before cancelling.')]);
                }
                if (SalesDocumentPayment::withoutGlobalScope('active_customer_payment')->where('void_operation_key', $operationKey)->exists()) {
                    throw ValidationException::withMessages(['reason' => __('This cancellation key has already been used.')]);
                }
                if ($payment->reconciliation()->exists() || $payment->origin_bank_transaction_id) {
                    throw ValidationException::withMessages(['reason' => __('This receipt is linked to a bank movement. Audited bank correction is required before cancellation; no reconciliation or bank movement will be removed.')]);
                }
                if ($document->status === 'cancelled') {
                    throw ValidationException::withMessages(['reason' => __('Review the cancelled invoice before cancelling its receipts.')]);
                }
                $entriesQuery = JournalEntry::whereMorphedTo('source', $payment)->whereIn('event', ['payment', 'invoice_allocation']);
                $dates = $entriesQuery->pluck('entry_date')->map(fn ($date) => substr($date, 0, 10))->push($payment->paid_at->toDateString())->unique()->sort()->values();
                // Lock fiscal years in chronological order before entries, matching JournalPoster/period closure.
                foreach ($dates as $date) {
                    $year = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();
                    if (! $year || $year->status !== 'open') {
                        throw ValidationException::withMessages(['reason' => __('The receipt belongs to a closed or unavailable fiscal year.')]);
                    }
                    $this->periods->assertOpen($date);
                }
                $taxDates = $dates->all();
                if ($document->locked_at) {
                    $taxDates[] = $document->issue_date->toDateString();
                }
                if (TaxReturn::whereIn('status', ['prepared', 'filed'])->where(function ($query) use ($taxDates) {
                    foreach ($taxDates as $date) {
                        $query->orWhere(fn ($q) => $q->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date));
                    }
                })->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['reason' => __('A prepared or filed tax return protects this receipt. Review it with your accountant.')]);
                }
                $entries = $entriesQuery->with('lines.account')->orderBy('id')->lockForUpdate()->get();
                if ($entries->contains(fn ($entry) => $entry->status !== 'posted' || $entry->reversal()->exists())) {
                    throw ValidationException::withMessages(['reason' => __('A receipt entry is unposted or already reversed. Accounting review is required.')]);
                }
                if ($entries->contains(fn ($entry) => ! $entry->lines->reduce(fn ($sum, $line) => $sum->plus($line->debit), BigDecimal::zero())->isEqualTo($payment->amount) || ! $entry->lines->reduce(fn ($sum, $line) => $sum->plus($line->credit), BigDecimal::zero())->isEqualTo($payment->amount))) {
                    throw ValidationException::withMessages(['reason' => __('Receipt and journal amounts differ. Accounting review is required.')]);
                }
                $before = $payment->only(['id', 'sales_document_id', 'created_by', 'paid_at', 'amount', 'method', 'reference', 'notes', 'origin_bank_transaction_id', 'idempotency_key']);
                $reversals = [];
                foreach ($entries as $entry) {
                    $reversal = $this->poster->post($entry->entry_date->toDateString(), __('Reversal of :entry', ['entry' => $entry->number]),
                        $entry->lines->map(fn ($line) => ['account_code' => $line->account->code, 'debit' => $line->credit, 'credit' => $line->debit, 'description' => $line->description])->all(),
                        $user->id, 'reversal', $payment, $entry->event === 'payment' ? 'payment_voided' : 'allocation_voided', $entry);
                    $reversals[$entry->event] = $reversal->id;
                }
                $payment->forceFill(['voided_at' => now()->utc(), 'voided_by' => $user->id, 'void_reason' => $reason, 'void_operation_key' => $operationKey, 'reversal_entry_id' => $reversals['payment'] ?? null])->save();
                $this->balances->refreshSalesDocument($document);
                AuditLog::create([
                    ...$context, 'user_id' => $user->id, 'event' => 'customer_receipt_voided', 'auditable_type' => $payment->getMorphClass(), 'auditable_id' => $payment->id,
                    'old_values' => $before, 'new_values' => [...$before, 'voided_at_utc' => $payment->voided_at->utc()->toISOString(), 'voided_by' => $user->id, 'reason' => $reason, 'operation_key' => $operationKey, 'reversal_entries' => $reversals, 'paid_amount' => $document->paid_amount, 'invoice_status' => $document->status, 'bank_refund_created' => false],
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (SalesDocumentPayment::withoutGlobalScope('active_customer_payment')->where('void_operation_key', $operationKey)->exists()) {
                throw ValidationException::withMessages(['reason' => __('This cancellation key has already been used.')]);
            }
            throw $exception;
        }
    }
}
