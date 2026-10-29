<?php

namespace App\Services\Accounting;

use App\Models\AccountingEntryCorrection;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\TaxReturn;
use App\Services\Finance\ReceivedInvoiceIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpenseCorrection
{
    public function __construct(private AccountingSynchronizer $accounting, private AccountingPeriodManager $periods, private JournalPoster $poster) {}

    public static function snapshot(Expense $expense): array
    {
        return [...$expense->attributesToArray(), 'tax_lines' => $expense->taxLines()->get()->toArray()];
    }

    public static function revision(Expense $expense): string
    {
        return hash('sha256', json_encode(self::snapshot($expense), JSON_THROW_ON_ERROR));
    }

    public function correct(Expense $expense, array $data, array $taxLines, Request $request): void
    {
        abort_unless($request->user()->hasRole('admin') && $request->user()->can('manage-catalog'), 403);
        DB::transaction(function () use ($expense, $data, $taxLines, $request) {
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);
            $paid = round((float) $expense->payments()->sum('amount'), 2);
            if ($expense->status === 'paid' || ($paid > 0 && $paid >= (float) $expense->amount)) {
                $this->fail('Paid expenses cannot be edited.');
            }
            if (! hash_equals(self::revision($expense), $request->string('revision')->toString())) {
                $this->fail('The expense has changed. Reload it before editing.');
            }
            app(ReceivedInvoiceIdentity::class)->lockCorrectionKeys($expense, $data);
            $before = self::snapshot($expense);
            $oldDate = $expense->expense_date->toDateString();
            $newDate = CarbonImmutable::parse($data['expense_date'])->toDateString();
            $dates = array_unique([$oldDate, $newDate]);
            sort($dates);
            foreach ($dates as $date) {
                $year = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();
                if (! $year || $year->status !== 'open') {
                    $this->fail('The expense date must belong to an open fiscal year.');
                }
                $this->periods->assertOpen($date);
                if (TaxReturn::whereIn('status', ['prepared', 'filed'])->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date)->lockForUpdate()->exists()) {
                    $this->fail('A prepared or filed tax return protects this expense.');
                }
            }
            if ($expense->creditor_account_code && (string) ($data['supplier_id'] ?? '') !== (string) ($expense->supplier_id ?? '')) {
                if ((string) ($data['supplier_id'] ?? '') === (string) ($expense->supplier_id ?? '')) {
                    $data['creditor_account_code'] = $expense->creditor_account_code;
                }
                $data = app(ExpenseCreditorRegistry::class)->newInvoice($data);
            }
            if ($paid > 0 && ((string) ($data['supplier_id'] ?? '') !== (string) ($expense->supplier_id ?? '') || $data['currency_code'] !== $expense->currency_code)) {
                $this->fail('Cancel existing payments before changing the supplier or currency.');
            }
            $amount = $data['tax_calculation_snapshot']['result']['totals']['total'] ?? round($data['subtotal'] + $data['tax_amount'] - $data['withholding_amount'], 2);
            if ($amount < $paid || $amount < 0) {
                $this->fail('The expense total cannot be less than its registered payments.');
            }
            $oldLines = app(PostedAccountSnapshot::class)->lines($expense) ?: $this->accounting->expenseLines($expense);
            if (AccountingEntryCorrection::whereIn('journal_entry_id', JournalEntry::whereMorphedTo('source', $expense)->select('id'))->exists()) {
                $this->fail('Review the manual journal correction before changing this fiscal document.');
            }
            $posted = $expense->accountingEntry()->exists();
            if ($posted) {
                $actual = [];
                foreach (JournalEntry::whereMorphedTo('source', $expense)->with('lines.account')->get() as $entry) {
                    foreach ($entry->lines as $line) {
                        $code = $line->account->code;
                        $actual[$code] = ($actual[$code] ?? 0) + (int) round(((float) $line->debit - (float) $line->credit) * 100);
                    }
                }
                $expected = [];
                foreach ($oldLines as [$code, $debit, $credit]) {
                    $expected[$code] = ($expected[$code] ?? 0) + (int) round(($debit - $credit) * 100);
                }
                $actual = array_filter($actual);
                $expected = array_filter($expected);
                ksort($actual);
                ksort($expected);
                if ($actual !== $expected) {
                    $this->fail('The expense accounting differs from its document. Review it before editing.');
                }
            }
            ReceivedInvoiceIdentity::correctingExpense($expense->id, fn () => $expense->update([...$data, 'amount' => $amount, 'paid_amount' => $paid,
                'status' => $paid > 0 ? ($paid >= $amount ? 'paid' : 'partial') : (filled($data['due_date'] ?? null) && CarbonImmutable::parse($data['due_date'])->isPast() ? 'overdue' : 'pending')])
            );
            $expense->taxLines()->delete();
            $expense->taxLines()->createMany($taxLines);
            $expense->refresh();
            $newLines = $this->accounting->expenseLines($expense);
            $entries = [];
            if ($posted && (! PostedAccountSnapshot::equivalent($oldLines, $newLines) || $oldDate !== $newDate)) {
                // Preserve the original entry and every earlier correction. Compensate
                // the previous document values, then post the revised values.
                $event = Str::random(20);
                foreach ([[$oldDate, $oldLines, true], [$newDate, $newLines, false]] as [$date, $lines, $reverse]) {
                    $lines = collect($lines)->filter(fn ($line) => (float) $line[1] !== 0.0 || (float) $line[2] !== 0.0)
                        ->map(fn ($line) => ['account_code' => $line[0], 'debit' => $line[$reverse ? 2 : 1], 'credit' => $line[$reverse ? 1 : 2]])->values()->all();
                    if ($lines) {
                        $entries[] = $this->poster->post($date, $expense->number.' — correction', $lines, $request->user()->id, 'expense_correction', $expense, ($reverse ? 'undo_' : 'apply_').$event)->id;
                    }
                }
            } elseif (! $posted) {
                $this->accounting->syncExpense($expense, $request->user()->id);
            }
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'expense_corrected',
                'auditable_type' => $expense->getMorphClass(), 'auditable_id' => $expense->id,
                'old_values' => $before, 'new_values' => [...self::snapshot($expense), 'correction_reason' => $request->input('correction_reason'), 'journal_entry_ids' => $entries],
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'route' => $request->route()?->getName(), 'method' => $request->method()]);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['correction_reason' => __($message)]);
    }
}
