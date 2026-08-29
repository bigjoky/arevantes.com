<?php

namespace App\Services\Analytics;

use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Opportunity;
use App\Models\ServiceCycleCommitment;
use App\Models\ServiceCycleDeviation;
use App\Models\ServiceTicket;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringServiceHealth
{
    /** @return array<string, mixed> */
    public function build(AnalyticsFilter $filter, bool $includeFinancials): array
    {
        $installations = $this->installations($filter)->get(['id', 'service_type']);
        $installationIds = $installations->pluck('id');
        $plans = MaintenancePlan::query()
            ->with('installation:id,service_type')
            ->whereIn('installation_id', $installationIds)
            ->where('is_active', true)
            ->get();
        $expiring = $plans->filter(fn (MaintenancePlan $plan) => $plan->contract_ends_on?->betweenIncluded($filter->from, $filter->to) ?? false);
        $renewals = Opportunity::query()
            ->whereIn('installation_id', $installationIds)
            ->where('commercial_kind', 'renewal')
            ->whereBetween('renewal_due_on', [$filter->from, $filter->to])
            ->get(['id', 'stage']);
        $tickets = ServiceTicket::query()
            ->whereIn('installation_id', $installationIds)
            ->whereNotNull('maintenance_plan_id')
            ->whereBetween('created_at', [$filter->from, $filter->to])
            ->get(['id', 'work_order_id', 'first_response_due_at', 'first_responded_at', 'resolution_due_at', 'resolved_at', 'reopened_count']);
        $workOrderIds = $tickets->pluck('work_order_id')->filter()->unique();
        $closedRenewals = $renewals->whereIn('stage', ['won', 'lost']);
        $completedCycles = ServiceCycleCommitment::query()
            ->whereHas('maintenancePlan', fn (Builder $query) => $query->whereIn('installation_id', $installationIds))
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$filter->from, $filter->to])
            ->count();
        $qualityDeviations = ServiceCycleDeviation::query()
            ->with('assets:id')
            ->whereHas('commitment.maintenancePlan', fn (Builder $query) => $query->whereIn('installation_id', $installationIds))
            ->whereIn('status', ['confirmed', 'resolved'])
            ->whereBetween('confirmed_at', [$filter->from, $filter->to])
            ->get();
        $qualityPenalty = $qualityDeviations->sum(fn (ServiceCycleDeviation $deviation) => match ($deviation->severity) {
            'critical' => 12, 'high' => 7, default => 3,
        });

        return [
            'summary' => [
                'installed_base' => $installations->count(),
                'active_contracts' => $plans->count(),
                'attached_installations' => $plans->pluck('installation_id')->unique()->count(),
                'attach_rate' => $this->percent($plans->pluck('installation_id')->unique()->count(), $installations->count()),
                'annual_recurring_value' => round((float) $plans->sum(fn (MaintenancePlan $plan) => (float) ($plan->billing_amount ?: 0) * (12 / max(1, (int) ($plan->billing_frequency_months ?: 12)))), 2),
                'renewals_due' => $expiring->count(),
                'renewals_prepared' => $expiring->whereNotNull('renewal_opportunity_id')->count(),
                'renewal_conversion' => $this->percent($closedRenewals->where('stage', 'won')->count(), $closedRenewals->count()),
                'response_sla_attainment' => $this->attainment($tickets, 'first_responded_at', 'first_response_due_at', $filter->to),
                'resolution_sla_attainment' => $this->attainment($tickets, 'resolved_at', 'resolution_due_at', $filter->to),
                'first_time_fix_rate' => $this->percent($tickets->whereNotNull('resolved_at')->where('reopened_count', 0)->count(), $tickets->whereNotNull('resolved_at')->count()),
                'service_quality_score' => $completedCycles > 0 ? max(0, round(100 - ($qualityPenalty / $completedCycles), 1)) : null,
                'confirmed_quality_deviations' => $qualityDeviations->count(),
                'quality_affected_assets' => $qualityDeviations->flatMap->assets->pluck('id')->unique()->count(),
                'actual_service_cost' => $includeFinancials ? $this->actualCost($workOrderIds, $filter) : null,
            ],
            'by_line' => $installations->groupBy('service_type')->map(function (Collection $lineInstallations, string $line) use ($plans): array {
                $linePlans = $plans->where('installation.service_type', $line);

                return [
                    'service_type' => $line,
                    'installed_base' => $lineInstallations->count(),
                    'active_contracts' => $linePlans->count(),
                    'attach_rate' => $this->percent($linePlans->pluck('installation_id')->unique()->count(), $lineInstallations->count()),
                    'annual_recurring_value' => round((float) $linePlans->sum(fn (MaintenancePlan $plan) => (float) ($plan->billing_amount ?: 0) * (12 / max(1, (int) ($plan->billing_frequency_months ?: 12)))), 2),
                ];
            })->sortByDesc('annual_recurring_value')->values()->all(),
            'definitions' => [
                'attach_rate' => __('Completed installations with at least one active service plan divided by the completed installed base at the reporting cut.'),
                'renewal_conversion' => __('Won renewal opportunities divided by won plus lost renewals whose renewal date falls within the selected period.'),
                'sla_attainment' => __('Service-plan tickets completed within their recorded response or resolution deadline.'),
                'first_time_fix_rate' => __('Resolved service-plan tickets that have never been reopened.'),
                'service_quality_score' => __('Completed recurring cycles start at 100 points. Confirmed deviations reduce the score according to their severity; dismissed findings never affect it.'),
                'actual_service_cost' => __('Recorded labour, travel and issued material cost on work orders linked to service-plan tickets in the selected period.'),
            ],
        ];
    }

    private function installations(AnalyticsFilter $filter): Builder
    {
        return Installation::query()
            ->where('status', 'completed')
            ->when($filter->commercialId, fn (Builder $query, int $id) => $query->where('commercial_user_id', $id))
            ->when($filter->projectId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->when($filter->user->hasRole('technician'), fn (Builder $query) => $query->where('technician_user_id', $filter->user->id))
            ->when($filter->restrictsToOwnCommercialScope(), fn (Builder $query) => $query->where('commercial_user_id', $filter->user->id));
    }

    private function attainment(Collection $tickets, string $actual, string $due, CarbonInterface $cutoff): float
    {
        $effectiveCutoff = $cutoff->isFuture() ? now() : $cutoff;
        $measured = $tickets->whereNotNull($due)->filter(fn (ServiceTicket $ticket) => $ticket->{$actual} !== null || $ticket->{$due}->lte($effectiveCutoff));

        return $this->percent($measured->filter(fn (ServiceTicket $ticket) => $ticket->{$actual}?->lte($ticket->{$due}) ?? false)->count(), $measured->count());
    }

    private function actualCost(Collection $workOrderIds, AnalyticsFilter $filter): float
    {
        if ($workOrderIds->isEmpty()) {
            return 0.0;
        }
        $labour = DB::table('time_entries')->whereIn('work_order_id', $workOrderIds)->whereBetween('started_at', [$filter->from, $filter->to])
            ->selectRaw('COALESCE(SUM((COALESCE(duration_minutes, 0) / 60.0) * hourly_cost + travel_km * mileage_rate), 0) total')->value('total');
        $materials = DB::table('stock_movement_lines')->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->whereIn('stock_movements.work_order_id', $workOrderIds)->where('stock_movements.type', 'issue')->whereBetween('stock_movements.posted_at', [$filter->from, $filter->to])
            ->selectRaw('COALESCE(SUM(-stock_movement_lines.quantity * COALESCE(stock_movement_lines.unit_cost, 0)), 0) total')->value('total');

        return round((float) $labour + (float) $materials, 2);
    }

    private function percent(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
    }
}
