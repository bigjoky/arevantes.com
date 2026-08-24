<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\StockMovementLine;
use App\Models\StockReservation;

class QuoteMaterialReadiness
{
    /** @return array<string, mixed> */
    public function preview(Quote $quote): array
    {
        $quote->loadMissing(['lines.product', 'lines.components.product']);
        $requirements = $quote->lines->where('is_selected', true)->flatMap(function ($line) {
            $components = $line->components->whereIn('fulfillment_kind', ['stock', 'material'])
                ->filter(fn ($component) => $component->product?->track_stock)
                ->map(fn ($component): array => ['product' => $component->product, 'quantity' => (float) $component->quantity * (float) $line->quantity]);

            return $components->isNotEmpty() ? $components : ($line->product?->track_stock
                ? collect([['product' => $line->product, 'quantity' => (float) $line->quantity]])
                : collect());
        })->groupBy(fn (array $item) => $item['product']->id)
            ->map(fn ($items): array => ['product' => $items->first()['product'], 'quantity' => $items->sum('quantity')]);

        $productIds = $requirements->keys();
        $onHand = StockMovementLine::whereIn('product_id', $productIds)->groupBy('product_id')->selectRaw('product_id, sum(quantity) quantity')->pluck('quantity', 'product_id');
        $reserved = StockReservation::whereIn('product_id', $productIds)->where('status', 'active')->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity - consumed_quantity) quantity')->pluck('quantity', 'product_id');
        $rows = $requirements->map(function (array $requirement, int $productId) use ($onHand, $reserved): array {
            $required = (float) $requirement['quantity'];
            $available = max(0, (float) ($onHand[$productId] ?? 0) - (float) ($reserved[$productId] ?? 0));

            return ['product_id' => $productId, 'sku' => $requirement['product']->sku, 'name' => $requirement['product']->name,
                'required_quantity' => round($required, 3), 'available_quantity' => round($available, 3),
                'shortage_quantity' => round(max(0, $required - $available), 3)];
        })->values();

        return ['items' => $rows->count(), 'shortage_items' => $rows->where('shortage_quantity', '>', 0)->count(),
            'shortage_quantity' => round((float) $rows->sum('shortage_quantity'), 3), 'rows' => $rows->all()];
    }
}
