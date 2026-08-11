<?php

namespace App\Services\Finance;

use App\Models\FinancialBudget;
use App\Models\FinancialCategory;
use App\Models\FinancialForecastItem;
use App\Models\JournalLine;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FinancialTaxonomyComparison
{
    /** @return array{rows: Collection<int, array<string, mixed>>, selectedCategory: ?array<string, mixed>, evidence: Collection<int, array<string, mixed>>} */
    public function build(int $year, ?int $selectedCategoryId = null): array
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $end = $start->endOfYear();
        $categories = FinancialCategory::query()->orderBy('reporting_group')->orderBy('name')->get();
        $budgets = FinancialBudget::query()
            ->where('year', $year)
            ->whereNotNull('financial_category_id')
            ->selectRaw('financial_category_id, sum(amount) as total')
            ->groupBy('financial_category_id')
            ->pluck('total', 'financial_category_id');
        $forecasts = FinancialForecastItem::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('due_on', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('financial_category_id')
            ->selectRaw('financial_category_id, sum(coalesce(baseline_amount, amount)) as total')
            ->groupBy('financial_category_id')
            ->pluck('total', 'financial_category_id');
        $actuals = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->whereBetween('journal_entries.entry_date', [$start->toDateString(), $end->toDateString()])
            ->where('journal_entries.entry_kind', '!=', 'closing')
            ->whereNotNull('ledger_accounts.financial_category_id')
            ->whereIn('ledger_accounts.type', ['income', 'expense'])
            ->selectRaw("ledger_accounts.financial_category_id, sum(case when ledger_accounts.type = 'income' then journal_lines.credit - journal_lines.debit else journal_lines.debit - journal_lines.credit end) as total")
            ->groupBy('ledger_accounts.financial_category_id')
            ->pluck('total', 'ledger_accounts.financial_category_id');

        $this->addCashActual($actuals, $categories, 'customer_collections', SalesDocumentPayment::whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])->sum('amount'));
        $this->addCashActual($actuals, $categories, 'supplier_payments', SupplierPayment::whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])->sum('amount'));

        $rows = $categories->map(function (FinancialCategory $category) use ($year, $budgets, $forecasts, $actuals) {
            $budget = round((float) ($budgets[$category->id] ?? 0), 2);
            $forecast = round((float) ($forecasts[$category->id] ?? 0), 2);
            $actual = round((float) ($actuals[$category->id] ?? 0), 2);

            return [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'reportingGroup' => $category->reporting_group,
                'isActive' => $category->is_active,
                'budget' => $budget,
                'forecast' => $forecast,
                'actual' => $actual,
                'budgetVariance' => round($actual - $budget, 2),
                'forecastVariance' => round($actual - $forecast, 2),
                'actualHref' => in_array($category->reporting_group, ['revenue', 'costs'], true)
                    ? '/accounting?year='.$year.'&financial_category='.$category->id
                    : '/finance?year='.$year.'&taxonomy='.$category->id.'#taxonomy-evidence',
            ];
        })->filter(fn (array $row) => $row['isActive'] || $row['budget'] != 0 || $row['forecast'] != 0 || $row['actual'] != 0)->values();
        $selected = $selectedCategoryId ? $categories->firstWhere('id', $selectedCategoryId) : null;

        return [
            'rows' => $rows,
            'selectedCategory' => $selected ? ['id' => $selected->id, 'code' => $selected->code, 'name' => $selected->name, 'reportingGroup' => $selected->reporting_group] : null,
            'evidence' => $selected ? $this->evidence($selected, $start, $end) : collect(),
        ];
    }

    private function addCashActual(Collection $actuals, Collection $categories, string $code, mixed $amount): void
    {
        $category = $categories->firstWhere('code', $code);
        if ($category) {
            $actuals[$category->id] = round((float) $amount, 2);
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function evidence(FinancialCategory $category, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        if ($category->code === 'customer_collections') {
            return SalesDocumentPayment::with('document:id,number')
                ->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])
                ->latest('paid_at')
                ->limit(200)
                ->get()
                ->map(fn (SalesDocumentPayment $payment) => [
                    'id' => 'customer-payment-'.$payment->id,
                    'date' => $payment->paid_at->toDateString(),
                    'reference' => $payment->document?->number ?: (string) $payment->id,
                    'description' => __('Customer collection'),
                    'amount' => round((float) $payment->amount, 2),
                    'href' => $payment->sales_document_id ? '/sales/'.$payment->sales_document_id : null,
                ]);
        }
        if ($category->code === 'supplier_payments') {
            return SupplierPayment::with(['expense:id,number', 'purchaseDocument:id,number'])
                ->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])
                ->latest('paid_at')
                ->limit(200)
                ->get()
                ->map(fn (SupplierPayment $payment) => [
                    'id' => 'supplier-payment-'.$payment->id,
                    'date' => $payment->paid_at->toDateString(),
                    'reference' => $payment->expense?->number ?? $payment->purchaseDocument?->number ?? (string) $payment->id,
                    'description' => __('Supplier payment'),
                    'amount' => round((float) $payment->amount, 2),
                    'href' => $payment->expense_id ? '/expenses/'.$payment->expense_id : ($payment->purchase_document_id ? '/purchases/'.$payment->purchase_document_id : null),
                ]);
        }

        return collect();
    }
}
