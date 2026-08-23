<?php

namespace App\Services\Operations;

use App\Models\Installation;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\StockMovementLine;

class ProjectProfitability
{
    /** @return array{planned_revenue: float, actual_revenue: float, planned_cost: float, actual_cost: float, planned_margin: float, actual_margin: float, margin_variance: float, has_actuals: bool} */
    public function for(Installation $installation, ?Quote $quote = null): array
    {
        $acceptedQuote = $quote && (int) $quote->installation_id === (int) $installation->id
            ? $quote
            : $installation->quotes()
                ->where('status', 'accepted')
                ->latest()
                ->first(['subtotal', 'discount_amount']);
        $plannedRevenue = $acceptedQuote
            ? (float) $acceptedQuote->subtotal - (float) $acceptedQuote->discount_amount
            : (float) ($installation->estimated_budget ?: 0);
        $plannedMaterialCost = (float) $installation->materialRequirements()->selectRaw('sum(planned_quantity * planned_unit_cost) as total')->value('total');
        $plannedLabourCost = (float) $installation->workOrders()->sum('estimated_duration_minutes') / 60 * (float) config('work.default_hourly_cost');
        $actualDocuments = SalesDocument::query()
            ->where('installation_id', $installation->id)
            ->whereIn('type', ['invoice', 'credit_note'])
            ->whereNotIn('status', ['draft', 'cancelled']);
        $actualRevenue = (float) (clone $actualDocuments)->sum('subtotal')
            - (float) (clone $actualDocuments)->sum('discount_amount');
        $workOrders = $installation->workOrders()->with('timeEntries')->get();
        $actualLabourCost = $workOrders->sum(fn ($order) => $order->timeEntries->sum('cost'));
        $actualMaterials = StockMovementLine::query()
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->where('stock_movements.installation_id', $installation->id)
            ->where('stock_movements.type', 'issue');
        $actualMaterialCost = (float) (clone $actualMaterials)
            ->selectRaw('sum(-stock_movement_lines.quantity * coalesce(stock_movement_lines.unit_cost, 0)) as total')
            ->value('total');
        $plannedCost = $plannedMaterialCost + $plannedLabourCost;
        $actualCost = $actualLabourCost + $actualMaterialCost;
        $plannedMargin = $plannedRevenue - $plannedCost;
        $actualMargin = $actualRevenue - $actualCost;
        $hasActuals = (clone $actualDocuments)->exists()
            || $workOrders->contains(fn ($order) => $order->timeEntries->isNotEmpty())
            || (clone $actualMaterials)->exists();

        return [
            'planned_revenue' => round($plannedRevenue, 2),
            'actual_revenue' => round($actualRevenue, 2),
            'planned_cost' => round($plannedCost, 2),
            'actual_cost' => round($actualCost, 2),
            'planned_margin' => round($plannedMargin, 2),
            'actual_margin' => round($actualMargin, 2),
            'margin_variance' => round($actualMargin - $plannedMargin, 2),
            'has_actuals' => $hasActuals,
        ];
    }
}
