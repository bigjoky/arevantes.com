<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Support\Crm\OpportunityCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommercialConversionAnalytics
{
    /** @return array<string, mixed> */
    public function forAnalytics(AnalyticsFilter $filter): array
    {
        return $this->report(
            $filter->from,
            $filter->to,
            $filter->restrictsToOwnCommercialScope() ? $filter->user->id : $filter->commercialId,
            $filter->projectId,
            true,
        );
    }

    /** @return array<string, mixed> */
    public function forCommercial(User $commercial, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->report($from, $to, $commercial->id, null, false);
    }

    /** Dashboard composition reuses closure semantics without loading commercial breakdowns. */
    public function forDashboard(AnalyticsFilter $filter): array
    {
        $base = $this->closedOpportunities($filter->from, $filter->to, $filter->restrictsToOwnCommercialScope() ? $filter->user->id : null, null);

        return ['summary' => $this->summary($this->sourceRows($base)), 'trend' => $this->trendRows($base)];
    }

    /** @return array<string, mixed> */
    private function report(CarbonInterface $from, CarbonInterface $to, ?int $commercialId, ?int $projectId, bool $includeCommercialBreakdown): array
    {
        $base = $this->closedOpportunities($from, $to, $commercialId, $projectId);
        $sourceRows = $this->sourceRows($base);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $this->summary($sourceRows),
            'by_source' => $sourceRows,
            'by_commercial' => $includeCommercialBreakdown ? $this->commercialRows($base) : collect(),
            'trend' => $this->trendRows($base),
        ];
    }

    private function closedOpportunities(CarbonInterface $from, CarbonInterface $to, ?int $commercialId, ?int $projectId): Builder
    {
        $latestClosure = DB::table('opportunity_stage_histories')
            ->whereIn('to_stage', ['won', 'lost'])
            ->selectRaw('opportunity_id, MAX(changed_at) closed_at')
            ->groupBy('opportunity_id');

        return DB::table('opportunities')
            ->joinSub($latestClosure, 'latest_closure', 'latest_closure.opportunity_id', '=', 'opportunities.id')
            ->leftJoin('users as owners', 'owners.id', '=', 'opportunities.owner_user_id')
            ->whereNull('opportunities.deleted_at')
            ->whereIn('opportunities.stage', ['won', 'lost'])
            ->whereBetween('latest_closure.closed_at', [$from, $to])
            ->when($commercialId, fn (Builder $query) => $query->where('opportunities.owner_user_id', $commercialId))
            ->when($projectId, fn (Builder $query) => $query->where('opportunities.installation_id', $projectId));
    }

    /** @return array<string, int|float> */
    private function summary(Collection $rows): array
    {
        $closed = (int) $rows->sum('closed');
        $won = (int) $rows->sum('won');
        $unattributed = (int) ($rows->firstWhere('key', 'unattributed')['closed'] ?? 0);

        return [
            'closed' => $closed,
            'won' => $won,
            'lost' => (int) $rows->sum('lost'),
            'win_rate' => $this->rate($won, $closed),
            'won_value' => round((float) $rows->sum('won_value'), 2),
            'attribution_rate' => $this->rate($closed - $unattributed, $closed),
        ];
    }

    /** @return Collection<int, array<string, int|float|string>> */
    private function sourceRows(Builder $base): Collection
    {
        return (clone $base)
            ->selectRaw("COALESCE(NULLIF(opportunities.source, ''), 'unattributed') attribution_key, COUNT(*) closed, SUM(CASE WHEN opportunities.stage = 'won' THEN 1 ELSE 0 END) won, SUM(CASE WHEN opportunities.stage = 'lost' THEN 1 ELSE 0 END) lost, SUM(CASE WHEN opportunities.stage = 'won' THEN opportunities.estimated_value ELSE 0 END) won_value")
            ->groupBy('attribution_key')
            ->orderByDesc('won')
            ->orderByDesc('closed')
            ->get()
            ->map(fn (object $row) => $this->row(
                (string) $row->attribution_key,
                OpportunityCatalog::SOURCES[$row->attribution_key] ?? ($row->attribution_key === 'unattributed' ? __('Sin origen') : ucfirst((string) $row->attribution_key)),
                $row,
            ));
    }

    /** @return Collection<int, array<string, int|float|string>> */
    private function commercialRows(Builder $base): Collection
    {
        return (clone $base)
            ->selectRaw("COALESCE(CAST(opportunities.owner_user_id AS TEXT), 'unassigned') attribution_key, COALESCE(owners.name, 'Sin comercial') attribution_label, COUNT(*) closed, SUM(CASE WHEN opportunities.stage = 'won' THEN 1 ELSE 0 END) won, SUM(CASE WHEN opportunities.stage = 'lost' THEN 1 ELSE 0 END) lost, SUM(CASE WHEN opportunities.stage = 'won' THEN opportunities.estimated_value ELSE 0 END) won_value")
            ->groupBy('opportunities.owner_user_id', 'owners.name')
            ->orderByDesc('won')
            ->orderByDesc('closed')
            ->get()
            ->map(fn (object $row) => $this->row((string) $row->attribution_key, (string) $row->attribution_label, $row));
    }

    /** @return Collection<int, array<string, int|float|string>> */
    private function trendRows(Builder $base): Collection
    {
        $monthExpression = match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(latest_closure.closed_at, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT(latest_closure.closed_at, '%Y-%m')",
            default => "strftime('%Y-%m', latest_closure.closed_at)",
        };

        return (clone $base)
            ->selectRaw("{$monthExpression} period, COUNT(*) closed, SUM(CASE WHEN opportunities.stage = 'won' THEN 1 ELSE 0 END) won")
            ->groupByRaw($monthExpression)
            ->orderBy('period')
            ->get()
            ->map(function (object $row): array {
                $closed = (int) $row->closed;
                $won = (int) $row->won;

                return ['period' => (string) $row->period, 'closed' => $closed, 'won' => $won, 'win_rate' => $this->rate($won, $closed)];
            });
    }

    /** @return array<string, int|float|string> */
    private function row(string $key, string $label, object $row): array
    {
        $closed = (int) $row->closed;
        $won = (int) $row->won;

        return [
            'key' => $key,
            'label' => $label,
            'closed' => $closed,
            'won' => $won,
            'lost' => (int) $row->lost,
            'win_rate' => $this->rate($won, $closed),
            'won_value' => round((float) $row->won_value, 2),
        ];
    }

    private function rate(int $positive, int $total): float
    {
        return $total > 0 ? round(($positive / $total) * 100, 1) : 0;
    }
}
