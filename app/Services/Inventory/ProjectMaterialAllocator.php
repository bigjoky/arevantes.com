<?php

namespace App\Services\Inventory;

use App\Models\PurchaseDocument;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Services\Documents\SalesDocumentConversion;
use Illuminate\Support\Collection;

class ProjectMaterialAllocator
{
    public function __construct(private SalesDocumentConversion $conversion) {}

    /**
     * @param  Collection<int, array{product_id: int, quantity: float|int}>  $receivedLines
     */
    public function allocate(PurchaseDocument $document, int $warehouseId, Collection $receivedLines): int
    {
        if (! Warehouse::whereKey($warehouseId)->where('is_usable', true)->exists()) {
            return 0;
        }
        if ($document->sales_document_id) {
            return $this->allocateSalesOrder($document, $warehouseId, $receivedLines);
        }

        if (! $document->installation_id) {
            return 0;
        }

        $created = 0;

        foreach ($receivedLines->groupBy('product_id') as $productId => $lines) {
            $planned = (float) $document->installation->materialRequirements()
                ->where('product_id', $productId)
                ->sum('planned_quantity');
            if ($planned <= 0) {
                continue;
            }

            $consumed = abs((float) StockMovementLine::query()
                ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
                ->where('stock_movements.installation_id', $document->installation_id)
                ->where('stock_movements.type', 'issue')
                ->where('stock_movement_lines.product_id', $productId)
                ->where('stock_movement_lines.quantity', '<', 0)
                ->sum('stock_movement_lines.quantity'));
            $reserved = (float) StockReservation::query()
                ->where('installation_id', $document->installation_id)
                ->where('product_id', $productId)
                ->where('status', 'active')
                ->selectRaw('coalesce(sum(quantity - consumed_quantity), 0) as total')
                ->value('total');
            $needed = max(0, $planned - $consumed - $reserved);
            $quantity = min($needed, (float) $lines->sum('quantity'));

            if ($quantity <= 0) {
                continue;
            }

            StockReservation::create([
                'installation_id' => $document->installation_id,
                'work_order_id' => $document->work_order_id,
                'warehouse_id' => $warehouseId,
                'product_id' => (int) $productId,
                'quantity' => $quantity,
                'status' => 'active',
            ]);
            $created++;
        }

        return $created;
    }

    private function allocateSalesOrder(PurchaseDocument $document, int $warehouseId, Collection $receivedLines): int
    {
        $order = $document->salesOrder()->with('lines.product')->firstOrFail();
        $progress = collect($this->conversion->summary($order)['lines'])->keyBy('id');
        $required = $order->lines->filter(fn ($line) => $line->product?->track_stock)->groupBy('product_id')->map(
            fn ($lines) => $lines->sum(fn ($line) => max(0, (float) $line->quantity - (float) ($progress[$line->id]['delivered_quantity'] ?? 0)))
        );
        $created = 0;

        foreach ($receivedLines->groupBy('product_id') as $productId => $lines) {
            $reserved = (float) StockReservation::where('sales_document_id', $order->id)->where('product_id', $productId)->where('status', 'active')
                ->selectRaw('coalesce(sum(quantity - consumed_quantity), 0) as total')->value('total');
            $quantity = min(max(0, (float) ($required[$productId] ?? 0) - $reserved), (float) $lines->sum('quantity'));
            if ($quantity <= 0) {
                continue;
            }

            StockReservation::create([
                'installation_id' => $order->installation_id,
                'sales_document_id' => $order->id,
                'warehouse_id' => $warehouseId,
                'product_id' => (int) $productId,
                'quantity' => $quantity,
                'status' => 'active',
            ]);
            $created++;
        }

        return $created;
    }
}
