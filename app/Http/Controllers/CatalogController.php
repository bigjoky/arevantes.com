<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Support\BusinessLineCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(): Response
    {
        return $this->renderCatalog('items');
    }

    public function suppliers(): Response
    {
        return $this->renderCatalog('suppliers');
    }

    public function settings(): Response
    {
        return $this->renderCatalog('settings');
    }

    private function renderCatalog(string $initialTab): Response
    {
        return Inertia::render('catalog/index', [
            'initialTab' => $initialTab,
            'products' => Product::with(['unit:id,name,symbol', 'taxRate:id,name,rate', 'components.product.unit:id,name,symbol'])->orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('legal_name')->get(),
            'units' => Unit::where('is_active', true)->orderBy('name')->get(),
            'taxRates' => TaxRate::where('is_active', true)->orderByDesc('is_default')->orderByDesc('rate')->get(),
            'documentSeries' => DocumentSeries::orderBy('document_type')->get(),
            'accountingAccounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->whereIn('type', ['income', 'expense'])->orderBy('code')->get(['code', 'name', 'type']),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        DB::transaction(fn () => $this->persistProduct(new Product, $this->validateProduct($request)));

        return back()->with('toast', ['type' => 'success', 'message' => __('Product or service created.')]);
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        DB::transaction(fn () => $this->persistProduct($product, $this->validateProduct($request, $product)));

        return back()->with('toast', ['type' => 'success', 'message' => __('Product or service updated.')]);
    }

    public function destroyProduct(Product $product): RedirectResponse
    {
        abort_if(DB::table('product_components')->where('component_product_id', $product->id)->exists(), 422, __('This item is used in a product composition and cannot be deleted.'));
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
        $data = $request->validate([
            'type' => ['required', Rule::in(['product', 'service'])],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products')->ignore($product)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'business_lines' => ['sometimes', 'array'],
            'business_lines.*' => ['required', 'distinct', Rule::in(BusinessLineCatalog::INSTALLATION_TYPES)],
            'business_lines_submitted' => ['sometimes', 'boolean'],
            'unit_id' => ['required', 'exists:units,id'],
            'tax_rate_id' => ['required', 'exists:tax_rates,id'],
            'sale_account_code' => ['required', 'exists:ledger_accounts,code'],
            'purchase_account_code' => ['required', 'exists:ledger_accounts,code'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'track_stock' => ['boolean'],
            'track_serials' => ['boolean'],
            'supply_criticality' => ['sometimes', Rule::in(['low', 'standard', 'critical'])],
            'preferred_lead_time_days' => ['sometimes', 'integer', 'between:0,365'],
            'is_active' => ['boolean'],
            'components' => ['sometimes', 'array'],
            'components.*.component_product_id' => ['required', 'distinct', 'exists:products,id', Rule::notIn([$product?->id])],
            'components.*.quantity' => ['required', 'numeric', 'gt:0'],
            'components.*.fulfillment_kind' => ['required', Rule::in(['stock', 'activity', 'recurring', 'none'])],
            'components.*.visibility' => ['required', Rule::in(['detailed', 'summary', 'hidden'])],
            'components.*.interval_months' => ['nullable', 'integer', 'between:1,120'],
        ]);

        if (($data['business_lines_submitted'] ?? false) && ! array_key_exists('business_lines', $data)) {
            $data['business_lines'] = [];
        }

        unset($data['business_lines_submitted']);

        return $data;
    }

    private function persistProduct(Product $product, array $data): Product
    {
        $components = $data['components'] ?? [];
        unset($data['components']);

        foreach ($components as $component) {
            if ($product->exists && $this->compositionContains((int) $component['component_product_id'], $product->id)) {
                throw ValidationException::withMessages(['components' => __('A product composition cannot contain itself, directly or indirectly.')]);
            }
        }

        if ($components !== []) {
            $data['track_stock'] = false;
            $data['track_serials'] = false;
        }
        $product->fill($data)->save();
        $product->components()->delete();
        $product->components()->createMany(collect($components)->values()->map(fn (array $component, int $position) => [...$component, 'position' => $position + 1])->all());

        if ($components !== []) {
            $costs = Product::whereIn('id', collect($components)->pluck('component_product_id'))->pluck('cost_price', 'id');
            $cost = collect($components)->sum(fn (array $component) => (float) ($costs->get((int) $component['component_product_id']) ?? 0) * (float) $component['quantity']);
            $product->updateQuietly(['cost_price' => round($cost, 2)]);
        }
        $this->refreshParentCosts($product->id);

        return $product;
    }

    private function refreshParentCosts(int $componentProductId, array $visited = []): void
    {
        if (in_array($componentProductId, $visited, true)) {
            return;
        }
        $visited[] = $componentProductId;
        $parentIds = DB::table('product_components')->where('component_product_id', $componentProductId)->pluck('parent_product_id');
        foreach ($parentIds as $parentId) {
            $rows = DB::table('product_components')->where('parent_product_id', $parentId)->get();
            $costs = Product::whereIn('id', $rows->pluck('component_product_id'))->pluck('cost_price', 'id');
            $cost = $rows->sum(fn ($row) => (float) ($costs->get((int) $row->component_product_id) ?? 0) * (float) $row->quantity);
            Product::whereKey($parentId)->update(['cost_price' => round($cost, 2), 'track_stock' => false, 'track_serials' => false]);
            $this->refreshParentCosts((int) $parentId, $visited);
        }
    }

    private function compositionContains(int $productId, int $targetId, array $visited = []): bool
    {
        if ($productId === $targetId) {
            return true;
        }
        if (in_array($productId, $visited, true)) {
            return false;
        }

        $visited[] = $productId;

        return DB::table('product_components')->where('parent_product_id', $productId)->pluck('component_product_id')
            ->contains(fn ($childId) => $this->compositionContains((int) $childId, $targetId, $visited));
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
            'locality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
