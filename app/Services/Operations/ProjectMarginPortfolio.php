<?php

namespace App\Services\Operations;

use App\Models\Installation;
use App\Models\Quote;
use App\Services\Finance\PurchaseCommitmentAnalysis;
use App\Support\BusinessLineCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectMarginPortfolio
{
    public function __construct(private PurchaseCommitmentAnalysis $commitments) {}

    /**
     * @param  Collection<int, Installation>  $installations
     * @param  array<int, Quote>  $quoteOverrides
     * @return Collection<int, array<string, mixed>>
     */
    public function analyze(Collection $installations, array $quoteOverrides = []): Collection
    {
        $installations = $installations->values();
        $ids = $installations->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        $quotes = Quote::query()->whereIn('installation_id', $ids)->where('status', 'accepted')->latest('id')
            ->get(['id', 'installation_id', 'number', 'subtotal', 'discount_amount', 'expected_margin_amount'])
            ->groupBy('installation_id')->map->first();
        foreach ($quoteOverrides as $installationId => $quote) {
            $quotes->put($installationId, $quote);
        }

        $plannedMaterials = DB::table('installation_material_requirements')->whereIn('installation_id', $ids)
            ->selectRaw('installation_id, coalesce(sum(planned_quantity * planned_unit_cost), 0) total')
            ->groupBy('installation_id')->pluck('total', 'installation_id');
        $plannedLabour = DB::table('work_orders')->whereIn('installation_id', $ids)->where('status', '!=', 'cancelled')
            ->selectRaw('installation_id, coalesce(sum(estimated_duration_minutes), 0) minutes, count(*) work_orders')
            ->groupBy('installation_id')->get()->keyBy('installation_id');
        $sales = DB::table('sales_documents')->whereIn('installation_id', $ids)
            ->whereIn('type', ['invoice', 'credit_note'])->whereNotIn('status', ['draft', 'cancelled'])
            ->selectRaw('installation_id, coalesce(sum(subtotal - discount_amount), 0) net_revenue, coalesce(sum(amount), 0) billed_gross, coalesce(sum(paid_amount), 0) collected_cash, count(*) documents')
            ->groupBy('installation_id')->get()->keyBy('installation_id');
        $labour = DB::table('time_entries')->join('work_orders', 'work_orders.id', '=', 'time_entries.work_order_id')
            ->whereIn('work_orders.installation_id', $ids)->whereNotNull('time_entries.ended_at')
            ->selectRaw('work_orders.installation_id, coalesce(sum((coalesce(time_entries.duration_minutes, 0) / 60.0) * coalesce(time_entries.hourly_cost, 0) + coalesce(time_entries.travel_km, 0) * coalesce(time_entries.mileage_rate, 0)), 0) total, count(*) entries')
            ->groupBy('work_orders.installation_id')->get()->keyBy('installation_id');
        $materials = DB::table('stock_movement_lines')->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->whereIn('stock_movements.installation_id', $ids)->where('stock_movements.type', 'issue')
            ->selectRaw('stock_movements.installation_id, coalesce(sum(-stock_movement_lines.quantity * coalesce(stock_movement_lines.unit_cost, 0)), 0) total, count(*) movements')
            ->groupBy('stock_movements.installation_id')->get()->keyBy('installation_id');
        $supplierServices = DB::table('purchase_document_lines')->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_lines.purchase_document_id')
            ->leftJoin('products', 'products.id', '=', 'purchase_document_lines.product_id')
            ->whereIn('purchase_documents.installation_id', $ids)->whereIn('purchase_documents.type', ['supplier_invoice', 'supplier_credit_note'])
            ->whereNotIn('purchase_documents.status', ['draft', 'review_required', 'cancelled'])->where(fn ($query) => $query->where('products.type', 'service')->orWhereNull('purchase_document_lines.product_id'))
            ->selectRaw('purchase_documents.installation_id, coalesce(sum(purchase_document_lines.subtotal), 0) total, count(distinct purchase_documents.id) documents')
            ->groupBy('purchase_documents.installation_id')->get()->keyBy('installation_id');
        $expenses = DB::table('expenses')->whereIn('installation_id', $ids)->whereNotIn('status', ['draft', 'cancelled'])
            ->selectRaw('installation_id, coalesce(sum(subtotal), 0) total, count(*) documents')
            ->groupBy('installation_id')->get()->keyBy('installation_id');
        $committed = $this->commitments->remainingNetForInstallations($ids);
        $quality = $this->qualityIssues($ids);
        $hourlyCost = (float) config('work.default_hourly_cost');

        return $installations->map(function (Installation $installation) use ($quotes, $plannedMaterials, $plannedLabour, $sales, $labour, $materials, $supplierServices, $expenses, $committed, $quality, $hourlyCost): array {
            $id = $installation->id;
            $quote = $quotes->get($id);
            $plannedRevenue = $quote
                ? (float) $quote->subtotal - (float) $quote->discount_amount
                : (float) ($installation->estimated_budget ?: 0);
            $plannedMaterialCost = (float) ($plannedMaterials[$id] ?? 0);
            $plannedLabourCost = ((float) ($plannedLabour->get($id)?->minutes ?? 0) / 60) * $hourlyCost;
            $derivedPlannedCost = $plannedMaterialCost + $plannedLabourCost;
            $plannedCost = $quote?->expected_margin_amount !== null
                ? max(0, $plannedRevenue - (float) $quote->expected_margin_amount)
                : $derivedPlannedCost;
            $actualMaterialCost = (float) ($materials->get($id)?->total ?? 0);
            $actualLabourCost = (float) ($labour->get($id)?->total ?? 0);
            $supplierServiceCost = (float) ($supplierServices->get($id)?->total ?? 0);
            $directExpenses = (float) ($expenses->get($id)?->total ?? 0);
            $actualCost = $actualMaterialCost + $actualLabourCost + $supplierServiceCost + $directExpenses;
            $actualRevenue = (float) ($sales->get($id)?->net_revenue ?? 0);
            $committedPurchaseCost = (float) ($committed->get($id)['remaining_net'] ?? 0);
            $committedCost = $actualCost + $committedPurchaseCost;
            $plannedMargin = $plannedRevenue - $plannedCost;
            $committedMargin = $plannedRevenue - $committedCost;
            $actualMargin = $actualRevenue - $actualCost;
            $issues = collect($quality->get($id, []));
            if (! $quote) {
                $issues->push(__('No accepted quote defines planned revenue.'));
            }
            if ($plannedCost <= 0) {
                $issues->push(__('No planned project cost is available.'));
            }

            return [
                'installation_id' => $id,
                'reference' => $installation->project_reference ?: $installation->site_name ?: __('Installation #:id', ['id' => $id]),
                'service_type' => BusinessLineCatalog::normalizeInstallationType($installation->service_type),
                'status' => $installation->status,
                'planned_revenue' => round($plannedRevenue, 2),
                'planned_material_cost' => round($plannedMaterialCost, 2),
                'planned_labour_cost' => round($plannedLabourCost, 2),
                'planned_cost' => round($plannedCost, 2),
                'planned_margin' => round($plannedMargin, 2),
                'planned_margin_percent' => $plannedRevenue !== 0.0 ? round($plannedMargin / $plannedRevenue * 100, 1) : null,
                'committed_purchase_cost' => round($committedPurchaseCost, 2),
                'committed_cost' => round($committedCost, 2),
                'committed_margin' => round($committedMargin, 2),
                'committed_margin_percent' => $plannedRevenue !== 0.0 ? round($committedMargin / $plannedRevenue * 100, 1) : null,
                'actual_revenue' => round($actualRevenue, 2),
                'billed_gross' => round((float) ($sales->get($id)?->billed_gross ?? 0), 2),
                'collected_cash' => round((float) ($sales->get($id)?->collected_cash ?? 0), 2),
                'outstanding_cash' => round(max(0, (float) ($sales->get($id)?->billed_gross ?? 0) - (float) ($sales->get($id)?->collected_cash ?? 0)), 2),
                'actual_material_cost' => round($actualMaterialCost, 2),
                'actual_labour_cost' => round($actualLabourCost, 2),
                'supplier_service_cost' => round($supplierServiceCost, 2),
                'direct_expenses' => round($directExpenses, 2),
                'actual_cost' => round($actualCost, 2),
                'actual_margin' => round($actualMargin, 2),
                'actual_margin_percent' => $actualRevenue !== 0.0 ? round($actualMargin / $actualRevenue * 100, 1) : null,
                'margin_variance' => round($actualMargin - $plannedMargin, 2),
                'has_actuals' => $actualRevenue !== 0.0 || $actualCost !== 0.0,
                'quality' => [
                    'score' => max(0, 100 - ($issues->unique()->count() * 20)),
                    'issues' => $issues->unique()->values()->all(),
                ],
                'sources' => [
                    'quote' => $quote ? ['id' => $quote->id, 'number' => $quote->number, 'url' => route('quotes.show', $quote->id)] : null,
                    'work_orders' => (int) ($plannedLabour->get($id)?->work_orders ?? 0),
                    'sales_documents' => (int) ($sales->get($id)?->documents ?? 0),
                    'time_entries' => (int) ($labour->get($id)?->entries ?? 0),
                    'stock_movements' => (int) ($materials->get($id)?->movements ?? 0),
                    'purchase_documents' => (int) (($supplierServices->get($id)?->documents ?? 0) + ($committed->get($id)['orders'] ?? 0)),
                    'expenses' => (int) ($expenses->get($id)?->documents ?? 0),
                    'url' => route('installations.show', $id),
                ],
            ];
        });
    }

    /** @return Collection<int, array<int, string>> */
    private function qualityIssues(Collection $ids): Collection
    {
        $issues = collect();
        DB::table('stock_movement_lines')->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->whereIn('stock_movements.installation_id', $ids)->where('stock_movements.type', 'issue')->whereNull('stock_movement_lines.unit_cost')
            ->pluck('stock_movements.installation_id')->unique()->each(fn ($id) => $issues->put($id, [__('Material consumption without unit cost.')]));
        DB::table('purchase_documents')->whereIn('installation_id', $ids)->where('status', 'review_required')
            ->pluck('installation_id')->unique()->each(fn ($id) => $issues->put($id, [...$issues->get($id, []), __('Supplier documents are pending validation.')]));
        DB::table('time_entries')->join('work_orders', 'work_orders.id', '=', 'time_entries.work_order_id')
            ->whereIn('work_orders.installation_id', $ids)->whereNotNull('time_entries.ended_at')->whereNull('time_entries.hourly_cost')
            ->pluck('work_orders.installation_id')->unique()->each(fn ($id) => $issues->put($id, [...$issues->get($id, []), __('Time entries are missing hourly cost.')]));

        return $issues;
    }
}
