<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\PurchaseDocumentLine;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

class StockPositionService
{
    public function positions(): Collection
    {
        $onHand = StockMovementLine::query()
            ->selectRaw('warehouse_id, product_id, sum(quantity) quantity')
            ->groupBy('warehouse_id', 'product_id')
            ->get()
            ->keyBy(fn ($row) => $this->key($row->warehouse_id, $row->product_id));
        $reserved = StockReservation::query()
            ->where('status', 'active')
            ->selectRaw('warehouse_id, product_id, sum(quantity - consumed_quantity) quantity')
            ->groupBy('warehouse_id', 'product_id')
            ->get()
            ->keyBy(fn ($row) => $this->key($row->warehouse_id, $row->product_id));
        $inTransit = PurchaseDocumentLine::query()
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_lines.purchase_document_id')
            ->where('purchase_documents.type', 'purchase_order')
            ->whereIn('purchase_documents.status', ['ordered', 'partially_received'])
            ->whereNotNull('purchase_documents.destination_warehouse_id')
            ->selectRaw('purchase_documents.destination_warehouse_id warehouse_id, purchase_document_lines.product_id, sum(purchase_document_lines.quantity - purchase_document_lines.received_quantity) quantity, count(distinct purchase_documents.id) order_count, min(coalesce(purchase_documents.supplier_confirmed_delivery_date, purchase_documents.expected_delivery_date)) next_delivery_date')
            ->groupBy('purchase_documents.destination_warehouse_id', 'purchase_document_lines.product_id')
            ->get()
            ->keyBy(fn ($row) => $this->key($row->warehouse_id, $row->product_id));

        $keys = $onHand->keys()->merge($reserved->keys())->merge($inTransit->keys())->unique();
        $warehouseIds = $keys->map(fn (string $key) => (int) explode(':', $key)[0])->unique();
        $productIds = $keys->map(fn (string $key) => (int) explode(':', $key)[1])->unique();
        $warehouses = Warehouse::whereKey($warehouseIds)->get(['id', 'code', 'name'])->keyBy('id');
        $products = Product::whereKey($productIds)->get(['id', 'sku', 'name', 'track_serials'])->keyBy('id');

        return $keys->map(function (string $key) use ($onHand, $reserved, $inTransit, $warehouses, $products): ?array {
            [$warehouseId, $productId] = array_map('intval', explode(':', $key));
            $warehouse = $warehouses->get($warehouseId);
            $product = $products->get($productId);
            if (! $warehouse || ! $product) {
                return null;
            }

            $physical = round((float) ($onHand[$key]->quantity ?? 0), 3);
            $committed = round((float) ($reserved[$key]->quantity ?? 0), 3);
            $incoming = round(max(0, (float) ($inTransit[$key]->quantity ?? 0)), 3);
            $available = round($physical - $committed, 3);

            return [
                'warehouse' => $warehouse->only(['id', 'code', 'name']),
                'product' => $product->only(['id', 'sku', 'name', 'track_serials']),
                'on_hand' => $physical,
                'reserved' => $committed,
                'available' => $available,
                'in_transit' => $incoming,
                'projected' => round($available + $incoming, 3),
                'inbound_order_count' => (int) ($inTransit[$key]->order_count ?? 0),
                'next_delivery_date' => $inTransit[$key]->next_delivery_date ?? null,
            ];
        })->filter()->sortBy(fn (array $position) => strtolower($position['warehouse']['name'].'|'.$position['product']['name']))->values();
    }

    private function key(int $warehouseId, int $productId): string
    {
        return "{$warehouseId}:{$productId}";
    }
}
