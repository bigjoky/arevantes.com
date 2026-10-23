<?php

namespace App\Services\Inventory;

use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderMaterialConsumer
{
    public function __construct(private StockLedger $ledger) {}

    public function consume(WorkOrder $order, StockReservation $reservation, float $quantity, ?string $serials, int $userId, ?string $operationKey = null): StockReservation
    {
        abort_unless($reservation->work_order_id === $order->id, 422);

        return DB::transaction(function () use ($order, $reservation, $quantity, $serials, $userId, $operationKey): StockReservation {
            Warehouse::whereKey($reservation->warehouse_id)->lockForUpdate()->firstOrFail();
            $locked = StockReservation::query()->with('product:id,cost_price')->lockForUpdate()->findOrFail($reservation->id);
            if ($operationKey && ($existing = StockMovement::where('operation_key', $operationKey)->first())) {
                abort_unless($existing->created_by === $userId && $existing->work_order_id === $order->id && $existing->reference === $order->number.' / '.$reservation->id && (float) $existing->lines()->sum('quantity') === -$quantity, 409);
                $originalSerials = SerialNumber::whereHas('events', fn ($q) => $q->where('stock_movement_id', $existing->id))->pluck('serial_number')->sort()->values()->all();
                $requestedSerials = collect(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $serials ?? ''))))->sort()->values()->all();
                abort_unless($originalSerials === $requestedSerials, 409);

                return $locked;
            }
            abort_unless($locked->status === 'active', 422);
            $remaining = round((float) $locked->quantity - (float) $locked->consumed_quantity, 3);
            if ($quantity > $remaining) {
                throw ValidationException::withMessages(['quantity' => __('The consumed quantity exceeds the remaining reservation.')]);
            }

            $this->ledger->post([
                'type' => 'issue',
                'operation_key' => $operationKey,
                'warehouse_id' => $locked->warehouse_id,
                'installation_id' => $order->installation_id,
                'work_order_id' => $order->id,
                'reference' => $operationKey ? $order->number.' / '.$reservation->id : $order->number,
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
