<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\Installation;
use App\Models\PurchaseDocumentLine;
use App\Models\SalesDocument;
use App\Models\StockMovementLine;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

class ProjectProfitability
{
    public function __construct(private PurchaseCommitmentAnalysis $commitments) {}

    /** @param Collection<int, array<string, mixed>>|null $purchaseCommitments */
    public function forYear(int $year, ?Collection $purchaseCommitments = null): Collection
    {
        $revenue = SalesDocument::whereIn('type', ['invoice', 'credit_note'])->whereNotNull('locked_at')->whereNotNull('installation_id')
            ->whereYear('issue_date', $year)->get()->groupBy('installation_id')
            ->map(fn ($items) => (float) $items->sum(fn ($item) => (float) $item->subtotal - (float) $item->discount_amount));
        $material = StockMovementLine::whereHas('movement', fn ($query) => $query->whereNotNull('installation_id')->whereYear('posted_at', $year))
            ->with('movement:id,installation_id')->get()->filter(fn ($line) => (float) $line->quantity < 0)
            ->groupBy(fn ($line) => $line->movement->installation_id)
            ->map(fn ($items) => (float) $items->sum(fn ($line) => abs((float) $line->quantity) * (float) ($line->unit_cost ?? 0)));
        $labour = TimeEntry::whereNotNull('ended_at')->whereYear('started_at', $year)
            ->whereHas('workOrder', fn ($query) => $query->whereNotNull('installation_id'))->with('workOrder:id,installation_id')->get()
            ->groupBy(fn ($entry) => $entry->workOrder->installation_id)->map(fn ($entries) => (float) $entries->sum(fn ($entry) => $entry->cost));
        $supplierServices = PurchaseDocumentLine::query()
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_lines.purchase_document_id')
            ->join('products', 'products.id', '=', 'purchase_document_lines.product_id')
            ->whereIn('purchase_documents.type', ['supplier_invoice', 'supplier_credit_note'])
            ->whereNot('purchase_documents.status', 'review_required')
            ->whereNotNull('purchase_documents.installation_id')
            ->where('products.type', 'service')
            ->whereYear('purchase_documents.issue_date', $year)
            ->selectRaw('purchase_documents.installation_id, coalesce(sum(purchase_document_lines.subtotal), 0) total')
            ->groupBy('purchase_documents.installation_id')->pluck('total', 'installation_id');
        $directExpenses = Expense::whereNotNull('installation_id')->whereYear('expense_date', $year)
            ->selectRaw('installation_id, coalesce(sum(subtotal), 0) total')->groupBy('installation_id')->pluck('total', 'installation_id');
        $committedCosts = ($purchaseCommitments ?? $this->commitments->build($year)['rows'])
            ->whereNotNull('installationId')
            ->groupBy('installationId')
            ->map(fn (Collection $items) => (float) $items->sum('remainingNet'));
        $ids = $revenue->keys()->concat($material->keys())->concat($labour->keys())->concat($supplierServices->keys())->concat($directExpenses->keys())->concat($committedCosts->keys())->unique();
        $installations = Installation::whereIn('id', $ids)->get(['id', 'project_reference', 'site_name'])->keyBy('id');

        return $ids->map(function ($id) use ($revenue, $material, $labour, $supplierServices, $directExpenses, $committedCosts, $installations) {
            $income = (float) ($revenue[$id] ?? 0);
            $materialCost = (float) ($material[$id] ?? 0);
            $labourCost = (float) ($labour[$id] ?? 0);
            $supplierCost = (float) ($supplierServices[$id] ?? 0);
            $expenseCost = (float) ($directExpenses[$id] ?? 0);
            $committedCost = (float) ($committedCosts[$id] ?? 0);
            $totalCost = $materialCost + $labourCost + $supplierCost + $expenseCost;
            $margin = $income - $totalCost;
            $projectedMargin = $margin - $committedCost;
            $installation = $installations[$id] ?? null;

            return [
                'installation_id' => (int) $id,
                'reference' => $installation?->project_reference ?: $installation?->site_name,
                'revenue' => round($income, 2),
                'tracked_cost' => round($materialCost, 2),
                'labour_cost' => round($labourCost, 2),
                'supplier_cost' => round($supplierCost, 2),
                'direct_expenses' => round($expenseCost, 2),
                'total_cost' => round($totalCost, 2),
                'committed_cost' => round($committedCost, 2),
                'margin' => round($margin, 2),
                'margin_percent' => $income !== 0.0 ? round($margin / $income * 100, 1) : null,
                'projected_margin' => round($projectedMargin, 2),
                'projected_margin_percent' => $income !== 0.0 ? round($projectedMargin / $income * 100, 1) : null,
            ];
        })->sortByDesc('margin')->values();
    }
}
