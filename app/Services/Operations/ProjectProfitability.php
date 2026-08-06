<?php

namespace App\Services\Operations;

use App\Models\Installation;
use App\Models\SalesDocument;
use App\Models\StockMovementLine;

class ProjectProfitability
{
    /** @return array<string, float> */
    public function for(Installation $installation): array
    {
        $plannedRevenue = (float) ($installation->quotes()->where('status', 'accepted')->latest()->value('amount') ?: $installation->estimated_budget ?: 0);
        $plannedMaterialCost = (float) $installation->materialRequirements()->selectRaw('sum(planned_quantity * planned_unit_cost) as total')->value('total');
        $plannedLabourCost = (float) $installation->workOrders()->sum('estimated_duration_minutes') / 60 * (float) config('work.default_hourly_cost');
        $actualRevenue = (float) SalesDocument::query()->where('installation_id', $installation->id)->where('type', 'invoice')->whereNotIn('status', ['draft', 'cancelled'])->sum('subtotal');
        $actualLabourCost = $installation->workOrders()->with('timeEntries')->get()->sum(fn ($order) => $order->timeEntries->sum('cost'));
        $actualMaterialCost = (float) StockMovementLine::query()->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->where('stock_movements.installation_id', $installation->id)->where('stock_movements.type', 'issue')
            ->selectRaw('sum(-stock_movement_lines.quantity * coalesce(stock_movement_lines.unit_cost, 0)) as total')->value('total');
        $plannedCost = $plannedMaterialCost + $plannedLabourCost;
        $actualCost = $actualLabourCost + $actualMaterialCost;

        return [
            'planned_revenue' => round($plannedRevenue, 2),
            'actual_revenue' => round($actualRevenue, 2),
            'planned_cost' => round($plannedCost, 2),
            'actual_cost' => round($actualCost, 2),
            'planned_margin' => round($plannedRevenue - $plannedCost, 2),
            'actual_margin' => round($actualRevenue - $actualCost, 2),
        ];
    }
}
