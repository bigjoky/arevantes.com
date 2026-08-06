<?php

namespace App\Services\Supply;

use App\Models\PurchaseDocumentLine;
use App\Models\ReplenishmentPolicy;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\SupplierProduct;
use Illuminate\Support\Collection;

class ReplenishmentPlanner
{
    public function recommendations(?Collection $policies = null): Collection
    {
        $policies ??= ReplenishmentPolicy::with(['warehouse:id,code,name', 'product:id,sku,name,track_serials'])
            ->where('is_active', true)
            ->get();
        $onHand = StockMovementLine::query()
            ->selectRaw('warehouse_id, product_id, sum(quantity) quantity')
            ->groupBy('warehouse_id', 'product_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->warehouse_id}:{$row->product_id}");
        $reserved = StockReservation::query()
            ->where('status', 'active')
            ->selectRaw('warehouse_id, product_id, sum(quantity - consumed_quantity) quantity')
            ->groupBy('warehouse_id', 'product_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->warehouse_id}:{$row->product_id}");
        $inbound = PurchaseDocumentLine::query()
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_lines.purchase_document_id')
            ->whereNotNull('purchase_documents.destination_warehouse_id')
            ->where(function ($query) {
                $query->where(fn ($query) => $query->where('purchase_documents.type', 'purchase_rfq')->whereIn('purchase_documents.status', ['draft', 'sent', 'quoted']))
                    ->orWhere(fn ($query) => $query->where('purchase_documents.type', 'purchase_order')->whereIn('purchase_documents.status', ['ordered', 'partially_received']));
            })
            ->selectRaw('purchase_documents.destination_warehouse_id warehouse_id, purchase_document_lines.product_id, sum(purchase_document_lines.quantity - purchase_document_lines.received_quantity) quantity')
            ->groupBy('purchase_documents.destination_warehouse_id', 'purchase_document_lines.product_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->warehouse_id}:{$row->product_id}");
        $sources = SupplierProduct::with('supplier:id,legal_name,trade_name,is_active')
            ->where('is_active', true)
            ->where('last_unit_price', '>', 0)
            ->orderByDesc('is_preferred')
            ->orderBy('last_unit_price')
            ->get()
            ->groupBy('product_id');

        return $policies->map(function (ReplenishmentPolicy $policy) use ($onHand, $reserved, $inbound, $sources): array {
            $key = "{$policy->warehouse_id}:{$policy->product_id}";
            $physical = (float) ($onHand[$key]->quantity ?? 0);
            $committed = (float) ($reserved[$key]->quantity ?? 0);
            $incoming = (float) ($inbound[$key]->quantity ?? 0);
            $projected = $physical - $committed + $incoming;
            $raw = $projected <= (float) $policy->reorder_point
                ? max(0, (float) $policy->target_stock - $projected)
                : 0;
            $source = $sources->get($policy->product_id, collect())
                ->first(fn (SupplierProduct $item) => $item->supplier?->is_active);
            $suggested = $this->purchaseQuantity($raw, $source, $policy->product->track_serials);

            return [
                'policy_id' => $policy->id,
                'warehouse' => $policy->warehouse->only(['id', 'code', 'name']),
                'product' => $policy->product->only(['id', 'sku', 'name']),
                'reorder_point' => (float) $policy->reorder_point,
                'target_stock' => (float) $policy->target_stock,
                'on_hand' => round($physical, 3),
                'reserved' => round($committed, 3),
                'inbound' => round($incoming, 3),
                'projected' => round($projected, 3),
                'suggested_quantity' => $suggested,
                'supplier' => $source?->supplier ? [
                    'id' => $source->supplier->id,
                    'name' => $source->supplier->trade_name ?: $source->supplier->legal_name,
                    'unit_price' => (float) ($source->last_unit_price ?? 0),
                    'lead_time_days' => $source->lead_time_days,
                    'is_preferred' => $source->is_preferred,
                ] : null,
                'needs_action' => $suggested > 0,
            ];
        })->sortByDesc('needs_action')->values();
    }

    private function purchaseQuantity(float $quantity, ?SupplierProduct $source, bool $serialized): float
    {
        if ($quantity <= 0) {
            return 0;
        }
        $minimum = (float) ($source?->minimum_order_quantity ?? 1);
        $multiple = max(0.001, (float) ($source?->order_multiple ?? 1));
        $result = max($quantity, $minimum);
        $result = ceil(($result / $multiple) - 1e-9) * $multiple;

        return round($serialized ? ceil($result) : $result, 3);
    }
}
