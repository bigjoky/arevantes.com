<?php

namespace App\Services\Analytics;

use App\Models\FinancialBudget;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /** @return Collection<int, array<string, mixed>> */
    public function metrics(AnalyticsFilter $filter): Collection
    {
        return collect(config('analytics.metrics'))
            ->filter(fn (array $definition) => in_array($filter->area, $definition['areas'], true))
            ->map(fn (array $definition, string $key) => $this->metric($key, $definition, $filter))
            ->values();
    }

    /** @return array<string, mixed> */
    public function metric(string $key, array $definition, AnalyticsFilter $filter): array
    {
        abort_unless(array_key_exists($key, config('analytics.metrics')), 404);
        abort_unless(in_array($filter->area, $definition['areas'], true), 403);
        [$previousFrom, $previousTo] = $filter->previousPeriod();
        $value = $this->value($key, $filter, $filter->from, $filter->to);
        $previous = $this->value($key, $filter, $previousFrom, $previousTo);
        $target = $this->target($definition, $filter);
        $quality = $this->quality($key, $filter);

        return [
            'key' => $key,
            ...$definition,
            'value' => round($value, 2),
            'previous' => round($previous, 2),
            'previous_delta_percent' => $previous != 0 ? round((($value - $previous) / abs($previous)) * 100, 1) : null,
            'target' => $target,
            'target_delta_percent' => $target && $target != 0 ? round((($value - $target) / abs($target)) * 100, 1) : null,
            'quality' => $quality,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function records(string $key, AnalyticsFilter $filter, ?int $limit = null): Collection
    {
        abort_unless(array_key_exists($key, config('analytics.metrics')), 404);
        abort_unless(in_array($filter->area, config("analytics.metrics.{$key}.areas"), true), 403);

        return match ($key) {
            'net_revenue' => $this->limitQuery($this->salesQuery($filter, $filter->from, $filter->to)
                ->select(['sales_documents.id', 'sales_documents.number', 'sales_documents.issue_date', 'sales_documents.status', 'sales_documents.subtotal', 'installations.project_reference'])
                ->orderByDesc('sales_documents.issue_date'), $limit)->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->issue_date, 'reference' => $row->number,
                    'project' => $row->project_reference, 'status' => $row->status,
                    'value' => (float) $row->subtotal,
                    'url' => route('sales-documents.show', $row->id),
                ]),
            'outstanding_receivables' => $this->limitQuery($this->receivableQuery($filter, $filter->to)
                ->orderByDesc('sales_documents.issue_date'), $limit)->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->issue_date, 'reference' => $row->number,
                    'project' => $row->project_reference, 'status' => $row->status,
                    'value' => round((float) $row->outstanding_amount, 2),
                    'url' => route('sales-documents.show', $row->id),
                ]),
            'open_pipeline' => $this->limitQuery($this->opportunityQuery($filter, $filter->from, $filter->to)
                ->select(['opportunities.id', 'opportunities.code', 'opportunities.title', 'opportunities.stage', 'opportunities.estimated_value', 'opportunities.expected_close_on', 'installations.project_reference'])
                ->orderByDesc('opportunities.expected_close_on'), $limit)->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->expected_close_on, 'reference' => $row->code ?: $row->title,
                    'project' => $row->project_reference, 'status' => $row->stage,
                    'value' => (float) $row->estimated_value,
                    'url' => route('opportunities.index', ['search' => $row->code ?: $row->title]),
                ]),
            'win_rate' => $this->limitQuery($this->opportunityQuery($filter, $filter->from, $filter->to, true)
                ->select(['opportunities.id', 'opportunities.code', 'opportunities.title', 'opportunities.stage', 'installations.project_reference', 'latest_closure.closed_at'])
                ->orderByDesc('latest_closure.closed_at'), $limit)->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->closed_at, 'reference' => $row->code ?: $row->title,
                    'project' => $row->project_reference, 'status' => $row->stage,
                    'value' => $row->stage === 'won' ? 1 : 0,
                    'url' => route('opportunities.index', ['search' => $row->code ?: $row->title]),
                ]),
            'completed_projects' => $this->limitQuery($this->installationQuery($filter, $filter->from, $filter->to)
                ->select(['installations.id', 'installations.project_reference', 'installations.site_name', 'installations.status', 'installations.completed_date'])
                ->orderByDesc('completed_date'), $limit)->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->completed_date, 'reference' => $row->project_reference ?: $row->site_name,
                    'project' => $row->project_reference, 'status' => $row->status, 'value' => 1,
                    'url' => route('installations.show', $row->id),
                ]),
            'work_order_completion' => $this->limitQuery($this->workOrderQuery($filter, $filter->from, $filter->to)
                ->select(['work_orders.id', 'work_orders.number', 'work_orders.title', 'work_orders.status', 'work_orders.scheduled_start', 'installations.project_reference'])
                ->orderByDesc('work_orders.scheduled_start'), $limit)->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->scheduled_start, 'reference' => $row->number ?: $row->title,
                    'project' => $row->project_reference, 'status' => $row->status, 'value' => $row->status === 'completed' ? 1 : 0,
                    'url' => route('work-orders.index', ['work_order' => $row->id]),
                ]),
            'purchase_spend' => $this->limitQuery($this->purchaseQuery($filter, $filter->from, $filter->to)
                ->select(['purchase_documents.id', 'purchase_documents.number', 'purchase_documents.issue_date', 'purchase_documents.status', 'purchase_documents.subtotal', 'installations.project_reference'])
                ->orderByDesc('purchase_documents.issue_date'), $limit)->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->issue_date, 'reference' => $row->number,
                    'project' => $row->project_reference, 'status' => $row->status, 'value' => (float) $row->subtotal,
                    'url' => route('purchases.show', $row->id),
                ]),
            'gross_margin' => $this->grossMarginRecords($filter, $limit),
        };
    }

    public function value(string $key, AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return match ($key) {
            'net_revenue' => (float) $this->salesQuery($filter, $from, $to)->sum('sales_documents.subtotal'),
            'outstanding_receivables' => (float) DB::query()->fromSub($this->receivableQuery($filter, $to), 'receivables')->sum('outstanding_amount'),
            'open_pipeline' => (float) $this->opportunityQuery($filter, $from, $to)->sum('opportunities.estimated_value'),
            'win_rate' => $this->ratio($this->opportunityQuery($filter, $from, $to, true), 'opportunities.stage', 'won'),
            'completed_projects' => (float) $this->installationQuery($filter, $from, $to)->count(),
            'work_order_completion' => $this->ratio($this->workOrderQuery($filter, $from, $to), 'work_orders.status', 'completed'),
            'purchase_spend' => (float) $this->purchaseQuery($filter, $from, $to)->sum('purchase_documents.subtotal'),
            'gross_margin' => $this->value('net_revenue', $filter, $from, $to) - $this->materialCost($filter, $from, $to) - $this->labourCost($filter, $from, $to),
        };
    }

    private function ratio(Builder $query, string $column, string $positive): float
    {
        $totals = $query->selectRaw("COUNT(*) total, SUM(CASE WHEN {$column} = ? THEN 1 ELSE 0 END) positive", [$positive])->first();

        return $totals && $totals->total ? ((float) $totals->positive / (float) $totals->total) * 100 : 0;
    }

    /** @return array{score: float|null, complete: int, total: int, label: string} */
    private function quality(string $key, AnalyticsFilter $filter): array
    {
        [$total, $incomplete] = match ($key) {
            'net_revenue' => $this->qualityCounts(
                $this->salesQuery($filter, $filter->from, $filter->to),
                fn (Builder $query) => $query->whereNull('sales_documents.issue_date')->orWhereNull('sales_documents.number')->orWhere('sales_documents.number', ''),
            ),
            'outstanding_receivables' => $this->qualityCounts(
                $this->receivableQuery($filter, $filter->to),
                fn (Builder $query) => $query->whereNull('sales_documents.issue_date')->orWhereNull('sales_documents.number')->orWhere('sales_documents.number', ''),
            ),
            'open_pipeline' => $this->qualityCounts(
                $this->opportunityQuery($filter, $filter->from, $filter->to),
                fn (Builder $query) => $query->whereNull('opportunities.expected_close_on')->orWhere(fn (Builder $reference) => $reference->whereNull('opportunities.code')->whereNull('opportunities.title')),
            ),
            'win_rate' => $this->qualityCounts(
                $this->opportunityQuery($filter, $filter->from, $filter->to, true),
                fn (Builder $query) => $query->whereNull('latest_closure.closed_at')->orWhere(fn (Builder $reference) => $reference->whereNull('opportunities.code')->whereNull('opportunities.title')),
            ),
            'completed_projects' => $this->qualityCounts(
                $this->installationQuery($filter, $filter->from, $filter->to),
                fn (Builder $query) => $query->whereNull('installations.completed_date')->orWhere(fn (Builder $reference) => $reference->whereNull('installations.project_reference')->whereNull('installations.site_name')),
            ),
            'work_order_completion' => $this->qualityCounts(
                $this->workOrderQuery($filter, $filter->from, $filter->to),
                fn (Builder $query) => $query->whereNull('work_orders.scheduled_start')->orWhere(fn (Builder $reference) => $reference->whereNull('work_orders.number')->whereNull('work_orders.title')),
            ),
            'purchase_spend' => $this->qualityCounts(
                $this->purchaseQuery($filter, $filter->from, $filter->to),
                fn (Builder $query) => $query->whereNull('purchase_documents.issue_date')->orWhereNull('purchase_documents.number')->orWhere('purchase_documents.number', ''),
            ),
            'gross_margin' => $this->grossMarginQuality($filter),
        };
        $complete = $total - $incomplete;
        $score = $total ? round(($complete / $total) * 100, 1) : null;

        return ['score' => $score, 'complete' => $complete, 'total' => $total, 'label' => $score === null ? 'Sin datos' : ($score >= 98 ? 'Buena' : ($score >= 90 ? 'Revisar' : 'Deficiente'))];
    }

    /** @return array{int, int} */
    private function qualityCounts(Builder $query, callable $markIncomplete): array
    {
        $total = (clone $query)->count();
        $incomplete = (clone $query)->where(fn (Builder $invalid) => $markIncomplete($invalid))->count();

        return [$total, $incomplete];
    }

    /** @return array{int, int} */
    private function grossMarginQuality(AnalyticsFilter $filter): array
    {
        [$revenueTotal, $revenueIncomplete] = $this->qualityCounts(
            $this->salesQuery($filter, $filter->from, $filter->to),
            fn (Builder $query) => $query->whereNull('sales_documents.issue_date')->orWhereNull('sales_documents.number'),
        );
        [$materialTotal, $materialIncomplete] = $this->qualityCounts(
            $this->materialQuery($filter, $filter->from, $filter->to),
            fn (Builder $query) => $query->whereNull('stock_movement_lines.unit_cost')->orWhereNull('stock_movements.installation_id'),
        );
        [$labourTotal, $labourIncomplete] = $this->qualityCounts(
            $this->labourQuery($filter, $filter->from, $filter->to),
            fn (Builder $query) => $query->whereNull('time_entries.duration_minutes')->orWhereNull('time_entries.hourly_cost')->orWhereNull('work_orders.installation_id'),
        );

        return [$revenueTotal + $materialTotal + $labourTotal, $revenueIncomplete + $materialIncomplete + $labourIncomplete];
    }

    private function target(array $definition, AnalyticsFilter $filter): ?float
    {
        $components = isset($definition['budget_category'])
            ? [$definition['budget_category'] => 1]
            : ($definition['budget_components'] ?? null);
        if (! $components) {
            return null;
        }

        $budgets = FinancialBudget::query()
            ->whereIn('category', array_keys($components))
            ->where(function ($query) use ($filter) {
                $cursor = $filter->from->startOfMonth();
                while ($cursor <= $filter->to) {
                    $query->orWhere(fn ($month) => $month->where('year', $cursor->year)->where('month', $cursor->month));
                    $cursor = $cursor->addMonth();
                }
            })->get(['year', 'month', 'category', 'amount']);

        return round((float) $budgets->sum(function (FinancialBudget $budget) use ($components, $filter): float {
            $monthStart = CarbonImmutable::create($budget->year, $budget->month, 1)->startOfDay();
            $monthEnd = $monthStart->endOfMonth()->endOfDay();
            $coveredFrom = $filter->from->greaterThan($monthStart) ? $filter->from : $monthStart;
            $coveredTo = $filter->to->lessThan($monthEnd) ? $filter->to : $monthEnd;
            $coveredDays = $coveredFrom->startOfDay()->diffInDays($coveredTo->startOfDay()) + 1;
            $weight = $coveredDays / $monthStart->daysInMonth;

            return (float) $budget->amount * $weight * $components[$budget->category];
        }), 2);
    }

    /** Existing financial revenue budget, prorated using the canonical analytics definition. */
    public function revenueTarget(AnalyticsFilter $filter): ?float
    {
        $target = $this->target(config('analytics.metrics.net_revenue'), $filter);

        return $target > 0 ? $target : null;
    }

    /** Monthly net invoiced revenue, with the same document and user scopes as the metric. */
    public function monthlyRevenue(AnalyticsFilter $filter): Collection
    {
        $month = match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(sales_documents.issue_date, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT(sales_documents.issue_date, '%Y-%m')",
            default => "strftime('%Y-%m', sales_documents.issue_date)",
        };

        return $this->salesQuery($filter, $filter->from, $filter->to)
            ->selectRaw("{$month} period, SUM(sales_documents.subtotal) value")
            ->groupByRaw($month)->orderBy('period')->get();
    }

    private function salesQuery(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = DB::table('sales_documents')->leftJoin('installations', 'installations.id', '=', 'sales_documents.installation_id')
            ->whereBetween('sales_documents.issue_date', [$from, $to])
            ->whereIn('sales_documents.type', ['invoice', 'credit_note'])
            ->whereNotIn('sales_documents.status', ['draft', 'cancelled']);

        return $this->scopeInstallation($query, $filter);
    }

    private function receivableQuery(AnalyticsFilter $filter, CarbonImmutable $asOf): Builder
    {
        $payments = DB::table('sales_document_payments')
            ->selectRaw('sales_document_id, SUM(amount) paid_as_of')
            ->where('paid_at', '<=', $asOf)
            ->groupBy('sales_document_id');

        $query = DB::table('sales_documents')
            ->leftJoin('installations', 'installations.id', '=', 'sales_documents.installation_id')
            ->leftJoinSub($payments, 'payments_as_of', 'payments_as_of.sales_document_id', '=', 'sales_documents.id')
            ->where('sales_documents.type', 'invoice')
            ->whereNotIn('sales_documents.status', ['draft', 'cancelled'])
            ->where('sales_documents.issue_date', '<=', $asOf)
            ->select([
                'sales_documents.id', 'sales_documents.number', 'sales_documents.issue_date',
                'sales_documents.status', 'installations.project_reference',
            ])
            ->selectRaw('CASE WHEN sales_documents.amount - COALESCE(payments_as_of.paid_as_of, 0) > 0 THEN sales_documents.amount - COALESCE(payments_as_of.paid_as_of, 0) ELSE 0 END outstanding_amount')
            ->whereRaw('sales_documents.amount - COALESCE(payments_as_of.paid_as_of, 0) > 0');

        return $this->scopeInstallation($query, $filter);
    }

    private function opportunityQuery(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to, bool $closed = false): Builder
    {
        $query = DB::table('opportunities')->leftJoin('installations', 'installations.id', '=', 'opportunities.installation_id')->whereNull('opportunities.deleted_at');
        if ($closed) {
            $latestClosure = DB::table('opportunity_stage_histories')
                ->whereIn('to_stage', ['won', 'lost'])
                ->selectRaw('opportunity_id, MAX(changed_at) closed_at')
                ->groupBy('opportunity_id');
            $query->joinSub($latestClosure, 'latest_closure', 'latest_closure.opportunity_id', '=', 'opportunities.id')
                ->whereIn('opportunities.stage', ['won', 'lost'])
                ->whereBetween('latest_closure.closed_at', [$from, $to]);
        } else {
            $query->whereNotIn('opportunities.stage', ['won', 'lost'])
                ->whereBetween('opportunities.expected_close_on', [$from, $to]);
        }
        if ($filter->commercialId) {
            $query->where('opportunities.owner_user_id', $filter->commercialId);
        }
        if ($filter->projectId) {
            $query->where('opportunities.installation_id', $filter->projectId);
        }
        if ($filter->restrictsToOwnCommercialScope()) {
            $query->where('opportunities.owner_user_id', $filter->user->id);
        }

        return $query;
    }

    private function installationQuery(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = DB::table('installations')->where('installations.status', 'completed')->whereBetween('installations.completed_date', [$from, $to]);

        return $this->scopeInstallation($query, $filter);
    }

    private function workOrderQuery(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = DB::table('work_orders')->leftJoin('installations', 'installations.id', '=', 'work_orders.installation_id')
            ->whereNotNull('work_orders.scheduled_start')
            ->where('work_orders.status', '!=', 'cancelled')
            ->whereBetween('work_orders.scheduled_start', [$from, $to]);
        if ($filter->user->hasRole('technician')) {
            $query->where('work_orders.assigned_user_id', $filter->user->id);
        }

        return $this->scopeInstallation($query, $filter);
    }

    private function purchaseQuery(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = DB::table('purchase_documents')->leftJoin('installations', 'installations.id', '=', 'purchase_documents.installation_id')
            ->whereIn('purchase_documents.type', ['supplier_invoice', 'supplier_credit_note'])
            ->whereNotIn('purchase_documents.status', ['draft', 'cancelled', 'review_required'])
            ->where(fn (Builder $query) => $query->whereNull('purchase_documents.matching_status')->orWhere('purchase_documents.matching_status', '!=', 'review_required'))
            ->whereBetween('purchase_documents.issue_date', [$from, $to]);

        return $this->scopeInstallation($query, $filter);
    }

    private function scopeInstallation(Builder $query, AnalyticsFilter $filter): Builder
    {
        if ($filter->commercialId) {
            $query->where('installations.commercial_user_id', $filter->commercialId);
        }
        if ($filter->projectId) {
            $query->where('installations.id', $filter->projectId);
        }
        if ($filter->user->hasRole('technician')) {
            $query->where('installations.technician_user_id', $filter->user->id);
        }
        if ($filter->restrictsToOwnCommercialScope()) {
            $query->where('installations.commercial_user_id', $filter->user->id);
        }

        return $query;
    }

    private function materialCost(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) $this->materialQuery($filter, $from, $to)
            ->selectRaw('COALESCE(SUM(-stock_movement_lines.quantity * COALESCE(stock_movement_lines.unit_cost, 0)), 0) total')
            ->value('total');
    }

    private function materialQuery(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = DB::table('stock_movement_lines')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->leftJoin('installations', 'installations.id', '=', 'stock_movements.installation_id')
            ->where('stock_movements.type', 'issue')
            ->whereBetween('stock_movements.posted_at', [$from, $to]);

        return $this->scopeInstallation($query, $filter);
    }

    private function labourCost(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) $this->labourQuery($filter, $from, $to)
            ->selectRaw('COALESCE(SUM((COALESCE(time_entries.duration_minutes, 0) / 60.0) * time_entries.hourly_cost), 0) total')
            ->value('total');
    }

    private function labourQuery(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = DB::table('time_entries')
            ->join('work_orders', 'work_orders.id', '=', 'time_entries.work_order_id')
            ->leftJoin('installations', 'installations.id', '=', 'work_orders.installation_id')
            ->whereBetween('time_entries.started_at', [$from, $to]);

        return $this->scopeInstallation($query, $filter);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function grossMarginRecords(AnalyticsFilter $filter, ?int $limit = null): Collection
    {
        $revenue = $this->records('net_revenue', $filter, $limit)->map(fn (array $row) => [...$row, 'status' => __('Ingreso neto')]);
        $materials = $this->limitQuery($this->materialQuery($filter, $filter->from, $filter->to)
            ->select(['stock_movement_lines.id', 'stock_movements.posted_at', 'stock_movements.number', 'stock_movement_lines.quantity', 'stock_movement_lines.unit_cost', 'installations.id as installation_id', 'installations.project_reference'])
            ->orderByDesc('stock_movements.posted_at'), $limit)->get()->map(fn ($row) => [
                'id' => $row->id, 'date' => $row->posted_at, 'reference' => $row->number,
                'project' => $row->project_reference, 'status' => __('Material consumido'),
                'value' => -round(-(float) $row->quantity * (float) ($row->unit_cost ?? 0), 2),
                'url' => $row->installation_id ? route('installations.show', $row->installation_id) : route('inventory.index'),
            ]);
        $labour = $this->limitQuery($this->labourQuery($filter, $filter->from, $filter->to)
            ->select(['time_entries.id', 'time_entries.started_at', 'time_entries.duration_minutes', 'time_entries.hourly_cost', 'work_orders.id as work_order_id', 'work_orders.number', 'installations.project_reference'])
            ->orderByDesc('time_entries.started_at'), $limit)->get()->map(fn ($row) => [
                'id' => $row->id, 'date' => $row->started_at, 'reference' => $row->number,
                'project' => $row->project_reference, 'status' => __('Mano de obra directa'),
                'value' => -round(((float) ($row->duration_minutes ?? 0) / 60) * (float) $row->hourly_cost, 2),
                'url' => route('work-orders.index', ['work_order' => $row->work_order_id]),
            ]);

        $records = $revenue->concat($materials)->concat($labour)->sortByDesc('date')->values();

        return $limit === null ? $records : $records->take($limit)->values();
    }

    private function limitQuery(Builder $query, ?int $limit): Builder
    {
        return $limit === null ? $query : $query->limit(max(1, $limit));
    }
}
