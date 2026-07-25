<?php

namespace App\Services\Finance;

use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\FinancialBudget;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use Illuminate\Support\Collection;

class FinancialAlertService
{
    public function __construct(private TreasuryForecast $forecast) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forYear(int $year, string $scenario = 'probable'): Collection
    {
        $budgets = FinancialBudget::where('year', $year)->get();
        $sales = SalesDocument::whereIn('type', ['invoice', 'credit_note'])
            ->whereNotNull('locked_at')
            ->whereYear('issue_date', $year)
            ->get();
        $purchases = PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])
            ->whereNot('status', 'review_required')
            ->whereYear('issue_date', $year)
            ->get();
        $expenses = Expense::whereYear('expense_date', $year)->get();
        $customerPayments = SalesDocumentPayment::whereYear('paid_at', $year)->get();
        $supplierPayments = SupplierPayment::whereYear('paid_at', $year)->get();

        $performance = collect(range(1, 12))->map(function (int $month) use ($sales, $purchases, $expenses, $budgets) {
            $revenue = (float) $sales->filter(fn ($document) => $document->issue_date->month === $month)->sum(fn ($document) => (float) $document->subtotal - (float) $document->discount_amount);
            $costs = (float) $purchases->filter(fn ($document) => $document->issue_date->month === $month)->sum(fn ($document) => (float) $document->subtotal + (float) $document->shipping_cost)
                + (float) $expenses->filter(fn ($expense) => $expense->expense_date->month === $month)->sum('subtotal');
            $budgetRevenue = (float) $budgets->where('month', $month)->where('category', 'revenue')->sum('amount');
            $budgetCosts = (float) $budgets->where('month', $month)->where('category', 'costs')->sum('amount');

            return [
                'month' => now()->month($month)->translatedFormat('M'),
                'result' => round($revenue - $costs, 2),
                'budgetResult' => round($budgetRevenue - $budgetCosts, 2),
            ];
        });
        $cashFlow = collect(range(1, 12))->map(function (int $month) use ($customerPayments, $supplierPayments, $budgets) {
            $in = (float) $customerPayments->filter(fn ($payment) => $payment->paid_at->month === $month)->sum('amount');
            $out = (float) $supplierPayments->filter(fn ($payment) => $payment->paid_at->month === $month)->sum('amount');
            $budgetIn = (float) $budgets->where('month', $month)->where('category', 'cash_in')->sum('amount');
            $budgetOut = (float) $budgets->where('month', $month)->where('category', 'cash_out')->sum('amount');

            return [
                'net' => round($in - $out, 2),
                'budgetNet' => round($budgetIn - $budgetOut, 2),
            ];
        });

        return $this->evaluate(
            $year,
            $performance,
            $cashFlow,
            $this->forecast->build($year, $scenario),
            CompanyProfile::firstOrFail(),
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    public function evaluate(int $year, Collection $performance, Collection $cashFlow, array $treasuryForecast, CompanyProfile $company): Collection
    {
        $lastCompletedMonth = $year < now()->year ? 12 : ($year === now()->year ? now()->month : 0);
        $operatingThreshold = (float) $company->operating_variance_alert_percent;
        $cashThreshold = (float) $company->cash_variance_alert_percent;
        $alerts = collect();

        foreach ($performance->take($lastCompletedMonth) as $index => $month) {
            $operatingVariance = (float) $month['result'] - (float) $month['budgetResult'];
            if ((float) $month['budgetResult'] !== 0.0 && $operatingVariance < 0) {
                $variancePercent = abs($operatingVariance) / abs((float) $month['budgetResult']) * 100;
                if ($variancePercent >= $operatingThreshold) {
                    $alerts->push($this->budgetAlert('operating', $index + 1, $month['month'], $operatingVariance, $variancePercent, $operatingThreshold));
                }
            }

            $cashMonth = $cashFlow[$index];
            $cashVariance = (float) $cashMonth['net'] - (float) $cashMonth['budgetNet'];
            if ((float) $cashMonth['budgetNet'] !== 0.0 && $cashVariance < 0) {
                $variancePercent = abs($cashVariance) / abs((float) $cashMonth['budgetNet']) * 100;
                if ($variancePercent >= $cashThreshold) {
                    $alerts->push($this->budgetAlert('cash', $index + 1, $month['month'], $cashVariance, $variancePercent, $cashThreshold));
                }
            }
        }

        $liquidityBuffer = (float) $company->liquidity_buffer_amount;
        if ((float) $treasuryForecast['lowestBalance'] < $liquidityBuffer) {
            $shortfall = (float) $treasuryForecast['lowestBalance'] - $liquidityBuffer;
            $alerts->push([
                'type' => 'liquidity',
                'month' => null,
                'monthLabel' => null,
                'severity' => (float) $treasuryForecast['lowestBalance'] < 0 ? 'critical' : 'warning',
                'variance' => round($shortfall, 2),
                'variancePercent' => null,
                'message' => __('Forecast minimum is :amount below the configured liquidity buffer.', ['amount' => number_format(abs($shortfall), 2)]),
            ]);
        }

        return $alerts
            ->sortByDesc(fn (array $alert) => ($alert['severity'] === 'critical' ? 1000000 : 0) + abs((float) $alert['variance']))
            ->values();
    }

    /** @return array<string, mixed> */
    private function budgetAlert(string $type, int $month, string $monthLabel, float $variance, float $variancePercent, float $threshold): array
    {
        return [
            'type' => $type,
            'month' => $month,
            'monthLabel' => $monthLabel,
            'severity' => $variancePercent >= $threshold * 2 ? 'critical' : 'warning',
            'variance' => round($variance, 2),
            'variancePercent' => round($variancePercent, 1),
            'message' => $type === 'operating'
                ? __('Operating result is below budget.')
                : __('Net cash is below budget.'),
        ];
    }
}
