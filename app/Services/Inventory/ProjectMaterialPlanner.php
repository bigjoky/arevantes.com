<?php

namespace App\Services\Inventory;

use App\Models\Installation;
use App\Models\Quote;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectMaterialPlanner
{
    public function syncFromQuote(Quote $quote, Installation $installation): void
    {
        $quote->loadMissing(['lines.product', 'lines.components.product']);

        DB::transaction(function () use ($quote, $installation): void {
            $installation->materialRequirements()->where('source', 'quote')->delete();

            $selectedLines = $quote->lines->where('is_selected', true);
            $componentRequirements = $selectedLines->flatMap(function ($line) {
                return $line->components
                    ->whereIn('fulfillment_kind', ['stock', 'material'])
                    ->filter(fn ($component) => $component->product?->track_stock)
                    ->map(fn ($component) => (object) [
                        'product_id' => $component->product_id,
                        'product' => $component->product,
                        'description' => $component->description,
                        'quantity' => (float) $component->quantity * (float) $line->quantity,
                        'planned_unit_cost' => $component->planned_unit_cost,
                    ]);
            });
            $legacyRequirements = $selectedLines
                ->filter(fn ($line) => $line->components->isEmpty() && $line->product?->track_stock)
                ->map(fn ($line) => (object) [
                    'product_id' => $line->product_id,
                    'product' => $line->product,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'planned_unit_cost' => $line->product->cost_price,
                ]);
            $requirements = $componentRequirements->concat($legacyRequirements)
                ->groupBy('product_id')
                ->map(function (Collection $items) use ($quote): array {
                    $product = $items->first()->product;

                    return [
                        'quote_id' => $quote->id,
                        'product_id' => $product->id,
                        'source' => 'quote',
                        'sku' => $product->sku,
                        'description' => $items->first()->description ?: $product->name,
                        'planned_quantity' => $items->sum(fn ($item) => (float) $item->quantity),
                        'planned_unit_cost' => $items->first()->planned_unit_cost ?: $product->cost_price,
                    ];
                })
                ->values()
                ->all();

            $installation->materialRequirements()->createMany($requirements);
        });
    }

    /** @return array{planned_cost: float, actual_cost: float, variance: float, shortage_items: int, shortage_quantity: float, rows: array<int, array<string, mixed>>} */
    public function summary(Installation $installation): array
    {
        $requirements = $installation->materialRequirements()->with('product:id,sku,name')->get();
        $productIds = $requirements->pluck('product_id')->filter()->unique()->values();
        $actuals = StockMovementLine::query()
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->join('products', 'products.id', '=', 'stock_movement_lines.product_id')
            ->where('stock_movements.installation_id', $installation->id)
            ->where('stock_movements.type', 'issue')
            ->where('stock_movement_lines.quantity', '<', 0)
            ->groupBy('stock_movement_lines.product_id', 'products.sku', 'products.name', 'products.cost_price')
            ->selectRaw('stock_movement_lines.product_id, products.sku, products.name, sum(-stock_movement_lines.quantity) as actual_quantity, sum(-stock_movement_lines.quantity * coalesce(stock_movement_lines.unit_cost, products.cost_price, 0)) as actual_cost')
            ->get()
            ->keyBy('product_id');

        $onHand = StockMovementLine::query()
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity) as quantity')
            ->pluck('quantity', 'product_id');
        $reserved = StockReservation::query()
            ->whereIn('product_id', $productIds)
            ->where('status', 'active')
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity - consumed_quantity) as quantity')
            ->pluck('quantity', 'product_id');
        $projectReserved = StockReservation::query()
            ->where('installation_id', $installation->id)
            ->whereIn('product_id', $productIds)
            ->where('status', 'active')
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity - consumed_quantity) as quantity')
            ->pluck('quantity', 'product_id');
        $inbound = DB::table('purchase_document_lines')
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_lines.purchase_document_id')
            ->where('purchase_documents.installation_id', $installation->id)
            ->whereIn('purchase_document_lines.product_id', $productIds)
            ->where(function ($query): void {
                $query->where(fn ($query) => $query->where('purchase_documents.type', 'purchase_rfq')->whereIn('purchase_documents.status', ['draft', 'sent']))
                    ->orWhere(fn ($query) => $query->where('purchase_documents.type', 'purchase_order')->whereIn('purchase_documents.status', ['ordered', 'partially_received']));
            })
            ->groupBy('purchase_document_lines.product_id')
            ->selectRaw('purchase_document_lines.product_id, sum(purchase_document_lines.quantity - purchase_document_lines.received_quantity) as quantity')
            ->pluck('quantity', 'product_id');

        $rows = $requirements->map(function ($requirement) use ($actuals, $onHand, $reserved, $projectReserved, $inbound): array {
            $actual = $actuals->pull($requirement->product_id);
            $plannedQuantity = (float) $requirement->planned_quantity;
            $plannedCost = $plannedQuantity * (float) $requirement->planned_unit_cost;
            $actualQuantity = (float) ($actual?->actual_quantity ?? 0);
            $actualCost = (float) ($actual?->actual_cost ?? 0);
            $remainingQuantity = max(0, $plannedQuantity - $actualQuantity);
            $reservedQuantity = (float) ($projectReserved[$requirement->product_id] ?? 0);
            $freeQuantity = max(0, (float) ($onHand[$requirement->product_id] ?? 0) - (float) ($reserved[$requirement->product_id] ?? 0));
            $inboundQuantity = (float) ($inbound[$requirement->product_id] ?? 0);
            $shortageQuantity = $requirement->product_id
                ? max(0, $remainingQuantity - $reservedQuantity - $freeQuantity - $inboundQuantity)
                : 0;

            return [
                'product_id' => $requirement->product_id,
                'sku' => $requirement->sku,
                'description' => $requirement->description,
                'planned_quantity' => $plannedQuantity,
                'actual_quantity' => $actualQuantity,
                'quantity_variance' => $actualQuantity - $plannedQuantity,
                'planned_cost' => round($plannedCost, 2),
                'actual_cost' => round($actualCost, 2),
                'cost_variance' => round($actualCost - $plannedCost, 2),
                'remaining_quantity' => round($remainingQuantity, 3),
                'available_quantity' => round($freeQuantity, 3),
                'reserved_quantity' => round($reservedQuantity, 3),
                'inbound_quantity' => round($inboundQuantity, 3),
                'shortage_quantity' => round($shortageQuantity, 3),
                'is_unplanned' => false,
            ];
        });

        foreach ($actuals as $actual) {
            $actualQuantity = (float) $actual->actual_quantity;
            $actualCost = (float) $actual->actual_cost;
            $rows->push([
                'product_id' => (int) $actual->product_id,
                'sku' => $actual->sku,
                'description' => $actual->name,
                'planned_quantity' => 0,
                'actual_quantity' => $actualQuantity,
                'quantity_variance' => $actualQuantity,
                'planned_cost' => 0,
                'actual_cost' => round($actualCost, 2),
                'cost_variance' => round($actualCost, 2),
                'remaining_quantity' => 0,
                'available_quantity' => 0,
                'reserved_quantity' => 0,
                'inbound_quantity' => 0,
                'shortage_quantity' => 0,
                'is_unplanned' => true,
            ]);
        }

        $plannedCost = round($rows->sum('planned_cost'), 2);
        $actualCost = round($rows->sum('actual_cost'), 2);

        return [
            'planned_cost' => $plannedCost,
            'actual_cost' => $actualCost,
            'variance' => round($actualCost - $plannedCost, 2),
            'shortage_items' => $rows->where('shortage_quantity', '>', 0)->count(),
            'shortage_quantity' => round($rows->sum('shortage_quantity'), 3),
            'rows' => $rows->values()->all(),
        ];
    }
}
