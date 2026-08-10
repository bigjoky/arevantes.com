<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductCompositionResolver
{
    /** @return array<int, array<string, mixed>> */
    public function resolve(Product $product): array
    {
        return $this->expand($product, 1)->groupBy(fn (array $item) => implode('|', [$item['product_id'], $item['fulfillment_kind'], $item['visibility'], $item['interval_months']]))
            ->map(function (Collection $items): array {
                $first = $items->first();

                return [...$first, 'quantity' => $items->sum('quantity')];
            })->values()->all();
    }

    private function expand(Product $product, float $multiplier, array $visited = []): Collection
    {
        if (in_array($product->id, $visited, true)) {
            return collect();
        }
        $visited[] = $product->id;
        $product->loadMissing('components.product.unit');

        return $product->components->flatMap(function ($component) use ($multiplier, $visited): Collection {
            $quantity = $multiplier * (float) $component->quantity;
            $child = $component->product;
            $child->loadMissing('components');
            if ($child->components->isNotEmpty()) {
                return $this->expand($child, $quantity, $visited);
            }

            return collect([[
                'product_id' => $child->id,
                'unit_id' => $child->unit_id,
                'kind' => $child->type === 'service' ? 'service' : 'material',
                'fulfillment_kind' => $component->fulfillment_kind,
                'visibility' => $component->visibility,
                'description' => $child->description ?: $child->name,
                'quantity' => $quantity,
                'planned_unit_cost' => (float) $child->cost_price,
                'interval_months' => $component->interval_months,
                'unit' => $child->unit?->only(['id', 'symbol']),
            ]]);
        });
    }
}
