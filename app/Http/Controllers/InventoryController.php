<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Product;
use App\Models\SerialNumber;
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
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(StockPositionService $positions): Response
    {
        return Inertia::render('inventory/index', [
            'warehouses' => Warehouse::orderByDesc('is_default')->orderBy('name')->get(),
            'products' => Product::where('track_stock', true)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'cost_price', 'track_serials']),
            'positions' => $positions->positions(),
            'movements' => StockMovement::with(['workOrder:id,number', 'lines.product:id,name,sku', 'lines.warehouse:id,code,name'])->latest('posted_at')->limit(100)->get(),
            'serials' => SerialNumber::with(['product:id,name,sku'])->where('status', 'available')->orderBy('serial_number')->get(),
            'reservations' => StockReservation::with(['workOrder:id,number', 'salesDocument:id,number', 'installation:id,project_reference,site_name', 'warehouse:id,code,name', 'product:id,sku,name'])->where('status', 'active')->latest()->get(),
            'installations' => Installation::whereNotIn('status', ['completed', 'cancelled'])->orderBy('project_reference')->get(['id', 'project_reference', 'site_name']),
        ]);
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', 'unique:warehouses'], 'name' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:255'], 'postal_code' => ['nullable', 'string', 'max:20'], 'city' => ['nullable', 'string', 'max:255'], 'is_default' => ['boolean'], 'is_active' => ['boolean']]);
        DB::transaction(function () use ($data) {
            if ($data['is_default'] ?? false) {
                Warehouse::query()->update(['is_default' => false]);
            } Warehouse::create($data);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Warehouse created.')]);
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', Rule::unique('warehouses')->ignore($warehouse)], 'name' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:255'], 'postal_code' => ['nullable', 'string', 'max:20'], 'city' => ['nullable', 'string', 'max:255'], 'is_default' => ['boolean'], 'is_active' => ['boolean']]);
        DB::transaction(function () use ($warehouse, $data) {
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
        $ledger->post($data, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Stock movement posted.')]);
    }

    public function storeReservation(Request $request, FinancialClearanceGuard $financialClearance): RedirectResponse
    {
        $data = $request->validate(['installation_id' => ['required', 'exists:installations,id'], 'warehouse_id' => ['required', 'exists:warehouses,id'], 'product_id' => ['required', 'exists:products,id'], 'quantity' => ['required', 'numeric', 'gt:0'], 'reserved_until' => ['nullable', 'date', 'after:now']]);
        $financialClearance->assertAllows(Installation::findOrFail($data['installation_id']), 'reserve');
        $onHand = (float) StockMovementLine::where('warehouse_id', $data['warehouse_id'])->where('product_id', $data['product_id'])->sum('quantity');
        $reserved = (float) StockReservation::where('warehouse_id', $data['warehouse_id'])->where('product_id', $data['product_id'])->where('status', 'active')->selectRaw('coalesce(sum(quantity - consumed_quantity), 0) total')->value('total');
        if ($onHand - $reserved < (float) $data['quantity']) {
            return back()->withErrors(['quantity' => __('The requested quantity is not available.')]);
        }
        StockReservation::create([...$data, 'status' => 'active']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Stock reserved.')]);
    }

    public function releaseReservation(StockReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->status === 'active', 422);
        $reservation->update(['status' => 'released']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Reservation released.')]);
    }
}
