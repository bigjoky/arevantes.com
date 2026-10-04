<?php

namespace App\Services\Tax;

use App\Models\AccountingPeriod;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\TaxReturn;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseDocumentaryReview
{
    public function page(int $perPage = 50): LengthAwarePaginator
    {
        $years = FiscalYear::all(['starts_on', 'ends_on', 'status']);
        $closedPeriods = AccountingPeriod::where('status', 'closed')->get(['year', 'month'])
            ->mapWithKeys(fn (AccountingPeriod $period) => [sprintf('%04d-%02d', $period->year, $period->month) => true]);
        $protectedReturns = TaxReturn::whereIn('status', ['prepared', 'filed'])->get(['period_from', 'period_to']);

        return Expense::query()
            ->whereNull('tax_calculation_version')
            ->with(['category:id,name', 'supplier:id,legal_name,trade_name', 'taxLines'])
            ->withSum('payments', 'amount')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($perPage)
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
                    'edit_url' => route('expenses.edit', ['expense' => $expense, 'documentary_review' => 1]),
                ];
            });
    }
}
