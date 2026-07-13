<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\WorkOrder;
use App\Services\Inventory\ProjectProcurement;
use App\Services\Inventory\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkOrderMaterialsController extends Controller
{
    public function procure(Request $request, WorkOrder $workOrder, ProjectProcurement $procurement): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)],
        ]);
        $document = $procurement->createRfq($workOrder, (int) $data['supplier_id'], $request->user()->id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Purchase request :number created.', ['number' => $document->number]),
        ]);
    }

    public function reserve(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('track_stock', true)->where('is_active', true))],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reserved_until' => ['nullable', 'date', 'after:now'],
        ]);

        DB::transaction(function () use ($data, $workOrder): void {
            $product = Product::findOrFail($data['product_id']);
            if ($product->track_serials && floor((float) $data['quantity']) !== (float) $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => __('Serialized products must be reserved in whole units.')]);
            }
            $onHand = (float) StockMovementLine::where('warehouse_id', $data['warehouse_id'])->where('product_id', $data['product_id'])->sum('quantity');
            $reserved = (float) StockReservation::where('warehouse_id', $data['warehouse_id'])->where('product_id', $data['product_id'])->where('status', 'active')->selectRaw('coalesce(sum(quantity - consumed_quantity), 0) total')->value('total');
            if ($onHand - $reserved < (float) $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => __('The requested quantity is not available.')]);
            }

            StockReservation::create([
                ...$data,
                'installation_id' => $workOrder->installation_id,
                'work_order_id' => $workOrder->id,
                'consumed_quantity' => 0,
                'status' => 'active',
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Materials reserved for the work order.')]);
    }

    public function consume(Request $request, WorkOrder $workOrder, StockReservation $reservation, StockLedger $ledger): RedirectResponse
    {
        $this->ensureAccess($request, $workOrder);
        abort_unless($reservation->work_order_id === $workOrder->id && $reservation->status === 'active', 422);
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'serials' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $workOrder, $reservation, $ledger, $data): void {
            $reservation = StockReservation::lockForUpdate()->findOrFail($reservation->id);
            $reservation->load('product:id,cost_price');
            $remaining = round((float) $reservation->quantity - (float) $reservation->consumed_quantity, 3);
            if ((float) $data['quantity'] > $remaining) {
                throw ValidationException::withMessages(['quantity' => __('The consumed quantity exceeds the remaining reservation.')]);
            }

            $ledger->post([
                'type' => 'issue',
                'warehouse_id' => $reservation->warehouse_id,
                'installation_id' => $workOrder->installation_id,
                'work_order_id' => $workOrder->id,
                'reference' => $workOrder->number,
                'notes' => __('Material consumed on work order :number.', ['number' => $workOrder->number]),
                'lines' => [[
                    'product_id' => $reservation->product_id,
                    'quantity' => $data['quantity'],
                    'unit_cost' => $reservation->product->cost_price,
                    'serials' => $data['serials'] ?? null,
                ]],
            ], $request->user()->id);

            $consumed = round((float) $reservation->consumed_quantity + (float) $data['quantity'], 3);
            $reservation->update([
                'consumed_quantity' => $consumed,
                'status' => $consumed >= (float) $reservation->quantity ? 'consumed' : 'active',
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Material consumption recorded.')]);
    }

    public function release(WorkOrder $workOrder, StockReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->work_order_id === $workOrder->id && $reservation->status === 'active', 422);
        $reservation->update(['status' => 'released']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Material reservation released.')]);
    }

    private function ensureAccess(Request $request, WorkOrder $workOrder): void
    {
        if ($request->user()->hasRole('technician')) {
            abort_unless($workOrder->assigned_user_id === $request->user()->id, 403);
        }
    }
}
