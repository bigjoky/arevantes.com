<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Services\Documents\DocumentNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesProcurement
{
    public function __construct(private SalesStockAllocator $stock, private DocumentNumber $numbers) {}

    public function createRequest(SalesDocument $order, Supplier $supplier, int $userId): PurchaseDocument
    {
        return $this->createRequests($order, collect([$supplier]), $userId)->firstOrFail();
    }

    /** @param Collection<int, Supplier> $suppliers */
    public function createRequests(SalesDocument $order, Collection $suppliers, int $userId): Collection
    {
        return DB::transaction(function () use ($order, $suppliers, $userId): Collection {
            $order = SalesDocument::lockForUpdate()->with('lines.product')->findOrFail($order->id);
            abort_unless($order->type === 'order', 422, __('Only sales orders can create purchase requests.'));
            if ($suppliers->isEmpty()) {
                throw ValidationException::withMessages(['supplier_ids' => __('Select at least one supplier.')]);
            }

            $shortages = collect($this->stock->status($order)['lines'])->where('shortage', '>', 0);
            if ($shortages->isEmpty()) {
                throw ValidationException::withMessages(['supplier_ids' => __('There are no unrequested stock shortages on this order.')]);
            }

            $products = Product::with('taxRate:id,rate')->whereIn('id', $shortages->pluck('product_id'))->get()->keyBy('id');
            $subtotal = $tax = $amount = 0;
            $lines = $shortages->values()->map(function (array $shortage, int $position) use ($products, &$subtotal, &$tax, &$amount): array {
                $product = $products->get($shortage['product_id']);
                $quantity = (float) $shortage['shortage'];
                $price = (float) $product->cost_price;
                $rate = (float) ($product->taxRate?->rate ?? 0);
                $base = round($quantity * $price, 2);
                $lineTax = round($base * $rate / 100, 2);
                $subtotal += $base;
                $tax += $lineTax;
                $amount += $base + $lineTax;

                return [
                    'product_id' => $product->id,
                    'position' => $position + 1,
                    'description' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'estimated_unit_price' => $price,
                    'discount_percent' => 0,
                    'tax_percent' => $rate,
                    'subtotal' => $base,
                    'tax_amount' => $lineTax,
                    'total' => $base + $lineTax,
                ];
            })->all();
            $batchId = (string) Str::uuid();
            $requests = $suppliers->unique('id')->values()->map(function (Supplier $supplier) use ($order, $userId, $subtotal, $tax, $amount, $lines, $batchId): PurchaseDocument {
                $issued = $this->numbers->next('purchase_rfq', now());
                $request = PurchaseDocument::create([
                    'type' => 'purchase_rfq',
                    'installation_id' => $order->installation_id,
                    'sales_document_id' => $order->id,
                    'procurement_batch_id' => $batchId,
                    'supplier_id' => $supplier->id,
                    'document_series_id' => $issued['series']->id,
                    'created_by' => $userId,
                    'number' => $issued['number'],
                    'status' => 'draft',
                    'issue_date' => now()->toDateString(),
                    'subtotal' => round($subtotal, 2),
                    'tax_amount' => round($tax, 2),
                    'amount' => round($amount, 2),
                    'notes' => __('Stock replenishment for sales order :number.', ['number' => $order->number]),
                ]);
                $request->lines()->createMany($lines);

                return $request;
            });

            return $requests;
        });
    }
}
