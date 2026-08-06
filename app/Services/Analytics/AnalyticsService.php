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
    public function records(string $key, AnalyticsFilter $filter): Collection
    {
        abort_unless(array_key_exists($key, config('analytics.metrics')), 404);
        abort_unless(in_array($filter->area, config("analytics.metrics.{$key}.areas"), true), 403);

        return match ($key) {
            'net_revenue', 'outstanding_receivables' => $this->salesQuery($filter, $filter->from, $filter->to, $key === 'outstanding_receivables')
                ->select(['sales_documents.id', 'sales_documents.number', 'sales_documents.issue_date', 'sales_documents.status', 'sales_documents.amount', 'sales_documents.paid_amount', 'installations.project_reference'])
                ->orderByDesc('sales_documents.issue_date')->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->issue_date, 'reference' => $row->number,
                    'project' => $row->project_reference, 'status' => $row->status,
                    'value' => $key === 'outstanding_receivables' ? round((float) $row->amount - (float) $row->paid_amount, 2) : (float) $row->amount,
                    'url' => route('sales-documents.show', $row->id),
                ]),
            'open_pipeline', 'win_rate' => $this->opportunityQuery($filter, $filter->from, $filter->to, $key === 'win_rate')
                ->select(['opportunities.id', 'opportunities.code', 'opportunities.title', 'opportunities.stage', 'opportunities.estimated_value', 'opportunities.expected_close_on', 'installations.project_reference'])
                ->orderByDesc('opportunities.updated_at')->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->expected_close_on, 'reference' => $row->code ?: $row->title,
                    'project' => $row->project_reference, 'status' => $row->stage,
                    'value' => $key === 'win_rate' ? ($row->stage === 'won' ? 1 : 0) : (float) $row->estimated_value,
                    'url' => route('opportunities.index', ['search' => $row->code ?: $row->title]),
                ]),
            'completed_projects' => $this->installationQuery($filter, $filter->from, $filter->to)
                ->select(['installations.id', 'installations.project_reference', 'installations.site_name', 'installations.status', 'installations.completed_date'])
                ->orderByDesc('completed_date')->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->completed_date, 'reference' => $row->project_reference ?: $row->site_name,
                    'project' => $row->project_reference, 'status' => $row->status, 'value' => 1,
                    'url' => route('installations.show', $row->id),
                ]),
            'work_order_completion' => $this->workOrderQuery($filter, $filter->from, $filter->to)
                ->select(['work_orders.id', 'work_orders.number', 'work_orders.title', 'work_orders.status', 'work_orders.scheduled_start', 'installations.project_reference'])
                ->orderByDesc('work_orders.scheduled_start')->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->scheduled_start, 'reference' => $row->number ?: $row->title,
                    'project' => $row->project_reference, 'status' => $row->status, 'value' => $row->status === 'completed' ? 1 : 0,
                    'url' => route('work-orders.index', ['work_order' => $row->id]),
                ]),
            'purchase_spend' => $this->purchaseQuery($filter, $filter->from, $filter->to)
                ->select(['purchase_documents.id', 'purchase_documents.number', 'purchase_documents.issue_date', 'purchase_documents.status', 'purchase_documents.amount', 'installations.project_reference'])
                ->orderByDesc('purchase_documents.issue_date')->get()->map(fn ($row) => [
                    'id' => $row->id, 'date' => $row->issue_date, 'reference' => $row->number,
                    'project' => $row->project_reference, 'status' => $row->status, 'value' => (float) $row->amount,
                    'url' => route('purchases.show', $row->id),
                ]),
            'gross_margin' => $this->records('net_revenue', $filter)->concat(
                $this->records('purchase_spend', $filter)->map(fn (array $row) => [...$row, 'value' => -$row['value']]),
            )->values(),
        };
    }

    public function value(string $key, AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return match ($key) {
            'net_revenue' => (float) $this->salesQuery($filter, $from, $to)->sum('sales_documents.amount'),
            'outstanding_receivables' => (float) $this->salesQuery($filter, $from, $to, true)->selectRaw('COALESCE(SUM(sales_documents.amount - sales_documents.paid_amount), 0) total')->value('total'),
            'open_pipeline' => (float) $this->opportunityQuery($filter, $from, $to)->sum('opportunities.estimated_value'),
            'win_rate' => $this->ratio($this->opportunityQuery($filter, $from, $to, true), 'opportunities.stage', 'won'),
            'completed_projects' => (float) $this->installationQuery($filter, $from, $to)->count(),
            'work_order_completion' => $this->ratio($this->workOrderQuery($filter, $from, $to), 'work_orders.status', 'completed'),
            'purchase_spend' => (float) $this->purchaseQuery($filter, $from, $to)->sum('purchase_documents.amount'),
            'gross_margin' => $this->value('net_revenue', $filter, $from, $to) - $this->value('purchase_spend', $filter, $from, $to),
        };
    }

    private function ratio(Builder $query, string $column, string $positive): float
    {
        $totals = $query->selectRaw("COUNT(*) total, SUM(CASE WHEN {$column} = ? THEN 1 ELSE 0 END) positive", [$positive])->first();

        return $totals && $totals->total ? ((float) $totals->positive / (float) $totals->total) * 100 : 0;
    }

    /** @return array{score: float, complete: int, total: int, label: string} */
    private function quality(string $key, AnalyticsFilter $filter): array
    {
        $records = $this->records($key, $filter);
        $total = $records->count();
        $complete = $records->filter(fn (array $row) => filled($row['date']) && filled($row['reference']) && ($key === 'open_pipeline' || $key === 'win_rate' || $key === 'work_order_completion' || filled($row['project'])))->count();
        $score = $total ? round(($complete / $total) * 100, 1) : 100.0;

        return ['score' => $score, 'complete' => $complete, 'total' => $total, 'label' => $score >= 98 ? 'Buena' : ($score >= 90 ? 'Revisar' : 'Deficiente')];
    }

    private function target(array $definition, AnalyticsFilter $filter): ?float
    {
        if (! isset($definition['budget_category'])) {
            return null;
        }

        return round((float) FinancialBudget::query()
            ->where('category', $definition['budget_category'])
            ->where(function ($query) use ($filter) {
                $cursor = $filter->from->startOfMonth();
                while ($cursor <= $filter->to) {
                    $query->orWhere(fn ($month) => $month->where('year', $cursor->year)->where('month', $cursor->month));
                    $cursor = $cursor->addMonth();
                }
            })->sum('amount'), 2);
    }

    private function salesQuery(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to, bool $outstanding = false): Builder
    {
        $query = DB::table('sales_documents')->leftJoin('installations', 'installations.id', '=', 'sales_documents.installation_id')
            ->whereBetween('sales_documents.issue_date', [$from, $to]);
        $outstanding
            ? $query->where('sales_documents.type', 'invoice')->whereIn('sales_documents.status', ['issued', 'partial', 'overdue'])
            : $query->whereIn('sales_documents.type', ['invoice', 'credit_note'])->where('sales_documents.status', '!=', 'draft');

        return $this->scopeInstallation($query, $filter);
    }

    private function opportunityQuery(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to, bool $closed = false): Builder
    {
        $query = DB::table('opportunities')->leftJoin('installations', 'installations.id', '=', 'opportunities.installation_id')->whereNull('opportunities.deleted_at');
        $closed
            ? $query->whereIn('opportunities.stage', ['won', 'lost'])->whereBetween('opportunities.updated_at', [$from, $to])
            : $query->whereNotIn('opportunities.stage', ['won', 'lost'])->whereBetween('opportunities.expected_close_on', [$from, $to]);
        if ($filter->commercialId) {
            $query->where('opportunities.owner_user_id', $filter->commercialId);
        }
        if ($filter->projectId) {
            $query->where('opportunities.installation_id', $filter->projectId);
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
            ->whereBetween(DB::raw('COALESCE(work_orders.scheduled_start, work_orders.created_at)'), [$from, $to]);
        if ($filter->user->hasRole('technician')) {
            $query->where('work_orders.assigned_user_id', $filter->user->id);
        }

        return $this->scopeInstallation($query, $filter);
    }

    private function purchaseQuery(AnalyticsFilter $filter, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = DB::table('purchase_documents')->leftJoin('installations', 'installations.id', '=', 'purchase_documents.installation_id')
            ->whereIn('purchase_documents.type', ['supplier_invoice', 'supplier_credit_note'])
            ->where('purchase_documents.status', '!=', 'review_required')
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

        return $query;
    }
}
