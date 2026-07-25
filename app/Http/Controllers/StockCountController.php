<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockMovementLine;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockCountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('inventory/counts/index', [
            'counts' => StockCount::with(['warehouse:id,code,name', 'creator:id,name', 'poster:id,name', 'movement:id,number'])
                ->withCount('lines')
                ->withCount(['lines as discrepancy_lines_count' => fn ($query) => $query->where('difference_quantity', '!=', 0)])
                ->latest('started_at')
                ->limit(100)
                ->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $count = DB::transaction(function () use ($request, $data) {
            abort_if(StockCount::where('warehouse_id', $data['warehouse_id'])->where('status', 'draft')->lockForUpdate()->exists(), 422, __('This warehouse already has an open stock count.'));
            $products = Product::where('track_stock', true)
                ->where('track_serials', false)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'cost_price']);
            abort_if($products->isEmpty(), 422, __('There are no quantity-tracked products to count.'));
            $balances = StockMovementLine::where('warehouse_id', $data['warehouse_id'])
                ->whereIn('product_id', $products->pluck('id'))
                ->selectRaw('product_id, sum(quantity) as quantity')
                ->groupBy('product_id')
                ->pluck('quantity', 'product_id');
            $number = 'COUNT-'.now()->format('Ym').'-'.str_pad((string) (StockCount::lockForUpdate()->count() + 1), 5, '0', STR_PAD_LEFT);
            $count = StockCount::create([
                ...$data,
                'created_by' => $request->user()->id,
                'number' => $number,
                'status' => 'draft',
                'started_at' => now(),
            ]);
            $count->lines()->createMany($products->map(fn (Product $product) => [
                'product_id' => $product->id,
                'system_quantity' => round((float) ($balances[$product->id] ?? 0), 3),
                'unit_cost' => $product->cost_price,
            ])->all());

            return $count;
        });

        return to_route('stock-counts.show', $count)->with('toast', ['type' => 'success', 'message' => __('Stock count started.')]);
    }

    public function show(StockCount $stockCount): Response
    {
        $stockCount->load(['warehouse:id,code,name', 'creator:id,name', 'poster:id,name', 'movement:id,number', 'lines.product:id,sku,name,cost_price']);

        return Inertia::render('inventory/counts/show', ['count' => $stockCount]);
    }

    public function update(Request $request, StockCount $stockCount): RedirectResponse
    {
        abort_unless($stockCount->status === 'draft', 422);
        $data = $request->validate([
            'quantities' => ['required', 'array', 'min:1'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $lines = $stockCount->lines()->get();
        $unknown = collect(array_keys($data['quantities']))->map(fn ($id) => (int) $id)->diff($lines->pluck('id'));
        abort_if($unknown->isNotEmpty(), 422, __('The count contains products outside this session.'));

        DB::transaction(function () use ($stockCount, $lines, $data) {
            StockCount::whereKey($stockCount->id)->lockForUpdate()->firstOrFail();
            foreach ($lines as $line) {
                if (array_key_exists($line->id, $data['quantities'])) {
                    $quantity = $data['quantities'][$line->id];
                    $line->update(['counted_quantity' => $quantity === null ? null : round((float) $quantity, 3)]);
                }
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Counted quantities saved.')]);
    }

    public function post(Request $request, StockCount $stockCount, StockLedger $ledger): RedirectResponse
    {
        DB::transaction(function () use ($request, $stockCount, $ledger) {
            $count = StockCount::lockForUpdate()->with('lines.product')->findOrFail($stockCount->id);
            abort_unless($count->status === 'draft', 422);
            if ($count->lines->contains(fn ($line) => $line->counted_quantity === null)) {
                throw ValidationException::withMessages(['quantities' => __('Count every product before posting the stock count.')]);
            }

            $currentBalances = StockMovementLine::where('warehouse_id', $count->warehouse_id)
                ->whereIn('product_id', $count->lines->pluck('product_id'))
                ->selectRaw('product_id, sum(quantity) as quantity')
                ->groupBy('product_id')
                ->pluck('quantity', 'product_id');
            foreach ($count->lines as $line) {
                $current = round((float) ($currentBalances[$line->product_id] ?? 0), 3);
                if (abs($current - (float) $line->system_quantity) > 0.0005) {
                    throw ValidationException::withMessages(['quantities' => __('Stock changed after this count started. Start a new count to avoid a stale adjustment.')]);
                }
            }

            $adjustments = $count->lines->map(function ($line) {
                $difference = round((float) $line->counted_quantity - (float) $line->system_quantity, 3);
                $line->update(['difference_quantity' => $difference]);

                return abs($difference) > 0.0005
                    ? ['product_id' => $line->product_id, 'quantity' => $difference, 'unit_cost' => $line->unit_cost]
                    : null;
            })->filter()->values();
            $movement = $adjustments->isNotEmpty()
                ? $ledger->post([
                    'type' => 'adjustment',
                    'warehouse_id' => $count->warehouse_id,
                    'reference' => $count->number,
                    'notes' => __('Physical stock count adjustment'),
                    'lines' => $adjustments->all(),
                ], $request->user()->id)
                : null;
            $count->update([
                'status' => 'posted',
                'posted_by' => $request->user()->id,
                'stock_movement_id' => $movement?->id,
                'posted_at' => now(),
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Stock count posted.')]);
    }

    public function destroy(StockCount $stockCount): RedirectResponse
    {
        abort_unless($stockCount->status === 'draft', 422);
        $stockCount->delete();

        return to_route('stock-counts.index')->with('toast', ['type' => 'success', 'message' => __('Open stock count discarded.')]);
    }
}
