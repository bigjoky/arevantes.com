<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\WorkOrder;
use App\Services\Inventory\ProjectProcurement;
use App\Services\Inventory\WorkOrderMaterialConsumer;
use App\Services\Operations\FinancialClearanceGuard;
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

    public function reserve(Request $request, WorkOrder $workOrder, FinancialClearanceGuard $financialClearance): RedirectResponse
    {
        $financialClearance->assertAllows($workOrder->loadMissing('installation')->installation, 'reserve');
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

    public function consume(Request $request, WorkOrder $workOrder, StockReservation $reservation, WorkOrderMaterialConsumer $consumer, FinancialClearanceGuard $financialClearance): RedirectResponse
    {
        $this->ensureAccess($request, $workOrder);
        $financialClearance->assertAllows($workOrder->loadMissing('installation')->installation, 'reserve');
        abort_unless($reservation->work_order_id === $workOrder->id && $reservation->status === 'active', 422);
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'serials' => ['nullable', 'string'],
        ]);

        $consumer->consume($workOrder, $reservation, (float) $data['quantity'], $data['serials'] ?? null, $request->user()->id);

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
