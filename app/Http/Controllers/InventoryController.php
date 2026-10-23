<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockPositionService;
use App\Services\Operations\FinancialClearanceGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, StockPositionService $positions): Response
    {
        $planningRequestId = $request->validate([
            'planning_request' => ['nullable', 'integer', Rule::exists('planning_scenario_requests', 'id')->where('area', 'inventory')],
        ])['planning_request'] ?? null;
        $planningRequestId = $planningRequestId !== null ? (int) $planningRequestId : null;
        $reservations = StockReservation::with(['workOrder:id,number', 'salesDocument:id,number', 'installation:id,project_reference,site_name', 'warehouse:id,code,name', 'product:id,sku,name'])
            ->when($planningRequestId, fn ($query, int $id) => $query->where('planning_scenario_request_id', $id), fn ($query) => $query->where('status', 'active'))
            ->latest()
            ->get();

        return Inertia::render('inventory/index', [
            'initialSection' => match ($request->route()->getName()) {
                'inventory.reservations.index' => 'reservations',
                'inventory.movements.index' => 'movements',
                'inventory.warehouses.index' => 'warehouses',
                default => $planningRequestId ? 'reservations' : (in_array($request->input('section'), ['stock', 'movements', 'reservations', 'warehouses']) ? $request->input('section') : 'stock'),
            },
            'warehouses' => Warehouse::orderByDesc('is_default')->orderBy('name')->get(),
            'products' => Product::where('track_stock', true)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'cost_price', 'track_serials'])->each(function ($product) {
                if (! Gate::allows('view-finance')) {
                    $product->cost_price = null;
                }
            }),
            'positions' => $positions->positions(),
            'movements' => StockMovement::with(['workOrder:id,number', 'lines.product:id,name,sku', 'lines.warehouse:id,code,name'])->latest('posted_at')->limit(100)->get()->each(function ($movement) {
                if (! Gate::allows('view-finance')) {
                    $movement->lines->each(fn ($line) => $line->unit_cost = null);
                }
            }),
            'serials' => SerialNumber::with(['product:id,name,sku'])->where('status', 'available')->orderBy('serial_number')->get(),
            'reservations' => $reservations,
            'planningRequestId' => $planningRequestId,
            'installations' => Installation::whereNotIn('status', ['completed', 'cancelled'])->orderBy('project_reference')->get(['id', 'project_reference', 'site_name']),
        ]);
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', 'unique:warehouses'], 'name' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:255'], 'postal_code' => ['nullable', 'string', 'max:20'], 'city' => ['nullable', 'string', 'max:255'], 'is_usable' => ['sometimes', 'boolean'], 'is_default' => ['boolean'], 'is_active' => ['boolean']]);
        DB::transaction(function () use ($data) {
            if ($data['is_default'] ?? false) {
                Warehouse::query()->update(['is_default' => false]);
            } Warehouse::create($data);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Warehouse created.')]);
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', Rule::unique('warehouses')->ignore($warehouse)], 'name' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:255'], 'postal_code' => ['nullable', 'string', 'max:20'], 'city' => ['nullable', 'string', 'max:255'], 'is_usable' => ['sometimes', 'boolean'], 'is_default' => ['boolean'], 'is_active' => ['boolean']]);
        DB::transaction(function () use ($warehouse, $data) {
            $warehouse = Warehouse::whereKey($warehouse->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('is_usable', $data) && (bool) $data['is_usable'] !== (bool) $warehouse->is_usable) {
                if (StockMovementLine::where('warehouse_id', $warehouse->id)->exists() || StockReservation::where('warehouse_id', $warehouse->id)->where('status', 'active')->exists()) {
                    throw ValidationException::withMessages(['is_usable' => __('The stock usability cannot change while the warehouse has movements or reservations.')]);
                }
            }
            if ($data['is_default'] ?? false) {
                Warehouse::whereKeyNot($warehouse->id)->update(['is_default' => false]);
            } $warehouse->update($data);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Warehouse updated.')]);
    }

    public function storeMovement(Request $request, StockLedger $ledger): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['receipt', 'issue', 'transfer', 'adjustment'])], 'warehouse_id' => ['nullable', 'exists:warehouses,id'], 'source_warehouse_id' => ['nullable', 'exists:warehouses,id'], 'destination_warehouse_id' => ['nullable', 'different:source_warehouse_id', 'exists:warehouses,id'], 'reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'exists:products,id'], 'lines.*.quantity' => ['required', 'numeric', 'not_in:0'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'], 'lines.*.serials' => ['nullable', 'string']]);
        $data['type'] === 'transfer' ? $request->validate(['source_warehouse_id' => ['required'], 'destination_warehouse_id' => ['required']]) : $request->validate(['warehouse_id' => ['required']]);
        if (! Gate::allows('view-finance')) {
            foreach ($data['lines'] as &$line) {
                unset($line['unit_cost']);
            }
            unset($line);
        }
        $ledger->post($data, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Stock movement posted.')]);
    }

    public function storeReservation(Request $request, FinancialClearanceGuard $financialClearance): RedirectResponse
    {
        $data = $request->validate(['installation_id' => ['required', 'exists:installations,id'], 'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_usable', true)], 'product_id' => ['required', 'exists:products,id'], 'quantity' => ['required', 'numeric', 'gt:0'], 'reserved_until' => ['nullable', 'date', 'after:now']]);
        $financialClearance->assertAllows(Installation::findOrFail($data['installation_id']), 'reserve');
        DB::transaction(function () use ($data): void {
            abort_unless(Warehouse::whereKey($data['warehouse_id'])->lockForUpdate()->firstOrFail()->is_usable, 422);
            $ledgerQuantity = (float) StockMovementLine::where('warehouse_id', $data['warehouse_id'])->where('product_id', $data['product_id'])->sum('quantity');
            StockBalance::query()->insertOrIgnore([
                'warehouse_id' => $data['warehouse_id'],
                'product_id' => $data['product_id'],
                'quantity' => $ledgerQuantity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $balance = StockBalance::where('warehouse_id', $data['warehouse_id'])
                ->where('product_id', $data['product_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $ledgerQuantity = (float) StockMovementLine::where('warehouse_id', $data['warehouse_id'])->where('product_id', $data['product_id'])->sum('quantity');
            if (abs((float) $balance->quantity - $ledgerQuantity) > 0.0005) {
                $balance->update(['quantity' => $ledgerQuantity]);
            }
            $reserved = (float) StockReservation::where('warehouse_id', $data['warehouse_id'])->where('product_id', $data['product_id'])->where('status', 'active')->selectRaw('coalesce(sum(quantity - consumed_quantity), 0) total')->value('total');
            if ($ledgerQuantity - $reserved < (float) $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => __('The requested quantity is not available.')]);
            }
            StockReservation::create([...$data, 'status' => 'active']);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Stock reserved.')]);
    }

    public function releaseReservation(StockReservation $reservation): RedirectResponse
    {
        DB::transaction(function () use ($reservation) {
            Warehouse::whereKey($reservation->warehouse_id)->lockForUpdate()->firstOrFail();
            $locked = StockReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'active', 422);
            $locked->update(['status' => 'released']);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Reservation released.')]);
    }
}
