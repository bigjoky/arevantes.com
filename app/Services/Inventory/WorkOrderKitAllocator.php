<?php

namespace App\Services\Inventory;

use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class WorkOrderKitAllocator
{
    public function reserveAvailable(WorkOrder $order): int
    {
        if (! in_array($order->kind, ['installation', 'repair'], true)) {
            return 0;
        }

        return DB::transaction(function () use ($order): int {
            $warehouses = Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->lockForUpdate()->get(['id']);
            StockReservation::query()->where('installation_id', $order->installation_id)->whereNull('work_order_id')
                ->where('status', 'active')->update(['work_order_id' => $order->id]);
            $requirements = $order->installation->materialRequirements()->whereNotNull('product_id')->get(['product_id', 'planned_quantity']);
            $created = 0;

            foreach ($requirements as $requirement) {
                $consumed = abs((float) StockMovementLine::query()->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
                    ->where('stock_movements.installation_id', $order->installation_id)->where('stock_movements.type', 'issue')
                    ->where('stock_movement_lines.product_id', $requirement->product_id)->where('stock_movement_lines.quantity', '<', 0)
                    ->sum('stock_movement_lines.quantity'));
                $projectReserved = (float) StockReservation::query()->where('installation_id', $order->installation_id)
                    ->where('product_id', $requirement->product_id)->where('status', 'active')->sum(DB::raw('quantity - consumed_quantity'));
                $needed = max(0, (float) $requirement->planned_quantity - $consumed - $projectReserved);

                foreach ($warehouses as $warehouse) {
                    if ($needed <= 0) {
                        break;
                    }
                    $onHand = (float) StockMovementLine::where('warehouse_id', $warehouse->id)->where('product_id', $requirement->product_id)->sum('quantity');
                    $reserved = (float) StockReservation::where('warehouse_id', $warehouse->id)->where('product_id', $requirement->product_id)
                        ->where('status', 'active')->sum(DB::raw('quantity - consumed_quantity'));
                    $quantity = min($needed, max(0, $onHand - $reserved));
                    if ($quantity <= 0) {
                        continue;
                    }
                    StockReservation::create([
                        'installation_id' => $order->installation_id, 'work_order_id' => $order->id,
                        'warehouse_id' => $warehouse->id, 'product_id' => $requirement->product_id,
                        'quantity' => $quantity, 'consumed_quantity' => 0, 'status' => 'active',
                        'reserved_until' => $order->scheduled_start?->copy()->addDay(),
                    ]);
                    $needed -= $quantity;
                    $created++;
                }
            }

            return $created;
        });
    }
}
