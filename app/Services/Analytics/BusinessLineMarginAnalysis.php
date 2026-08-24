<?php

namespace App\Services\Analytics;

use App\Models\Installation;
use App\Services\Operations\ProjectMarginPortfolio;
use App\Support\BusinessLineCatalog;
use Illuminate\Support\Collection;

class BusinessLineMarginAnalysis
{
    public function __construct(private ProjectMarginPortfolio $portfolio) {}

    /** @return array<string, mixed> */
    public function build(AnalyticsFilter $filter): array
    {
        $installations = Installation::query()
            ->whereIn('service_type', [...BusinessLineCatalog::INSTALLATION_TYPES, 'solar_pv'])
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '<=', $filter->to)
            ->where(fn ($query) => $query->whereNull('completed_date')->orWhere('completed_date', '>=', $filter->from->toDateString()))
            ->when($filter->commercialId, fn ($query, int $id) => $query->where('commercial_user_id', $id))
            ->when($filter->projectId, fn ($query, int $id) => $query->whereKey($id))
            ->when($filter->user->hasRole('technician'), fn ($query) => $query->where('technician_user_id', $filter->user->id))
            ->when($filter->restrictsToOwnCommercialScope(), fn ($query) => $query->where('commercial_user_id', $filter->user->id))
            ->orderByDesc('created_at')->limit(500)
            ->get(['id', 'service_type', 'status', 'project_reference', 'site_name', 'estimated_budget']);
        $projects = $this->portfolio->analyze($installations);

        $byLine = collect(BusinessLineCatalog::INSTALLATION_TYPES)->map(function (string $serviceType) use ($projects): array {
            $rows = $projects->where('service_type', $serviceType);
            $plannedRevenue = (float) $rows->sum('planned_revenue');
            $actualRevenue = (float) $rows->sum('actual_revenue');
            $plannedMargin = (float) $rows->sum('planned_margin');
            $committedMargin = (float) $rows->sum('committed_margin');
            $actualMargin = (float) $rows->sum('actual_margin');

            return [
                'service_type' => $serviceType,
                'label' => BusinessLineCatalog::installationLabel($serviceType),
                'projects' => $rows->count(),
                'planned_revenue' => round($plannedRevenue, 2),
                'planned_margin' => round($plannedMargin, 2),
                'planned_margin_percent' => $plannedRevenue !== 0.0 ? round($plannedMargin / $plannedRevenue * 100, 1) : null,
                'committed_margin' => round($committedMargin, 2),
                'committed_margin_percent' => $plannedRevenue !== 0.0 ? round($committedMargin / $plannedRevenue * 100, 1) : null,
                'actual_revenue' => round($actualRevenue, 2),
                'actual_margin' => round($actualMargin, 2),
                'actual_margin_percent' => $actualRevenue !== 0.0 ? round($actualMargin / $actualRevenue * 100, 1) : null,
                'outstanding_cash' => round((float) $rows->sum('outstanding_cash'), 2),
                'at_risk' => $rows->filter(fn (array $row) => $this->isAtRisk($row))->count(),
                'quality_score' => $rows->isNotEmpty() ? round((float) $rows->avg('quality.score'), 1) : null,
            ];
        });

        return [
            'summary' => $this->summary($projects),
            'by_line' => $byLine,
            'projects' => $projects->sortBy('committed_margin')->values(),
            'definitions' => [
                'planned' => __('Accepted net revenue minus the latest approved project cost plan.'),
                'committed' => __('Accepted net revenue minus incurred costs and uninvoiced open purchase commitments.'),
                'actual' => __('Issued net revenue minus consumed materials, completed labour, validated supplier services and direct expenses.'),
            ],
        ];
    }

    /** @param Collection<int, array<string, mixed>> $projects */
    private function summary(Collection $projects): array
    {
        $plannedRevenue = (float) $projects->sum('planned_revenue');
        $actualRevenue = (float) $projects->sum('actual_revenue');
        $plannedMargin = (float) $projects->sum('planned_margin');
        $committedMargin = (float) $projects->sum('committed_margin');
        $actualMargin = (float) $projects->sum('actual_margin');

        return [
            'projects' => $projects->count(),
            'planned_revenue' => round($plannedRevenue, 2),
            'planned_margin' => round($plannedMargin, 2),
            'planned_margin_percent' => $plannedRevenue !== 0.0 ? round($plannedMargin / $plannedRevenue * 100, 1) : null,
            'committed_margin' => round($committedMargin, 2),
            'committed_margin_percent' => $plannedRevenue !== 0.0 ? round($committedMargin / $plannedRevenue * 100, 1) : null,
            'actual_revenue' => round($actualRevenue, 2),
            'actual_margin' => round($actualMargin, 2),
            'actual_margin_percent' => $actualRevenue !== 0.0 ? round($actualMargin / $actualRevenue * 100, 1) : null,
            'outstanding_cash' => round((float) $projects->sum('outstanding_cash'), 2),
            'at_risk' => $projects->filter(fn (array $row) => $this->isAtRisk($row))->count(),
            'quality_score' => $projects->isNotEmpty() ? round((float) $projects->avg('quality.score'), 1) : null,
        ];
    }

    /** A project is at risk after losing at least 10% of its planned margin, or whenever its committed margin is negative. */
    private function isAtRisk(array $project): bool
    {
        $planned = (float) $project['planned_margin'];
        $committed = (float) $project['committed_margin'];

        return $committed < 0 || ($planned > 0 ? $committed < $planned * .9 : $committed < $planned);
    }
}
