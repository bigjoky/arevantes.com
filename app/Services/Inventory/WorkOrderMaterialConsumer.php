<?php

namespace App\Services\Inventory;

use App\Models\StockReservation;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderMaterialConsumer
{
    public function __construct(private StockLedger $ledger) {}

    public function consume(WorkOrder $order, StockReservation $reservation, float $quantity, ?string $serials, int $userId): StockReservation
    {
        abort_unless($reservation->work_order_id === $order->id && $reservation->status === 'active', 422);

        return DB::transaction(function () use ($order, $reservation, $quantity, $serials, $userId): StockReservation {
            $locked = StockReservation::query()->with('product:id,cost_price')->lockForUpdate()->findOrFail($reservation->id);
            $remaining = round((float) $locked->quantity - (float) $locked->consumed_quantity, 3);
            if ($quantity > $remaining) {
                throw ValidationException::withMessages(['quantity' => __('The consumed quantity exceeds the remaining reservation.')]);
            }

            $this->ledger->post([
                'type' => 'issue',
                'warehouse_id' => $locked->warehouse_id,
                'installation_id' => $order->installation_id,
                'work_order_id' => $order->id,
                'reference' => $order->number,
                'notes' => __('Material consumed on work order :number.', ['number' => $order->number]),
                'lines' => [[
                    'product_id' => $locked->product_id,
                    'quantity' => $quantity,
                    'unit_cost' => $locked->product->cost_price,
                    'serials' => $serials,
                ]],
            ], $userId);

            $consumed = round((float) $locked->consumed_quantity + $quantity, 3);
            $locked->update([
                'consumed_quantity' => $consumed,
                'status' => $consumed >= (float) $locked->quantity ? 'consumed' : 'active',
            ]);

            return $locked->fresh(['product:id,sku,name,track_serials', 'warehouse:id,code,name']);
        });
    }
}
