<?php

namespace App\Services\Accounting;

use App\Models\AuditLog;
use App\Models\FiscalYear;
use App\Models\Installation;
use App\Models\JournalEntry;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentLine;
use App\Models\TaxReturn;
use App\Services\Documents\SalesDocumentConversion;
use App\Services\Finance\AcceptancePaymentManager;
use App\Services\Quotes\QuoteCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssuedInvoiceCorrection
{
    public function __construct(private AccountingSynchronizer $accounting, private AccountingPeriodManager $periods, private JournalPoster $poster) {}

    public static function eligible(SalesDocument $document): bool
    {
        return $document->type === 'invoice' && $document->locked_at && in_array($document->status, ['issued', 'partial', 'paid', 'overdue'], true);
    }

    public static function snapshot(SalesDocument $document): array
    {
        return [...$document->attributesToArray(), 'lines' => $document->lines()->get()->toArray(), 'payments' => $document->payments()->get()->toArray()];
    }

    public static function revision(SalesDocument $document): string
    {
        return hash('sha256', json_encode(self::snapshot($document), JSON_THROW_ON_ERROR));
    }

    public function correct(SalesDocument $invoice, array $data, Request $request): void
    {
        abort_unless($request->user()->hasRole('admin') && $request->user()->can('manage-admin'), 403);
        DB::transaction(function () use ($invoice, $data, $request) {
            $quote = $invoice->quote_id ? Quote::lockForUpdate()->findOrFail($invoice->quote_id) : null;
            $invoice = SalesDocument::lockForUpdate()->findOrFail($invoice->id);
            abort_unless(self::eligible($invoice), 422);
            if (! hash_equals(self::revision($invoice), $request->string('revision')->toString())) {
                $this->fail('The invoice has changed. Reload it before editing.');
            }
            if ($invoice->childDocuments()->where('type', 'credit_note')->exists()) {
                $this->fail('This invoice already has a credit note. Review the correction before editing.');
            }
            $before = self::snapshot($invoice);
            $oldDate = $invoice->issue_date->toDateString();
            $newDate = Carbon::parse($data['issue_date'])->toDateString();
            $data['issue_date'] = $newDate;
            $dates = array_unique([$oldDate, $newDate]);
            sort($dates);
            foreach ($dates as $date) {
                $this->assertDateOpen($date);
            }
            $totals = $this->calculate($invoice, $data['lines']);
            $payments = $invoice->payments()->lockForUpdate()->get();
            $paid = round((float) $payments->sum('amount'), 2);
            if ($totals['amount'] < $paid) {
                $this->fail('The invoice total cannot be less than its registered payments.');
            }
            if ($quote) {
                $others = (float) SalesDocument::where('quote_id', $quote->id)->where('type', 'invoice')->whereKeyNot($invoice->id)->sum('amount');
                if (round($others + $totals['amount'], 2) > (float) $quote->amount && $totals['amount'] > (float) $invoice->amount) {
                    $this->fail('The invoices cannot exceed the accepted proposal total.');
                }
            }
            $installation = isset($data['installation_id']) ? Installation::find($data['installation_id']) : null;
            if ($installation?->person_id && (int) $installation->person_id !== (int) $data['person_id']) {
                $this->fail('The installation does not belong to the selected customer.');
            }
            $customer = Person::findOrFail($data['person_id']);
            $oldCustomerAccount = $invoice->customer?->receivable_account_code ?: '430';
            $newCustomerAccount = $customer->receivable_account_code ?: '430';
            $oldLines = $this->accounting->salesDocumentLines($invoice);
            $posted = $invoice->accountingEntry()->exists();
            if ($posted) {
                $this->assertAccountingMatches($invoice, $oldLines);
            }
            if ($oldCustomerAccount !== $newCustomerAccount) {
                foreach ($payments as $payment) {
                    $this->assertDateOpen($payment->paid_at->toDateString());
                    $cashAccount = $payment->method === 'cash' ? '570' : '572';
                    if ($payment->accountingEntry()->exists()) {
                        $this->assertAccountingMatches($payment, [[$cashAccount, (float) $payment->amount, 0], [$oldCustomerAccount, 0, (float) $payment->amount]]);
                    }
                }
            }
            if ($quote && (float) $quote->amount > 0) {
                $data['billing_percentage'] = round($totals['amount'] / (float) $quote->amount * 100, 2);
            }
            unset($data['lines']);
            $invoice->fill([...$data, ...array_diff_key($totals, ['lines' => true]), 'customer_snapshot' => $customer->customerSnapshot(), 'paid_amount' => $paid])->save();
            $kept = [];
            foreach ($totals['lines'] as $lineData) {
                $id = $lineData['id'] ?? null;
                unset($lineData['id']);
                $line = $id ? $invoice->lines()->findOrFail($id) : $invoice->lines()->make();
                $line->fill($lineData)->save();
                $kept[] = $line->id;
            }
            $invoice->lines()->whereNotIn('id', $kept)->delete();
            $invoice->refresh();
            $newLines = $this->accounting->salesDocumentLines($invoice);
            $entries = [];
            if ($posted && ($oldLines !== $newLines || $oldDate !== $newDate)) {
                $entries = $this->postCorrection($invoice, $oldDate, $oldLines, $newDate, $newLines, $request->user()->id);
            } elseif (! $posted) {
                $this->accounting->syncSalesDocument($invoice, $request->user()->id);
            }
            if ($oldCustomerAccount !== $newCustomerAccount) {
                foreach ($payments as $payment) {
                    $cashAccount = $payment->method === 'cash' ? '570' : '572';
                    if ($payment->accountingEntry()->exists()) {
                        $entries = [...$entries, ...$this->postCorrection($payment, $payment->paid_at->toDateString(), [[$cashAccount, (float) $payment->amount, 0], [$oldCustomerAccount, 0, (float) $payment->amount]], $payment->paid_at->toDateString(), [[$cashAccount, (float) $payment->amount, 0], [$newCustomerAccount, 0, (float) $payment->amount]], $request->user()->id)];
                    } else {
                        $payment->unsetRelation('document');
                        $this->accounting->syncCustomerPayment($payment, $request->user()->id);
                    }
                }
            }
            $status = $paid >= (float) $invoice->amount ? 'paid' : ($paid > 0 ? 'partial' : ($invoice->due_date?->isPast() ? 'overdue' : 'issued'));
            $invoice->update(['status' => $status]);
            $net = round((float) $invoice->subtotal - (float) $invoice->discount_amount, 2);
            $invoice->recurringServiceBilling?->update(['status' => $status, 'net_amount' => $net, 'tax_amount' => $invoice->tax_amount,
                'gross_amount' => $invoice->amount, 'tax_percent' => $net > 0 ? round((float) $invoice->tax_amount / $net * 100, 2) : 0,
                'due_on' => $invoice->due_date ?? $invoice->issue_date]);
            if ($invoice->is_acceptance_invoice && $quote) {
                app(AcceptancePaymentManager::class)->refresh($quote);
            }
            if ($invoice->sourceDocument) {
                app(SalesDocumentConversion::class)->refresh($invoice->sourceDocument);
            }
            $invoice->deliveries()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'issued_invoice_corrected', 'auditable_type' => $invoice->getMorphClass(), 'auditable_id' => $invoice->id,
                'old_values' => $before, 'new_values' => [...self::snapshot($invoice), 'correction_reason' => $request->input('correction_reason'), 'journal_entry_ids' => $entries],
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'route' => $request->route()?->getName(), 'method' => $request->method()]);
        });
    }

    private function calculate(SalesDocument $invoice, array $inputs): array
    {
        $existing = $invoice->lines()->with('sourceLine')->get()->keyBy('id');
        $totals = app(QuoteCalculator::class)->calculate($inputs);
        foreach ($totals['lines'] as &$line) {
            $original = isset($line['id']) ? $existing->get($line['id']) : null;
            if (! $original) {
                continue;
            }
            if ($original->sourceLine) {
                $otherQuantity = (float) SalesDocumentLine::where('source_line_id', $original->source_line_id)->where('sales_document_id', '!=', $invoice->id)->whereHas('document', fn ($query) => $query->where('type', 'invoice'))->sum('quantity');
                if (round((float) $line['quantity'] + $otherQuantity, 3) > (float) $original->sourceLine->quantity) {
                    $this->fail('The invoice quantity exceeds the quantity available on its source document.');
                }
            }
            $unchanged = collect(['quantity', 'unit_price', 'discount_percent', 'tax_percent'])->every(fn ($field) => (float) $line[$field] === (float) $original->{$field});
            if ($unchanged) {
                foreach (['subtotal', 'discount_amount', 'tax_amount', 'total'] as $field) {
                    $line[$field] = (float) $original->{$field};
                }
            }
        }
        unset($line);
        foreach (['subtotal', 'discount_amount', 'tax_amount', 'amount'] as $field) {
            $totals[$field] = round((float) collect($totals['lines'])->sum($field === 'amount' ? 'total' : $field), 2);
        }

        return $totals;
    }

    private function assertDateOpen(string $date): void
    {
        $year = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();
        if (! $year || $year->status !== 'open') {
            $this->fail('The invoice date must belong to an open fiscal year.');
        }
        $this->periods->assertOpen($date);
        if (TaxReturn::whereIn('status', ['prepared', 'filed'])->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date)->lockForUpdate()->exists()) {
            $this->fail('A prepared or filed tax return protects this invoice.');
        }
    }

    private function assertAccountingMatches(Model $source, array $expectedLines): void
    {
        $actual = [];
        foreach (JournalEntry::whereMorphedTo('source', $source)->with('lines.account')->get() as $entry) {
            foreach ($entry->lines as $line) {
                $code = $line->account->code;
                $actual[$code] = ($actual[$code] ?? 0) + (int) round(((float) $line->debit - (float) $line->credit) * 100);
            }
        }
        $expected = [];
        foreach ($expectedLines as [$code, $debit, $credit]) {
            $expected[$code] = ($expected[$code] ?? 0) + (int) round(($debit - $credit) * 100);
        }
        $actual = array_filter($actual);
        $expected = array_filter($expected);
        ksort($actual);
        ksort($expected);
        if ($actual !== $expected) {
            $this->fail('The invoice accounting differs from its document. Review it before editing.');
        }
    }

    private function postCorrection(Model $source, string $oldDate, array $oldLines, string $newDate, array $newLines, int $userId): array
    {
        $event = Str::random(20);
        $entries = [];
        foreach ([[$oldDate, $oldLines, true], [$newDate, $newLines, false]] as [$date, $lines, $reverse]) {
            $lines = collect($lines)->filter(fn ($line) => (float) $line[1] !== 0.0 || (float) $line[2] !== 0.0)
                ->map(fn ($line) => ['account_code' => $line[0], 'debit' => $line[$reverse ? 2 : 1], 'credit' => $line[$reverse ? 1 : 2]])->values()->all();
            if ($lines) {
                $entries[] = $this->poster->post($date, $source instanceof SalesDocument ? $source->number.' — correction' : 'Receipt correction', $lines, $userId, 'invoice_correction', $source, ($reverse ? 'undo_' : 'apply_').$event)->id;
            }
        }

        return $entries;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['correction_reason' => __($message)]);
    }
}
