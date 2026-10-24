<?php

namespace App\Services\Inventory;

use App\Models\Installation;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\WorkOrder;
use App\Services\Documents\DocumentNumber;
use App\Services\Operations\FinancialClearanceGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectProcurement
{
    public function __construct(
        private readonly ProjectMaterialPlanner $materials,
        private readonly DocumentNumber $numbers,
        private readonly FinancialClearanceGuard $financialClearance,
    ) {}

    public function createRfq(WorkOrder $workOrder, int $supplierId, int $userId): PurchaseDocument
    {
        return DB::transaction(function () use ($workOrder, $supplierId, $userId): PurchaseDocument {
            Installation::whereKey($workOrder->installation_id)->lockForUpdate()->firstOrFail();
            $workOrder = WorkOrder::with('installation')->lockForUpdate()->findOrFail($workOrder->id);
            $this->financialClearance->assertAllows($workOrder->installation, 'procure');
            $shortages = collect($this->materials->summary($workOrder->installation)['rows'])
                ->where('shortage_quantity', '>', 0)
                ->values();

            if ($shortages->isEmpty()) {
                throw ValidationException::withMessages(['materials' => __('There are no material shortages to purchase.')]);
            }

            $products = Product::with('taxRate:id,rate')->whereIn('id', $shortages->pluck('product_id'))->get()->keyBy('id');
            $subtotal = 0.0;
            $taxAmount = 0.0;
            $lines = $shortages->map(function (array $shortage, int $index) use ($products, &$subtotal, &$taxAmount): array {
                $product = $products->get($shortage['product_id']);
                $quantity = $product->track_serials ? ceil($shortage['shortage_quantity']) : $shortage['shortage_quantity'];
                $unitPrice = (float) $product->cost_price;
                $taxPercent = (float) ($product->taxRate?->rate ?? 0);
                $base = round($quantity * $unitPrice, 2);
                $tax = round($base * $taxPercent / 100, 2);
                $subtotal += $base;
                $taxAmount += $tax;

                return [
                    'product_id' => $product->id,
                    'position' => $index + 1,
                    'description' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => 0,
                    'tax_percent' => $taxPercent,
                    'subtotal' => $base,
                    'tax_amount' => $tax,
                    'total' => $base + $tax,
                ];
            });
            $issued = $this->numbers->next('purchase_rfq', now());
            $document = PurchaseDocument::create([
                'type' => 'purchase_rfq',
                'installation_id' => $workOrder->installation_id,
                'work_order_id' => $workOrder->id,
                'supplier_id' => $supplierId,
                'document_series_id' => $issued['series']->id,
                'created_by' => $userId,
                'number' => $issued['number'],
                'status' => 'draft',
                'issue_date' => now()->toDateString(),
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($taxAmount, 2),
                'amount' => round($subtotal + $taxAmount, 2),
                'notes' => __('Generated from material shortages for work order :number.', ['number' => $workOrder->number]),
            ]);
            $document->lines()->createMany($lines->all());

            return $document;
        });
    }
}
