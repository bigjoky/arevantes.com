<?php

namespace App\Services\Supply;

use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\ReplenishmentPolicy;
use App\Models\Supplier;
use App\Services\Documents\DocumentNumber;
use App\Services\Documents\PurchaseWorkflow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplenishmentProcurement
{
    public function __construct(
        private readonly ReplenishmentPlanner $planner,
        private readonly DocumentNumber $numbers,
        private readonly PurchaseWorkflow $workflow,
    ) {}

    public function createRequests(array $policyIds, int $userId): Collection
    {
        return DB::transaction(function () use ($policyIds, $userId): Collection {
            $policies = ReplenishmentPolicy::with(['warehouse', 'product.taxRate'])->whereIn('id', $policyIds)->where('is_active', true)->lockForUpdate()->get();
            $recommendations = $this->planner->recommendations($policies)->where('needs_action', true);
            if ($recommendations->isEmpty()) {
                throw ValidationException::withMessages(['policies' => __('The selected policies no longer require replenishment.')]);
            }
            if ($recommendations->contains(fn (array $row) => ! $row['supplier'])) {
                throw ValidationException::withMessages(['policies' => __('Every selected product needs an active supplier catalog source.')]);
            }

            return $recommendations
                ->groupBy(fn (array $row) => "{$row['warehouse']['id']}:{$row['supplier']['id']}")
                ->map(function (Collection $rows) use ($userId): PurchaseDocument {
                    $first = $rows->first();
                    $supplier = Supplier::findOrFail($first['supplier']['id']);
                    $warehouseId = $first['warehouse']['id'];
                    $productModels = Product::with('taxRate:id,rate')->whereIn('id', $rows->pluck('product.id'))->get()->keyBy('id');
                    $subtotal = $tax = 0.0;
                    $lines = $rows->values()->map(function (array $row, int $index) use ($productModels, &$subtotal, &$tax): array {
                        $product = $productModels[$row['product']['id']];
                        $price = (float) $row['supplier']['unit_price'];
                        $taxRate = (float) ($product->taxRate?->rate ?? 0);
                        $base = round($row['suggested_quantity'] * $price, 2);
                        $lineTax = round($base * $taxRate / 100, 2);
                        $subtotal += $base;
                        $tax += $lineTax;

                        return [
                            'product_id' => $product->id,
                            'position' => $index + 1,
                            'description' => $product->name,
                            'quantity' => $row['suggested_quantity'],
                            'unit_price' => $price,
                            'estimated_unit_price' => $price,
                            'discount_percent' => 0,
                            'tax_percent' => $taxRate,
                            'subtotal' => $base,
                            'tax_amount' => $lineTax,
                            'total' => $base + $lineTax,
                        ];
                    });
                    $issued = $this->numbers->next('purchase_rfq', now());
                    $leadTime = (int) $rows->max('supplier.lead_time_days');
                    $document = PurchaseDocument::create([
                        'type' => 'purchase_rfq',
                        'supplier_id' => $supplier->id,
                        'destination_warehouse_id' => $warehouseId,
                        'document_series_id' => $issued['series']->id,
                        'created_by' => $userId,
                        'number' => $issued['number'],
                        'status' => 'draft',
                        'issue_date' => today(),
                        'expected_delivery_date' => $leadTime > 0 ? today()->addDays($leadTime) : null,
                        'subtotal' => round($subtotal, 2),
                        'tax_amount' => round($tax, 2),
                        'shipping_cost' => 0,
                        'amount' => round($subtotal + $tax, 2),
                        'notes' => __('Automatic stock replenishment for :warehouse.', ['warehouse' => $first['warehouse']['name']]),
                    ]);
                    $document->lines()->createMany($lines->all());
                    $this->workflow->record($document, 'created_from_replenishment', $userId, null, 'draft', [
                        'warehouse' => $first['warehouse']['name'],
                    ]);

                    return $document;
                })->values();
        });
    }
}
