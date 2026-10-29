<?php

namespace App\Services\Accounting;

use App\Models\AccountingEntryCorrection;
use App\Models\AuditLog;
use App\Models\BankReconciliation;
use App\Models\BankTransactionPosting;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\TaxReturn;
use App\Models\TaxReturnPayment;
use App\Models\User;
use App\Support\Time\BusinessTime;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalCorrection
{
    public function __construct(private JournalPoster $poster, private AccountingPeriodManager $periods) {}

    public function revision(JournalEntry $entry): string
    {
        $entry = JournalEntry::findOrFail($entry->id);

        return hash('sha256', json_encode([$entry->getAttributes(), $entry->lines()->orderBy('id')->get()->toArray(), AccountingEntryCorrection::where('journal_entry_id', $entry->id)->max('id')], JSON_THROW_ON_ERROR));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['correction' => __($message)]);
    }

    public function currentLines(JournalEntry $entry): Collection
    {
        $entry->loadMissing('lines.account');
        $effective = $entry->lines->keyBy('id')->map(fn ($l) => ['account_code' => $l->account->code, 'description' => $l->description]);
        foreach (($entry->relationLoaded('corrections') ? $entry->corrections->sortBy('id') : AccountingEntryCorrection::where('journal_entry_id', $entry->id)->orderBy('id')->get()) as $c) {
            foreach ($c->line_changes as $line) {
                $effective[$line['id']] = ['account_code' => $line['account_code'], 'description' => $line['description']];
            }
        }

        return $effective;
    }

    public function plan(JournalEntry $entry, array $data): array
    {
        $entry->load('lines.account');
        $effective = $this->currentLines($entry);
        $changes = [];
        $posting = [];
        foreach ($data['lines'] ?? [] as $change) {
            $line = $entry->lines->firstWhere('id', (int) $change['id']);
            if (! $line) {
                $this->fail('The journal line does not belong to this entry.');
            }
            $old = $effective[$line->id];
            $code = (string) ($change['account_code'] ?? $old['account_code']);
            $new = LedgerAccount::where('code', $code)->first();
            $previous = LedgerAccount::where('code', $old['account_code'])->firstOrFail();
            if (! $new || ($new->code !== $old['account_code'] && (! $new->is_active || ! $new->is_postable))) {
                $this->fail('Select an active postable accounting account.');
            }
            $protected = ['430', '400', '410', '438', '407', '472', '477', '475', '476', '465', '460', '572', '570', '129', '120'];
            $prefix = in_array(substr($previous->code, 0, 3), $protected, true) ? substr($previous->code, 0, 3) : substr($previous->code, 0, 1);
            if (AccountingAccountPolicy::nature($new) !== AccountingAccountPolicy::nature($previous) || ! str_starts_with($new->code, $prefix)) {
                $this->fail('The replacement account is incompatible with the accounting nature of this line.');
            }
            $description = array_key_exists('description', $change) ? $change['description'] : $old['description'];
            $changes[] = ['id' => $line->id, 'account_code' => $new->code, 'description' => $description];
            if ($new->code !== $previous->code) {
                $posting[] = ['account_code' => $previous->code, 'description' => $old['description'], 'debit' => $line->credit, 'credit' => $line->debit];
                $posting[] = ['account_code' => $new->code, 'description' => $description, 'debit' => $line->debit, 'credit' => $line->credit];
            }
        }
        if ($posting && isset($data['entry_date'])) {
            if ($data['entry_date'] < $entry->entry_date->toDateString()) {
                $this->fail('The reclassification date cannot precede the original entry.');
            }
            $latest = AccountingEntryCorrection::where('journal_entry_id', $entry->id)->whereNotNull('correction_entry_id')->max('correction_date');
            if ($latest && $data['entry_date'] < $latest) {
                $this->fail('A later-dated account correction exists. Choose its date or a later open date.');
            }
        }
        if ($posting) {
            $this->poster->normalizeLines($posting);
        }

        return ['line_changes' => $changes, 'posting_lines' => $posting, 'changes_accounts' => (bool) $posting, 'original_untouched' => true];
    }

    /** Read-only eligibility, shared by individual and supervised batch previews. */
    public function preview(JournalEntry $entry, array $data): array
    {
        $entry = JournalEntry::findOrFail($entry->id);
        if ($entry->status !== 'posted' || $entry->reversal()->exists() || in_array($entry->entry_kind, ['closing', 'opening', 'correction', 'reversal'], true)) {
            $this->fail('This journal entry requires a different protected accounting procedure.');
        }
        if (! hash_equals($this->revision($entry), $data['revision'])) {
            $this->fail('The entry or its corrections changed. Reload before confirming.');
        }
        foreach (array_unique([$entry->entry_date->toDateString(), $data['entry_date']]) as $date) {
            if (! FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->where('status', 'open')->exists()) {
                $this->fail('The correction requires open fiscal years and periods.');
            }
            $this->periods->assertOpen($date);
        }
        if (TaxReturn::whereIn('status', ['prepared', 'filed'])->where(function ($q) use ($entry, $data) {
            foreach (array_unique([$entry->entry_date->toDateString(), $data['entry_date']]) as $date) {
                $q->orWhere(fn ($p) => $p->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date));
            }
        })->exists()) {
            $this->fail('A prepared or filed tax return protects this journal entry.');
        }
        $plan = $this->plan($entry, $data);
        $source = $entry->source;
        if ($entry->source_type && ! $source) {
            $this->fail('The source document is unavailable. Review it before correcting this entry.');
        }
        if ($plan['changes_accounts'] && ($source instanceof SalesDocumentPayment || $source instanceof SupplierPayment) && ($source->reconciliation()->exists() || $source->origin_bank_transaction_id || $source->voided_at)) {
            $this->fail('Correct the bank relationship or cancelled operation through its authorized workflow first.');
        }
        if ($plan['changes_accounts'] && ($source instanceof BankReconciliation || $source instanceof BankTransactionPosting || $source instanceof BankTransfer || $source instanceof TaxReturn || $source instanceof TaxReturnPayment)) {
            $this->fail('Bank reconciliation entries must be corrected from the bank workspace.');
        }

        return $plan;
    }

    private function replay(AccountingEntryCorrection $existing, JournalEntry $entry, array $data, User $actor): AccountingEntryCorrection
    {
        if ($existing->journal_entry_id !== $entry->id || $existing->created_by !== $actor->id || $existing->correction_date !== $data['entry_date'] || $existing->reason !== $data['reason'] || $existing->revision !== $data['revision'] || $existing->description !== ($data['description'] ?? null) || $existing->notes !== ($data['notes'] ?? null) || $existing->reference !== ($data['reference'] ?? null) || $existing->line_changes !== array_map(fn ($l) => ['id' => (int) $l['id'], 'account_code' => (string) $l['account_code'], 'description' => $l['description'] ?? null], $data['lines'] ?? [])) {
            $this->fail('The correction key was already used for a different request.');
        }

        return $existing;
    }

    public function correct(JournalEntry $entry, array $data, User $actor): AccountingEntryCorrection
    {
        abort_unless($actor->can('correct-journal-entries'), 403);
        try {
            return DB::transaction(function () use ($entry, $data, $actor) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SET LOCAL lock_timeout='3s'");
                }
                // Source -> fiscal years -> original entry, matching cancellation/posting workflows.
                $source = $entry->source;
                if ($entry->source_type && ! $source) {
                    $this->fail('The source document is unavailable. Review it before correcting this entry.');
                }
                if ($source instanceof SalesDocumentPayment) {
                    SalesDocument::whereKey($source->sales_document_id)->lockForUpdate()->firstOrFail();
                    $source = SalesDocumentPayment::withoutGlobalScopes()->whereKey($source->id)->lockForUpdate()->firstOrFail();
                } elseif ($source instanceof SupplierPayment) {
                    if ($source->expense_id) {
                        Expense::whereKey($source->expense_id)->lockForUpdate()->firstOrFail();
                    } elseif ($source->purchase_document_id) {
                        PurchaseDocument::whereKey($source->purchase_document_id)->lockForUpdate()->firstOrFail();
                    }$source = SupplierPayment::withoutGlobalScopes()->whereKey($source->id)->lockForUpdate()->firstOrFail();
                } elseif ($source) {
                    $source->newQuery()->whereKey($source->id)->lockForUpdate()->firstOrFail();
                }
                $existing = AccountingEntryCorrection::where('operation_key', $data['operation_key'])->first();
                if ($existing) {
                    return $this->replay($existing, $entry, $data, $actor);
                }
                $dates = array_unique([$entry->entry_date->toDateString(), $data['entry_date']]);
                sort($dates);
                foreach ($dates as $date) {
                    $year = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();
                    if (! $year || $year->status !== 'open') {
                        $this->fail('The correction requires open fiscal years and periods.');
                    }$this->periods->assertOpen($date);
                }
                $entry = JournalEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
                $existing = AccountingEntryCorrection::where('operation_key', $data['operation_key'])->first();
                if ($existing) {
                    return $this->replay($existing, $entry, $data, $actor);
                }
                if ($entry->status !== 'posted' || $entry->reversal()->exists() || in_array($entry->entry_kind, ['closing', 'opening', 'correction', 'reversal'], true)) {
                    $this->fail('This journal entry requires a different protected accounting procedure.');
                }
                if (! hash_equals($this->revision($entry), $data['revision'])) {
                    $this->fail('The entry or its corrections changed. Reload before confirming.');
                }
                if (TaxReturn::whereIn('status', ['prepared', 'filed'])->where(function ($q) use ($entry, $data) {
                    foreach (array_unique([$entry->entry_date->toDateString(), $data['entry_date']]) as $date) {
                        $q->orWhere(fn ($p) => $p->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date));
                    }
                })->exists()) {
                    $this->fail('A prepared or filed tax return protects this journal entry.');
                }
                $plan = $this->plan($entry, $data);
                if ($plan['changes_accounts'] && ($source instanceof SalesDocumentPayment || $source instanceof SupplierPayment) && ($source->reconciliation()->exists() || $source->origin_bank_transaction_id || $source->voided_at)) {
                    $this->fail('Correct the bank relationship or cancelled operation through its authorized workflow first.');
                }
                if ($plan['changes_accounts'] && ($source instanceof BankReconciliation || $source instanceof BankTransactionPosting || $source instanceof BankTransfer || $source instanceof TaxReturn || $source instanceof TaxReturnPayment)) {
                    $this->fail('Bank reconciliation entries must be corrected from the bank workspace.');
                }
                $correctionEntry = null;
                if ($plan['changes_accounts']) {
                    $correctionEntry = $this->poster->post($data['entry_date'], app(JournalConcepts::class)->render('correction', ['referencia' => $entry->number, 'concepto' => $data['reason']]), $plan['posting_lines'], $actor->id, 'correction', $entry, 'corr:'.substr(hash('sha256', $data['operation_key']), 0, 24));
                }
                $c = AccountingEntryCorrection::create(['journal_entry_id' => $entry->id, 'created_by' => $actor->id, 'operation_key' => $data['operation_key'], 'revision' => $data['revision'], 'reason' => $data['reason'], 'correction_date' => $data['entry_date'], 'description' => $data['description'] ?? null, 'notes' => $data['notes'] ?? null, 'reference' => $data['reference'] ?? null, 'line_changes' => $plan['line_changes'], 'correction_entry_id' => $correctionEntry?->id, 'recorded_at_utc' => BusinessTime::now()]);
                AuditLog::create(['user_id' => $actor->id, 'event' => $correctionEntry ? 'journal_account_correction' : 'journal_annotation', 'auditable_type' => $entry->getMorphClass(), 'auditable_id' => $entry->id, 'new_values' => ['correction_id' => $c->id, 'reason' => $c->reason, 'operation_key' => $c->operation_key, 'correction_entry_id' => $correctionEntry?->id, 'original_preserved' => true]]);

                return $c;
            });
        } catch (UniqueConstraintViolationException) {
            $this->fail('The correction key was already used for a different request.');
        }
    }
}
