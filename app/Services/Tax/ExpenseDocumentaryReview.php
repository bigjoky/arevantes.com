<?php

namespace App\Services\Tax;

use App\Models\AccountingPeriod;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\TaxReturn;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ExpenseDocumentaryReview
{
    public function __construct(private ExpenseDocumentaryCalculation $calculator) {}

    public function page(string $filter = 'all', int $perPage = 50): LengthAwarePaginator
    {
        $years = FiscalYear::all(['starts_on', 'ends_on', 'status']);
        $closedPeriods = AccountingPeriod::where('status', 'closed')->get(['year', 'month'])
            ->mapWithKeys(fn (AccountingPeriod $period) => [sprintf('%04d-%02d', $period->year, $period->month) => true]);
        $protectedReturns = TaxReturn::whereIn('status', ['prepared', 'filed'])->get(['period_from', 'period_to']);

        return Expense::query()
            ->whereNull('tax_calculation_version')
            ->when($filter === 'with_payments', fn ($query) => $query->where(fn ($query) => $query
                ->where('status', 'paid')->orWhereHas('payments')))
            ->when($filter === 'without_payments', fn ($query) => $query->where('status', '!=', 'paid')->whereDoesntHave('payments'))
            ->with(['category:id,name', 'supplier:id,legal_name,trade_name', 'taxLines'])
            ->withSum('payments', 'amount')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Expense $expense) use ($years, $closedPeriods, $protectedReturns): array {
                $date = CarbonImmutable::parse($expense->expense_date);
                $reasons = [];
                $paid = (float) ($expense->payments_sum_amount ?? 0);
                $year = $years->first(fn (FiscalYear $year) => $year->starts_on->lte($date) && $year->ends_on->gte($date));

                if ($expense->status === 'paid' || $paid > 0) {
                    $reasons[] = 'This expense has registered payments.';
                }
                if ($expense->currency_code !== 'EUR') {
                    $reasons[] = 'Only EUR expenses are supported.';
                }
                if (! in_array($expense->tax_regime, ['standard', 'exempt', 'non_subject'], true)) {
                    $reasons[] = 'This tax regime is not supported yet.';
                }
                if ((float) $expense->withholding_rate !== 0.0 || (float) $expense->withholding_amount !== 0.0) {
                    $reasons[] = 'Withholdings are not supported yet.';
                }
                if ($expense->taxLines->isEmpty() || $expense->taxLines->count() > 20) {
                    $reasons[] = 'A bounded VAT tax breakdown is required.';
                } elseif ($expense->taxLines->contains(fn ($line) => $line->kind !== 'vat')) {
                    $reasons[] = 'Equivalence surcharges are not supported yet.';
                }
                if (! $year || $year->status !== 'open') {
                    $reasons[] = 'The fiscal year is not open.';
                }
                if ($closedPeriods->has($date->format('Y-m'))) {
                    $reasons[] = 'The accounting period is closed.';
                }
                if ($protectedReturns->contains(fn (TaxReturn $return) => $return->period_from->lte($date) && $return->period_to->gte($date))) {
                    $reasons[] = 'A prepared or filed tax return protects this expense.';
                }

                return [
                    'id' => $expense->id,
                    'number' => $expense->number,
                    'issuer' => $expense->issuer_legal_name ?: $expense->supplier?->trade_name ?: $expense->supplier?->legal_name ?: $expense->merchant_name,
                    'category' => $expense->category?->name,
                    'expense_date' => $expense->expense_date->toDateString(),
                    'amount' => $expense->amount,
                    'status' => $expense->status,
                    'eligible' => $reasons === [],
                    'reasons' => $reasons,
                    'comparison' => $this->comparison($expense),
                    'edit_url' => route('expenses.edit', ['expense' => $expense, 'documentary_review' => 1]),
                ];
            });
    }

    private function comparison(Expense $expense): ?array
    {
        try {
            $calculation = $this->calculator->calculate([
                'currency_code' => $expense->currency_code,
                'tax_regime' => $expense->tax_regime,
                'withholding_rate' => $expense->withholding_rate,
                'withholding_amount' => $expense->withholding_amount,
                'tax_lines' => $expense->taxLines->map(fn ($line) => [
                    'calculation_id' => 'historical-'.$expense->id.'-'.$line->id,
                    'kind' => $line->kind,
                    'taxable_base' => $line->taxable_base,
                    'tax_rate' => $line->tax_rate,
                    'tax_amount' => $line->tax_amount,
                    'deductible_percent' => $line->deductible_percent,
                    'exemption_reason' => $line->exemption_reason,
                ])->all(),
            ], 'Historical documentary calculation review');
        } catch (ValidationException) {
            return null;
        }

        $result = $calculation['tax_calculation_snapshot']['result'];
        $calculatedTax = BigDecimal::zero();
        $adjustment = BigDecimal::zero();
        foreach ($result['tax_groups'] as $group) {
            $calculatedTax = $calculatedTax->plus($group['calculated_tax']);
            $adjustment = $adjustment->plus($group['tax_adjustment']);
        }
        $paid = BigDecimal::of((string) ($expense->payments_sum_amount ?? '0'))->toScale(2);

        return [
            'subtotal' => $result['totals']['subtotal'],
            'calculated_tax' => (string) $calculatedTax->toScale(2),
            'documentary_tax' => $result['totals']['tax_amount'],
            'adjustment' => (string) $adjustment->toScale(2),
            'total' => $result['totals']['total'],
            'paid' => (string) $paid,
            'outstanding' => (string) BigDecimal::of($result['totals']['total'])->minus($paid)->toScale(2),
        ];
    }
}
