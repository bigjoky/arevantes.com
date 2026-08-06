<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Supply\SupplierScorecards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierIntelligenceController extends Controller
{
    public function index(SupplierScorecards $scorecards): Response
    {
        return Inertia::render('purchases/suppliers/index', ['suppliers' => $scorecards->all()]);
    }

    public function show(Supplier $supplier, SupplierScorecards $scorecards): Response
    {
        $supplier->load([
            'products' => fn ($query) => $query->with([
                'product:id,sku,name',
                'prices' => fn ($query) => $query->limit(12)->with('purchaseDocumentLine.document:id,number,type,issue_date'),
            ])->orderByDesc('is_preferred')->orderBy('id'),
        ]);

        return Inertia::render('purchases/suppliers/show', [
            'supplier' => $supplier,
            'scorecard' => $scorecards->for($supplier),
            'availableProducts' => Product::where('is_active', true)
                ->whereNotIn('id', $supplier->products->pluck('product_id'))
                ->orderBy('name')
                ->get(['id', 'sku', 'name']),
        ]);
    }

    public function storeProduct(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $this->validateProduct($request, $supplier);
        DB::transaction(function () use ($supplier, $data): void {
            if ($data['is_preferred'] ?? false) {
                SupplierProduct::where('product_id', $data['product_id'])->update(['is_preferred' => false]);
            }
            $supplier->products()->create($data);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier product added.')]);
    }

    public function updateProduct(Request $request, SupplierProduct $supplierProduct): RedirectResponse
    {
        $data = $this->validateProduct($request, $supplierProduct->supplier, $supplierProduct);
        DB::transaction(function () use ($supplierProduct, $data): void {
            if ($data['is_preferred'] ?? false) {
                SupplierProduct::where('product_id', $supplierProduct->product_id)->whereKeyNot($supplierProduct->id)->update(['is_preferred' => false]);
            }
            $supplierProduct->update(collect($data)->except('product_id')->all());
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier product updated.')]);
    }

    public function destroyProduct(SupplierProduct $supplierProduct): RedirectResponse
    {
        $supplierProduct->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier product removed.')]);
    }

    private function validateProduct(Request $request, Supplier $supplier, ?SupplierProduct $supplierProduct = null): array
    {
        return $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true), Rule::unique('supplier_products')->where('supplier_id', $supplier->id)->ignore($supplierProduct)],
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'supplier_description' => ['nullable', 'string', 'max:255'],
            'minimum_order_quantity' => ['required', 'numeric', 'gt:0'],
            'order_multiple' => ['required', 'numeric', 'gt:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'last_unit_price' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_preferred' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }
}
