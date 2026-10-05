<?php

namespace App\Services\Tax;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseFiscalClassification;
use App\Models\FiscalYear;
use App\Models\TaxReturn;
use App\Services\Accounting\AccountingPeriodManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseFiscalReview
{
    public const CLASSES = ['pending', 'domestic_current', 'domestic_investment'];

    public function evidence(Expense $expense): array
    {
        $expense->loadMissing('taxLines');

        return [
            'version' => 'expense-fiscal-review-v1',
            'document' => $expense->only(['type', 'document_number', 'invoice_series', 'issuer_legal_name', 'issuer_tax_id', 'issuer_country',
                'expense_date', 'operation_date', 'currency_code', 'tax_regime', 'subtotal', 'tax_amount', 'amount',
                'withholding_amount', 'tax_calculation_version', 'tax_calculation_snapshot']),
            'tax_lines' => $expense->taxLines->map(fn ($line) => $line->only(['position', 'kind', 'taxable_base', 'tax_rate', 'tax_amount', 'deductible_percent', 'deductible_amount', 'exemption_reason']))->values()->all(),
        ];
    }

    public function fingerprint(Expense $expense): string
    {
        return hash('sha256', json_encode($this->evidence($expense), JSON_THROW_ON_ERROR));
    }

    public function revision(Expense $expense): string
    {
        $expense->loadMissing('fiscalClassification');

        return hash('sha256', json_encode([$this->fingerprint($expense), $expense->fiscalClassification?->attributesToArray()], JSON_THROW_ON_ERROR));
    }

    /** Eligibility is deliberately narrower than the accounting VAT report. */
    public function issues(Expense $expense): array
    {
        $expense->loadMissing('taxLines');
        $issues = [];
        if ($expense->type !== 'invoice' || ! filled($expense->document_number)
            || ! filled($expense->issuer_legal_name) || ! filled($expense->issuer_tax_id)) {
            $issues[] = 'A complete supplier invoice identity is required for fiscal classification.';
        }
        if ($expense->currency_code !== 'EUR' || $expense->tax_regime !== 'standard' || $expense->issuer_country !== 'ES'
            || (float) $expense->withholding_amount !== 0.0) {
            $issues[] = 'Only standard EUR invoices from Spain are supported by this review.';
        }
        $snapshot = $expense->tax_calculation_snapshot ?? [];
        $checksum = $snapshot['sha256'] ?? null;
        unset($snapshot['sha256']);
        if ($expense->tax_calculation_version !== SupplierInvoiceCalculator::VERSION || ! is_string($checksum)
            || ($snapshot['adapter_version'] ?? null) !== ExpenseDocumentaryCalculation::MODE
            || ($snapshot['result']['calculation_version'] ?? null) !== SupplierInvoiceCalculator::VERSION
            || ! hash_equals($checksum, hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)))) {
            $issues[] = 'Review and adopt documentary VAT evidence before fiscal classification.';
        }
        $lines = $expense->taxLines;
        if ($lines->isEmpty() || $lines->contains(fn ($line) => $line->kind !== 'vat'
            || ! in_array((float) $line->tax_rate, [4.0, 10.0, 21.0], true)
            || (float) $line->taxable_base < 0 || (float) $line->tax_amount <= 0
            || (float) $line->deductible_amount <= 0 || (float) $line->deductible_amount > (float) $line->tax_amount)) {
            $issues[] = 'Positive documentary VAT lines at supported rates and valid deductions are required.';
        }
        if ((int) round((float) $lines->sum('taxable_base') * 100) !== (int) round((float) $expense->subtotal * 100)
            || (int) round((float) $lines->sum('tax_amount') * 100) !== (int) round((float) $expense->tax_amount * 100)) {
            $issues[] = 'The tax breakdown must match the stored invoice totals.';
        }
        $expected = $snapshot['result']['lines'] ?? [];
        $matches = count($expected) === $lines->count();
        foreach ($lines->values() as $index => $line) {
            foreach (['taxable_base' => 'documentary_base', 'tax_amount' => 'tax_amount', 'deductible_amount' => 'deductible_amount',
                'tax_rate' => 'tax_rate', 'deductible_percent' => 'deductible_percent'] as $field => $source) {
                $value = $expected[$index][$source] ?? null;
                $scale = $field === 'tax_rate' ? 1000 : 100;
                if (! is_numeric($value) || (int) round((float) $value * $scale) !== (int) round((float) $line->$field * $scale)) {
                    $matches = false;
                }
            }
        }
        if (! $matches) {
            $issues[] = 'Review and adopt documentary VAT evidence before fiscal classification.';
        }

        return array_values(array_unique($issues));
    }

    public function isCurrent(Expense $expense): bool
    {
        $expense->loadMissing('fiscalClassification');
        $review = $expense->fiscalClassification;

        return $review && in_array($review->operation_class, ['domestic_current', 'domestic_investment'], true)
            && app(FiscalDeductionPeriod::class)->explicit($review)
            && $this->issues($expense) === [] && hash_equals($review->evidence_fingerprint, $this->fingerprint($expense));
    }

    public function protectionIssues(Expense $expense): array
    {
        $date = $expense->expense_date;
        $issues = [];
        if (! FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->where('status', 'open')->exists()) {
            $issues[] = 'The expense date must belong to an open fiscal year.';
        }
        if (AccountingPeriod::where('year', $date->year)->where('month', $date->month)->where('status', 'closed')->exists()) {
            $issues[] = 'The accounting period is closed.';
        }
        if (TaxReturn::where('tax_type', 'vat')->whereIn('status', ['prepared', 'filed'])
            ->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date)->exists()) {
            $issues[] = 'A prepared or filed tax return protects this expense.';
        }

        $expense->loadMissing('fiscalClassification');

        return [...$issues, ...app(FiscalDeductionPeriod::class)->protectionIssues(app(FiscalDeductionPeriod::class)->stored($expense->fiscalClassification))];
    }

    public function save(Expense $expense, array $data, Request $request): void
    {
        abort_unless($request->user()->hasRole('admin') && $request->user()->can('manage-tax'), 403);
        abort_unless(in_array($data['operation_class'], self::CLASSES, true), 422);
        DB::transaction(function () use ($expense, $data, $request) {
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);
            $expense->load(['taxLines', 'fiscalClassification']);
            if (! hash_equals($this->revision($expense), $data['revision'])) {
                $this->fail('The fiscal evidence or classification has changed. Reload before reviewing.');
            }
            $date = $expense->expense_date->toDateString();
            $year = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();
            if (! $year || $year->status !== 'open') {
                $this->fail('The expense date must belong to an open fiscal year.');
            }
            app(AccountingPeriodManager::class)->assertOpen($date);
            if (! filled($data['source_reference'] ?? null)) {
                $this->fail('A documentary review reference is required.');
            }
            $periods = app(FiscalDeductionPeriod::class);
            $period = $data['operation_class'] === 'pending' ? null : $periods->validate($expense->expense_date, $data);
            $periods->assertOpen($period);
            $periods->assertOpen($periods->stored($expense->fiscalClassification));
            // The year lock serializes with settlement posting and monthly closure.
            // Do not lock the return after the year: posting locks these in the opposite order.
            if (TaxReturn::where('tax_type', 'vat')->whereIn('status', ['prepared', 'filed'])
                ->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date)->exists()) {
                $this->fail('A prepared or filed tax return protects this expense.');
            }
            if ($data['operation_class'] !== 'pending') {
                if (! ($data['confirmed'] ?? false)) {
                    $this->fail('Confirm fiscal territory, invoice class and deductible VAT before saving.');
                }
                if ($this->issues($expense) !== []) {
                    $this->fail($this->issues($expense)[0]);
                }
            }
            $before = $expense->fiscalClassification?->attributesToArray();
            $review = ExpenseFiscalClassification::updateOrCreate(['expense_id' => $expense->id], [
                'operation_class' => $data['operation_class'], 'evidence_fingerprint' => $this->fingerprint($expense),
                'evidence_snapshot' => [...$this->evidence($expense), 'reviewer' => $request->user()->only(['id', 'name']), 'confirmation' => [
                    'domestic_vat_without_cash_accounting' => $data['operation_class'] !== 'pending',
                    'single_invoice_class' => $data['operation_class'] !== 'pending',
                    'deduction_period_confirmed' => $data['operation_class'] !== 'pending',
                ]], 'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(), 'reason' => $data['reason'], 'source_reference' => $data['source_reference'],
                'registered_on' => $period['registered_on'] ?? null,
                'deduction_year' => $period['deduction_year'] ?? null, 'deduction_quarter' => $period['deduction_quarter'] ?? null,
            ]);
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'expense_fiscal_classified',
                'auditable_type' => $expense->getMorphClass(), 'auditable_id' => $expense->id,
                'old_values' => $before, 'new_values' => $review->attributesToArray(),
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName(), 'method' => $request->method()]);
        });
    }

    /** Preserve supplier bases, quotas and partial deductions in the selected fiscal period. */
    public function rows(Expense $expense): array
    {
        if (! $this->isCurrent($expense)) {
            return [['direction' => 'received', 'date' => $expense->expense_date->toDateString(),
                'number' => $expense->document_number ?: $expense->number, 'party' => $expense->issuer_legal_name ?: '—',
                'tax_id' => $expense->issuer_tax_id, 'rate' => null, 'base' => (float) $expense->subtotal,
                'vat' => (float) $expense->tax_amount, 'total' => (float) $expense->amount,
                'source_type' => 'expense', 'source_id' => $expense->id, 'deductible_vat' => null, 'non_deductible_vat' => null]];
        }

        return $expense->taxLines->map(fn ($line) => [
            'direction' => 'received', 'date' => $expense->expense_date->toDateString(),
            'number' => $expense->document_number, 'party' => $expense->issuer_legal_name, 'tax_id' => $expense->issuer_tax_id,
            'rate' => (float) $line->tax_rate, 'base' => (float) $line->taxable_base, 'vat' => (float) $line->tax_amount,
            'total' => round((float) $line->taxable_base + (float) $line->tax_amount, 2),
            'source_type' => 'expense', 'source_id' => $expense->id, 'deductible_vat' => (float) $line->deductible_amount,
            'non_deductible_vat' => round((float) $line->tax_amount - (float) $line->deductible_amount, 2),
        ])->values()->all();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['operation_class' => __($message)]);
    }
}
