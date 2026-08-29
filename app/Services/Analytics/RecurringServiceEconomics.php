<?php

namespace App\Services\Analytics;

use App\Models\FinancialForecastItem;
use App\Models\ServiceCycleCommitment;
use App\Models\ServiceTicket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringServiceEconomics
{
    /** @return array<string, mixed> */
    public function build(bool $includeFinancials): array
    {
        $commitments = ServiceCycleCommitment::query()
            ->with(['maintenancePlan.installation:id,project_reference,site_name,service_type', 'approver:id,name', 'billing.invoice.payments', 'deviations:id,service_cycle_commitment_id,status,severity,category'])
            ->latest('period_starts_on')->limit(100)->get();
        if (! $includeFinancials) {
            return ['summary' => null, 'cycles' => [], 'definition' => __('Economic cycle data requires financial permission.')];
        }

        $planIds = $commitments->pluck('maintenance_plan_id')->unique();
        $tickets = ServiceTicket::query()->whereIn('maintenance_plan_id', $planIds)->whereNotNull('maintenance_plan_version')->get(['id', 'maintenance_plan_id', 'maintenance_plan_version', 'work_order_id', 'created_at']);
        $workOrderIds = $tickets->pluck('work_order_id')->filter()->unique();
        $timeCosts = DB::table('time_entries')->whereIn('work_order_id', $workOrderIds)->groupBy('work_order_id')
            ->selectRaw('work_order_id, COALESCE(SUM((COALESCE(duration_minutes, 0) / 60.0) * hourly_cost + travel_km * mileage_rate), 0) total')->pluck('total', 'work_order_id');
        $materialCosts = DB::table('stock_movement_lines')->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->whereIn('stock_movements.work_order_id', $workOrderIds)->where('stock_movements.type', 'issue')->groupBy('stock_movements.work_order_id')
            ->selectRaw('stock_movements.work_order_id, COALESCE(SUM(-stock_movement_lines.quantity * COALESCE(stock_movement_lines.unit_cost, 0)), 0) total')->pluck('total', 'work_order_id');
        $forecasts = FinancialForecastItem::query()->whereIn('maintenance_plan_id', $planIds)->get();

        $cycles = $commitments->map(function (ServiceCycleCommitment $commitment) use ($tickets, $timeCosts, $materialCosts, $forecasts): array {
            $cycleTickets = $tickets->where('maintenance_plan_id', $commitment->maintenance_plan_id)
                ->where('maintenance_plan_version', $commitment->maintenance_plan_version)
                ->filter(fn (ServiceTicket $ticket) => $ticket->created_at->betweenIncluded($commitment->period_starts_on->startOfDay(), $commitment->period_ends_on->endOfDay()));
            $orderIds = $cycleTickets->pluck('work_order_id')->filter();
            $completion = $commitment->completion_snapshot;
            $actualCost = $completion
                ? (float) ($completion['time']['labor_cost'] ?? 0) + (float) ($completion['time']['travel_cost'] ?? 0) + (float) ($completion['materials']['actual_cost'] ?? 0)
                : (float) $orderIds->sum(fn ($id) => (float) ($timeCosts[$id] ?? 0) + (float) ($materialCosts[$id] ?? 0));
            $cycleForecasts = $forecasts->where('maintenance_plan_id', $commitment->maintenance_plan_id)
                ->where('maintenance_plan_version', $commitment->maintenance_plan_version)
                ->filter(fn (FinancialForecastItem $item) => $item->due_on->betweenIncluded($commitment->period_starts_on, $commitment->period_ends_on));
            $invoicedRevenue = $commitment->billing?->invoice && $commitment->billing->invoice->status !== 'draft' && $commitment->billing->status !== 'credited'
                ? (float) $commitment->billing->net_amount
                : 0;
            $paidRatio = $commitment->billing?->invoice && $commitment->billing->status !== 'credited' && (float) $commitment->billing->invoice->amount > 0
                ? min(1, (float) $commitment->billing->invoice->payments->sum('amount') / (float) $commitment->billing->invoice->amount)
                : 0;
            $settledRevenue = $commitment->billing?->invoice
                ? $invoicedRevenue * $paidRatio
                : (float) $cycleForecasts->where('status', 'settled')->sum(fn ($item) => (float) ($item->actual_amount ?? $item->amount));
            $plannedCost = (float) $commitment->planned_labor_cost + (float) $commitment->planned_material_cost + (float) $commitment->planned_travel_cost;

            return [
                'id' => $commitment->id,
                'plan_id' => $commitment->maintenance_plan_id,
                'plan' => $commitment->maintenancePlan->name,
                'plan_version' => $commitment->maintenance_plan_version,
                'installation' => $commitment->maintenancePlan->installation?->project_reference ?: $commitment->maintenancePlan->installation?->site_name,
                'business_line' => $commitment->maintenancePlan->installation?->service_type,
                'period_starts_on' => $commitment->period_starts_on->toDateString(),
                'period_ends_on' => $commitment->period_ends_on->toDateString(),
                'status' => $commitment->status,
                'planned_revenue' => (float) $commitment->planned_revenue,
                'planned_cost' => round($plannedCost, 2),
                'planned_margin' => round((float) $commitment->planned_revenue - $plannedCost, 2),
                'settled_revenue' => round($settledRevenue, 2),
                'billing_status' => $commitment->billing?->status,
                'invoice_id' => $commitment->billing?->sales_document_id,
                'invoice_number' => $commitment->billing?->invoice?->number,
                'actual_cost' => round($actualCost, 2),
                'actual_margin' => round($settledRevenue - $actualCost, 2),
                'confirmed_deviations' => $commitment->deviations->whereIn('status', ['confirmed', 'resolved'])->count(),
                'quality_attention' => $commitment->deviations->whereIn('status', ['open', 'confirmed'])->isNotEmpty(),
                'completed_at' => $commitment->completed_at?->toIso8601String(),
                'tickets' => $cycleTickets->count(),
                'approved_by' => $commitment->approver?->name,
                'approved_at' => $commitment->approved_at?->toIso8601String(),
            ];
        });

        return [
            'summary' => $this->summary($cycles),
            'cycles' => $cycles->values()->all(),
            'definition' => __('Planned margin uses the approved contractual cycle snapshot. Actual margin uses net recurring invoice revenue in proportion to registered collections, with legacy settled forecasts as fallback, minus recorded labour, travel and issued materials for the same plan version and period.'),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $cycles */
    private function summary(Collection $cycles): array
    {
        $plannedRevenue = (float) $cycles->sum('planned_revenue');
        $settledRevenue = (float) $cycles->sum('settled_revenue');

        return [
            'cycles' => $cycles->count(),
            'planned_revenue' => round($plannedRevenue, 2),
            'planned_margin' => round((float) $cycles->sum('planned_margin'), 2),
            'settled_revenue' => round($settledRevenue, 2),
            'actual_cost' => round((float) $cycles->sum('actual_cost'), 2),
            'actual_margin' => round((float) $cycles->sum('actual_margin'), 2),
            'unsettled_cycles' => $cycles->where('settled_revenue', 0)->count(),
        ];
    }
}
