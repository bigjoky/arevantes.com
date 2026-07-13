<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('catalog/index', [
            'products' => Product::with(['unit:id,name,symbol', 'taxRate:id,name,rate'])->orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('legal_name')->get(),
            'units' => Unit::where('is_active', true)->orderBy('name')->get(),
            'taxRates' => TaxRate::where('is_active', true)->orderByDesc('is_default')->orderByDesc('rate')->get(),
            'documentSeries' => DocumentSeries::orderBy('document_type')->get(),
            'accountingAccounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->whereIn('type', ['income', 'expense'])->orderBy('code')->get(['code', 'name', 'type']),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        Product::create($this->validateProduct($request));

        return back()->with('toast', ['type' => 'success', 'message' => __('Product or service created.')]);
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validateProduct($request, $product));

        return back()->with('toast', ['type' => 'success', 'message' => __('Product or service updated.')]);
    }

    public function destroyProduct(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Product or service deleted.')]);
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        Supplier::create($this->validateSupplier($request));

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier created.')]);
    }

    public function updateSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validateSupplier($request));

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier updated.')]);
    }

    public function destroySupplier(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier deleted.')]);
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['product', 'service'])],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products')->ignore($product)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit_id' => ['required', 'exists:units,id'],
            'tax_rate_id' => ['required', 'exists:tax_rates,id'],
            'sale_account_code' => ['required', 'exists:ledger_accounts,code'],
            'purchase_account_code' => ['required', 'exists:ledger_accounts,code'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'track_stock' => ['boolean'],
            'track_serials' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateSupplier(Request $request): array
    {
        return $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
