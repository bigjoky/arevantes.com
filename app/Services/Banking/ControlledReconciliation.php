<?php

namespace App\Services\Banking;

use App\Models\AccountingEntryCorrection;
use App\Models\AuditLog;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\FiscalYear;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\TaxReturn;
use App\Models\TaxReturnPayment;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodManager;
use App\Services\Accounting\JournalPoster;
use App\Services\Finance\ReceivedInvoiceIdentity;
use App\Support\Time\BusinessTime;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ControlledReconciliation
{
    public function __construct(private AccountingPeriodManager $periods, private JournalPoster $poster) {}

    public static function authorized(User $user): bool
    {
        return $user->hasRole('admin') && $user->can('manage-treasury') && $user->can('view-finance');
    }

    public function bankRemaining(BankTransaction $bank): BigDecimal
    {
        return BigDecimal::of($bank->amount)->abs()->minus($this->allocated($bank->reconciliations()));
    }

    public function paymentRemaining(Model $payment): BigDecimal
    {
        return BigDecimal::of($payment->amount)->minus($this->allocated($payment->reconciliations()));
    }

    private function allocated($relation): string
    {
        return (string) $relation->join('bank_transactions as allocation_bank', 'allocation_bank.id', '=', 'bank_reconciliations.bank_transaction_id')->sum(DB::raw('coalesce(bank_reconciliations.allocated_amount, abs(allocation_bank.amount))'));
    }

    public function revision(BankTransaction $bank): string
    {
        return hash('sha256', json_encode([$bank->only(['id', 'bank_account_id', 'amount', 'currency', 'booked_on', 'reference', 'description', 'status', 'updated_at']), (string) $this->bankRemaining($bank), $bank->reconciliations()->orderBy('id')->pluck('id')->all()], JSON_THROW_ON_ERROR));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['reconciliation' => __($message)]);
    }

    public function payment(string $type, int $id, bool $lock = false): Model
    {
        $class = match ($type) {
            'customer' => SalesDocumentPayment::class, 'supplier' => SupplierPayment::class, 'tax' => TaxReturnPayment::class, default => null
        };
        abort_unless($class, 422);
        $query = $class::query()->withoutGlobalScope('active_customer_payment')->withoutGlobalScope('active_supplier_payment');

        $payment = ($lock ? $query->lockForUpdate() : $query)->findOrFail($id);
        if ($payment->voided_at) {
            $this->fail('Voided ERP operations cannot be reconciled.');
        }

        return $payment;
    }

    private function lockOwner(string $type, Model $payment): void
    {
        if ($type === 'customer') {
            $document = SalesDocument::findOrFail($payment->sales_document_id);
            if ($document->quote_id) {
                Quote::lockForUpdate()->findOrFail($document->quote_id);
            }
            SalesDocument::lockForUpdate()->findOrFail($document->id);
        } elseif ($type === 'supplier') {
            $owner = $payment->expense_id ? $payment->expense : $payment->purchaseDocument;
            abort_unless($owner, 422);
            $owner->newQuery()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            app(ReceivedInvoiceIdentity::class)->assertUsable($owner);
        }
    }

    public function assertPeriods(array $dates, bool $lock = true): void
    {
        foreach (collect($dates)->unique()->sort() as $date) {
            $query = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
            $year = ($lock ? $query->lockForUpdate() : $query)->first();
            if (! $year || $year->status !== 'open') {
                $this->fail('The bank operation belongs to a closed or unavailable fiscal year.');
            }
            $this->periods->assertOpen($date);
        }
        if (TaxReturn::whereIn('status', ['prepared', 'filed'])->where(function ($query) use ($dates) {
            foreach ($dates as $date) {
                $query->orWhere(fn ($q) => $q->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date));
            }
        })->exists()) {
            $this->fail('A protected tax period requires accounting review before bank correction or matching.');
        }
    }

    private function paymentDate(Model $payment): string
    {
        return ($payment instanceof TaxReturnPayment ? $payment->paid_on : $payment->paid_at)->toDateString();
    }

    private function assertCompatible(BankTransaction $bank, Model $payment, string $type): void
    {
        $bank->loadMissing('account.ledgerAccount');
        if (! $bank->account->is_active || $bank->currency !== $bank->account->currency || $bank->currency !== 'EUR') {
            $this->fail('The account or currency is incompatible with this ERP operation.');
        }
        $incoming = $type === 'customer' || ($type === 'tax' && (float) $payment->taxReturn->result < 0);
        if ($incoming !== BigDecimal::of($bank->amount)->isPositive() || BigDecimal::of($bank->amount)->isZero() || $payment->method === 'cash') {
            $this->fail('The payment direction or method is incompatible with the bank movement.');
        }
        if (($payment instanceof SalesDocumentPayment && ($payment->document?->type !== 'invoice' || ($payment->document?->status === 'cancelled' || ($payment->document?->status === 'draft' && ! $payment->document?->collect_before_invoice)))) || ($payment instanceof SupplierPayment && ($payment->expense?->status === 'cancelled' || $payment->purchaseDocument?->status === 'cancelled'))) {
            $this->fail('The underlying ERP document is cancelled or incompatible.');
        }
        $posted = $payment instanceof TaxReturnPayment ? $payment->journalEntry : $payment->accountingEntry;
        if ($posted && ($posted->status !== 'posted' || $posted->reversal()->exists())) {
            $this->fail('The ERP accounting entry is unposted or already reversed.');
        }
        if ($payment instanceof SupplierPayment) {
            if (($payment->funding_source_type === 'bank_account' && (int) $payment->funding_source_id !== $bank->bank_account_id) || ($payment->expense && $payment->expense->currency_code !== 'EUR')) {
                $this->fail('This supplier payment belongs to another bank account or currency.');
            }
        }
        if ($payment->origin_bank_transaction_id && (int) $payment->origin_bank_transaction_id !== $bank->id) {
            $this->fail('This ERP operation originates from another bank movement.');
        }
    }

    public function match(BankTransaction $bank, string $selection, string $amount, int $accountId, string $revision, string $key, string $reason, User $user): BankReconciliation
    {
        abort_unless(self::authorized($user), 403);
        if (! Str::isUuid($key)) {
            $this->fail('This reconciliation operation key has already been used.');
        }
        [$type, $id] = explode(':', $selection, 2);
        $allocated = BigDecimal::of($amount)->toScale(2);
        if (! $allocated->isPositive() || mb_strlen(trim($reason)) < 5) {
            $this->fail('Confirm a positive allocation and a meaningful reconciliation reason.');
        }

        return $this->transaction(function () use ($bank, $type, $id, $allocated, $accountId, $revision, $key, $reason, $user) {
            $bank = BankTransaction::lockForUpdate()->findOrFail($bank->id);
            $previous = BankReconciliation::withoutGlobalScope('active_bank_reconciliation')->where('operation_key', $key)->first();
            if ($previous) {
                if ($previous->bank_transaction_id === $bank->id && $previous->{$type === 'customer' ? 'sales_document_payment_id' : ($type === 'supplier' ? 'supplier_payment_id' : 'tax_return_payment_id')} === (int) $id && $previous->effectiveAmount() === (string) $allocated && $previous->reconciled_by === $user->id && $previous->reason === $reason && ! $previous->reversed_at) {
                    return $previous;
                }
                $this->fail('This reconciliation operation key has already been used.');
            }
            if ($bank->bank_account_id !== $accountId || ! hash_equals($this->revision($bank), $revision)) {
                $this->fail('The bank movement changed. Reload before confirming reconciliation.');
            }
            if (! in_array($bank->status, ['pending', 'partial'], true)) {
                $this->fail('The bank movement is already reconciled or accounted.');
            }
            $payment = $this->payment($type, (int) $id);
            $this->lockOwner($type, $payment);
            $payment = $this->payment($type, (int) $id, true);
            $this->assertCompatible($bank, $payment, $type);
            $this->assertPeriods([$bank->booked_on->toDateString(), $this->paymentDate($payment)]);
            if ($allocated->isGreaterThan($this->bankRemaining($bank)) || $allocated->isGreaterThan($this->paymentRemaining($payment))) {
                $this->fail('The allocation exceeds the remaining bank or ERP amount.');
            }
            $field = match ($type) {
                'customer' => 'sales_document_payment_id', 'supplier' => 'supplier_payment_id', 'tax' => 'tax_return_payment_id'
            };
            if ($bank->reconciliations()->where($field, $payment->id)->exists()) {
                $this->fail('This movement and ERP operation already have an active allocation.');
            }
            $reconciliation = BankReconciliation::create(['bank_transaction_id' => $bank->id, $field => $payment->id, 'allocated_amount' => (string) $allocated, 'operation_key' => $key, 'reason' => $reason, 'reconciled_by' => $user->id, 'reconciled_at' => now(), 'confirmed_at_utc' => BusinessTime::now(), 'method' => 'manual']);
            $this->reclassify($bank, $payment, $reconciliation, $allocated, $user);
            $this->refreshBank($bank);
            $this->audit($bank, 'bank_match_confirmed', $user, ['reconciliation_id' => $reconciliation->id, 'payment_type' => $type, 'payment_id' => $payment->id, 'amount' => (string) $allocated, 'operation_key' => $key, 'reason' => $reason, 'bank_reference' => $bank->reference]);

            return $reconciliation;
        }, $key, 'operation_key');
    }

    private function reclassify(BankTransaction $bank, Model $payment, BankReconciliation $match, BigDecimal $amount, User $user): void
    {
        $code = $bank->account->ledgerAccount?->code ?? '572';
        $entry = $payment instanceof TaxReturnPayment ? $payment->journalEntry : $payment->accountingEntry;
        $incoming = BigDecimal::of($bank->amount)->isPositive();
        $funding = $entry?->lines()->with('account')->where($incoming ? 'debit' : 'credit', '>', 0)->get();
        if (($entry && $funding?->count() !== 1) || (! $entry && $code !== '572')) {
            $this->fail('Post and verify the ERP treasury entry before bank account reclassification.');
        }
        $source = $funding?->count() === 1 ? $funding->first()->account->code : '572';
        if ($entry && $funding?->count() === 1) {
            foreach (AccountingEntryCorrection::where('journal_entry_id', $entry->id)->orderBy('id')->get() as $correction) {
                foreach ($correction->line_changes as $change) {
                    if ((int) $change['id'] === $funding->first()->id) {
                        $source = $change['account_code'];
                    }
                }
            }
        }
        if ($source !== '572' && $source !== $code) {
            $this->fail('The posted treasury account differs from the selected bank account. Accounting review is required.');
        }
        if ($code === $source) {
            return;
        }
        $lines = [['account_code' => $code, 'debit' => $incoming ? (string) $amount : '0.00', 'credit' => $incoming ? '0.00' : (string) $amount], ['account_code' => $source, 'debit' => $incoming ? '0.00' : (string) $amount, 'credit' => $incoming ? (string) $amount : '0.00']];
        $posted = $this->poster->post($bank->booked_on->toDateString(), __('Bank reconciliation :description', ['description' => $bank->description]), $lines, $user->id, 'bank_reclassification', $match, 'bank_reclassification');
        $match->update(['journal_entry_id' => $posted->id]);
    }

    public function undo(int $id, string $key, string $reason, User $user): void
    {
        abort_unless($user->can('correct-bank-reconciliations'), 403);
        if (mb_strlen(trim($reason)) < 5 || ! Str::isUuid($key)) {
            $this->fail('Confirm a positive allocation and a meaningful reconciliation reason.');
        }
        $this->transaction(function () use ($id, $key, $reason, $user): void {
            $reference = BankReconciliation::withoutGlobalScope('active_bank_reconciliation')->findOrFail($id);
            $bank = BankTransaction::lockForUpdate()->findOrFail($reference->bank_transaction_id);
            if ($reference->reversed_at) {
                if ($reference->reversal_operation_key === $key && $reference->reversal_reason === $reason && $reference->reversed_by === $user->id) {
                    return;
                }
                $this->fail('This reconciliation has already been corrected.');
            }
            $type = $reference->sales_document_payment_id ? 'customer' : ($reference->supplier_payment_id ? 'supplier' : 'tax');
            $paymentId = $reference->sales_document_payment_id ?? $reference->supplier_payment_id ?? $reference->tax_return_payment_id;
            $payment = $this->payment($type, $paymentId);
            $this->lockOwner($type, $payment);
            $payment = $this->payment($type, $paymentId, true);
            $this->assertPeriods([$bank->booked_on->toDateString(), $this->paymentDate($payment)]);
            $match = BankReconciliation::withoutGlobalScope('active_bank_reconciliation')->lockForUpdate()->findOrFail($id);
            if ($match->reversed_at) {
                if ($match->reversal_operation_key === $key && $match->reversal_reason === $reason && $match->reversed_by === $user->id) {
                    return;
                }
                $this->fail('This reconciliation has already been corrected.');
            }
            if ($payment->origin_bank_transaction_id || ! in_array($bank->status, ['partial', 'reconciled'], true)) {
                $this->fail('A bank-generated operation or incompatible state requires a separate accounting correction. The original operation is retained.');
            }
            $entry = $match->journalEntry()->lockForUpdate()->first();
            $reversal = null;
            if ($entry) {
                if ($entry->status !== 'posted' || $entry->reversal()->exists()) {
                    $this->fail('The reconciliation entry is already reversed or incompatible.');
                }
                $entry->load('lines.account');
                $reversal = $this->poster->post($entry->entry_date->toDateString(), __('Reversal of :entry', ['entry' => $entry->number]), $entry->lines->map(fn ($l) => ['account_code' => $l->account->code, 'debit' => $l->credit, 'credit' => $l->debit])->all(), $user->id, 'reversal', $match, 'bank_match_reversed', $entry);
            }
            $match->update(['reversed_at' => BusinessTime::now(), 'reversed_by' => $user->id, 'reversal_reason' => $reason, 'reversal_operation_key' => $key, 'reversal_entry_id' => $reversal?->id]);
            $this->refreshBank($bank);
            $this->audit($bank, 'bank_match_corrected', $user, ['reconciliation_id' => $id, 'amount' => $match->effectiveAmount(), 'reason' => $reason, 'operation_key' => $key, 'reversal_entry_id' => $reversal?->id, 'original_journal_entry_id' => $entry?->id]);
        }, $key, 'reversal_operation_key');
    }

    private function transaction(\Closure $callback, string $key, string $column): mixed
    {
        try {
            return DB::transaction(function () use ($callback) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SET LOCAL lock_timeout='3s'");
                }

                return $callback();
            });
        } catch (UniqueConstraintViolationException $error) {
            if (BankReconciliation::withoutGlobalScope('active_bank_reconciliation')->where($column, $key)->exists()) {
                $this->fail('This reconciliation operation key has already been used.');
            }
            throw $error;
        }
    }

    private function refreshBank(BankTransaction $bank): void
    {
        $remaining = $this->bankRemaining($bank);
        $bank->update(['status' => $remaining->isZero() ? 'reconciled' : ($remaining->isEqualTo(BigDecimal::of($bank->amount)->abs()) ? 'pending' : 'partial')]);
    }

    public function review(BankTransaction $bank, string $category, string $status, string $reason, string $key, User $user): void
    {
        abort_unless(self::authorized($user), 403);
        if (mb_strlen(trim($reason)) < 5 || ! Str::isUuid($key) || ! in_array($category, ReconciliationWorkspace::CATEGORIES, true) || ! in_array($status, ['investigating', 'documented', 'resolved'], true)) {
            $this->fail('Confirm a positive allocation and a meaningful reconciliation reason.');
        }
        DB::transaction(function () use ($bank, $category, $status, $reason, $key, $user): void {
            $bank = BankTransaction::lockForUpdate()->findOrFail($bank->id);
            $previous = AuditLog::where('auditable_type', BankTransaction::class)->where('auditable_id', $bank->id)->where('event', 'bank_discrepancy_review')->where('new_values->operation_key', $key)->first();
            if ($previous) {
                if ($previous->user_id !== $user->id || ($previous->new_values['reason'] ?? null) !== $reason || ($previous->new_values['category'] ?? null) !== $category || ($previous->new_values['review_status'] ?? null) !== $status) {
                    $this->fail('This reconciliation operation key has already been used.');
                }

                return;
            }
            $evidence = ($bank->reconciliations()->exists() && $this->bankRemaining($bank)->isZero()) || $bank->posting()->whereNull('reversed_at')->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))->exists() || $bank->outgoingTransfer()->whereNull('reversed_at')->exists() || $bank->incomingTransfer()->whereNull('reversed_at')->exists();
            if ($status === 'resolved' && (! in_array($bank->status, ['reconciled', 'accounted'], true) || ! $evidence)) {
                $this->fail('Documenting a review does not resolve an unmatched financial movement.');
            }
            $this->audit($bank, 'bank_discrepancy_review', $user, ['category' => $category, 'review_status' => $status, 'reason' => $reason, 'operation_key' => $key, 'financial_status' => $bank->status]);
        });
    }

    private function audit(BankTransaction $bank, string $event, User $user, array $values): void
    {
        AuditLog::create(['user_id' => $user->id, 'auditable_type' => BankTransaction::class, 'auditable_id' => $bank->id, 'event' => $event, 'new_values' => [...$values, 'actor_id' => $user->id, 'recorded_at_utc' => BusinessTime::now()->utc()->toISOString()]]);
    }
}
