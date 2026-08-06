<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\StockCount;
use App\Models\StockMovementLine;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
                ->withCount(['lines as discrepancy_lines_count' => fn ($query) => $query->where('discrepancy_count', '>', 0)])
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
            'mode' => ['required', Rule::in(['quantity', 'serial'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $count = DB::transaction(function () use ($request, $data) {
            abort_if(StockCount::where('warehouse_id', $data['warehouse_id'])->where('status', 'draft')->lockForUpdate()->exists(), 422, __('This warehouse already has an open stock count.'));
            $products = Product::where('track_stock', true)
                ->where('track_serials', $data['mode'] === 'serial')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'cost_price']);
            abort_if($products->isEmpty(), 422, $data['mode'] === 'serial' ? __('There are no serialized products to audit.') : __('There are no quantity-tracked products to count.'));
            $balances = StockMovementLine::where('warehouse_id', $data['warehouse_id'])
                ->whereIn('product_id', $products->pluck('id'))
                ->selectRaw('product_id, sum(quantity) as quantity')
                ->groupBy('product_id')
                ->pluck('quantity', 'product_id');
            $serials = $data['mode'] === 'serial'
                ? SerialNumber::where('warehouse_id', $data['warehouse_id'])
                    ->where('status', 'available')
                    ->whereIn('product_id', $products->pluck('id'))
                    ->orderBy('serial_number')
                    ->get(['product_id', 'serial_number'])
                    ->groupBy('product_id')
                : collect();
            if ($data['mode'] === 'serial') {
                foreach ($products as $product) {
                    $ledgerQuantity = round((float) ($balances[$product->id] ?? 0), 3);
                    $serialQuantity = $serials->get($product->id, collect())->count();
                    abort_if(abs($ledgerQuantity - $serialQuantity) > 0.0005, 422, __('The stock ledger and available serial registry differ for :product.', ['product' => $product->name]));
                }
            }
            $prefix = $data['mode'] === 'serial' ? 'SCOUNT' : 'COUNT';
            $number = $prefix.'-'.now()->format('Ym').'-'.str_pad((string) (StockCount::lockForUpdate()->count() + 1), 5, '0', STR_PAD_LEFT);
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
                'snapshot_serials' => $data['mode'] === 'serial' ? $serials->get($product->id, collect())->pluck('serial_number')->values()->all() : null,
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
        if ($stockCount->mode === 'serial') {
            return $this->updateSerials($request, $stockCount);
        }
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
            $adjustments = $count->mode === 'serial'
                ? $this->serialAdjustments($count)
                : $this->quantityAdjustments($count);
            $movement = $adjustments->isNotEmpty()
                ? $ledger->post([
                    'type' => 'adjustment',
                    'warehouse_id' => $count->warehouse_id,
                    'reference' => $count->number,
                    'notes' => $count->mode === 'serial' ? __('Serialized stock audit adjustment') : __('Physical stock count adjustment'),
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

    private function updateSerials(Request $request, StockCount $stockCount): RedirectResponse
    {
        $data = $request->validate([
            'serials' => ['required', 'array', 'min:1'],
            'serials.*' => ['nullable', 'string', 'max:100000'],
        ]);
        $lines = $stockCount->lines()->get();
        $unknown = collect(array_keys($data['serials']))->map(fn ($id) => (int) $id)->diff($lines->pluck('id'));
        abort_if($unknown->isNotEmpty(), 422, __('The audit contains products outside this session.'));
        $parsed = collect($data['serials'])->map(fn ($value) => $this->parseSerials($value));
        $allSerials = $parsed->flatten();
        if ($allSerials->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['serials' => __('Each serial number may only appear once in an audit.')]);
        }

        DB::transaction(function () use ($stockCount, $lines, $parsed) {
            StockCount::whereKey($stockCount->id)->lockForUpdate()->firstOrFail();
            foreach ($lines as $line) {
                if ($parsed->has($line->id)) {
                    $serials = $parsed->get($line->id);
                    $line->update(['counted_serials' => $serials, 'counted_quantity' => count($serials)]);
                }
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Scanned serial numbers saved.')]);
    }

    private function quantityAdjustments(StockCount $count): Collection
    {
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

        return $count->lines->map(function ($line) {
            $difference = round((float) $line->counted_quantity - (float) $line->system_quantity, 3);
            $line->update(['difference_quantity' => $difference, 'discrepancy_count' => abs($difference) > 0.0005 ? 1 : 0]);

            return abs($difference) > 0.0005
                ? ['product_id' => $line->product_id, 'quantity' => $difference, 'unit_cost' => $line->unit_cost]
                : null;
        })->filter()->values();
    }

    private function serialAdjustments(StockCount $count): Collection
    {
        if ($count->lines->contains(fn ($line) => $line->counted_serials === null)) {
            throw ValidationException::withMessages(['serials' => __('Scan every serialized product before posting the audit.')]);
        }
        $current = SerialNumber::where('warehouse_id', $count->warehouse_id)
            ->where('status', 'available')
            ->whereIn('product_id', $count->lines->pluck('product_id'))
            ->orderBy('serial_number')
            ->get(['product_id', 'serial_number'])
            ->groupBy('product_id');
        $adjustments = collect();
        foreach ($count->lines as $line) {
            $snapshot = collect($line->snapshot_serials)->sort()->values();
            $currentSerials = $current->get($line->product_id, collect())->pluck('serial_number')->sort()->values();
            if ($snapshot->all() !== $currentSerials->all()) {
                throw ValidationException::withMessages(['serials' => __('Serialized stock changed after this audit started. Start a new audit.')]);
            }
            $counted = collect($line->counted_serials);
            $missing = $snapshot->diff($counted)->values();
            $unexpected = $counted->diff($snapshot)->values();
            foreach ($unexpected as $serial) {
                $existing = SerialNumber::where('serial_number', $serial)->first();
                if ($existing && ($existing->product_id !== $line->product_id || $existing->status !== 'missing')) {
                    throw ValidationException::withMessages(['serials' => __('Serial number :serial is already active elsewhere. Transfer or resolve it before posting.', ['serial' => $serial])]);
                }
            }
            $line->update([
                'difference_quantity' => $counted->count() - $snapshot->count(),
                'discrepancy_count' => $missing->count() + $unexpected->count(),
                'missing_serials' => $missing->all(),
                'unexpected_serials' => $unexpected->all(),
            ]);
            if ($missing->isNotEmpty()) {
                $adjustments->push(['product_id' => $line->product_id, 'quantity' => -$missing->count(), 'unit_cost' => $line->unit_cost, 'serials' => $missing->implode("\n")]);
            }
            if ($unexpected->isNotEmpty()) {
                $adjustments->push(['product_id' => $line->product_id, 'quantity' => $unexpected->count(), 'unit_cost' => $line->unit_cost, 'serials' => $unexpected->implode("\n")]);
            }
        }

        return $adjustments;
    }

    private function parseSerials(?string $value): array
    {
        return collect(preg_split('/[\r\n,;]+/', $value ?? ''))
            ->map(fn ($serial) => trim($serial))
            ->filter()
            ->values()
            ->all();
    }
}
