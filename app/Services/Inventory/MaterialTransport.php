<?php

namespace App\Services\Inventory;

use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A physical delivery stays in stock at the real destination warehouse. */
class MaterialTransport
{
    public function __construct(private StockLedger $ledger) {}

    public function post(array $data, int $userId): StockMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $warehouses = Warehouse::whereIn('id', [$data['source_warehouse_id'], $data['destination_warehouse_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $existing = StockMovement::where('operation_key', $data['operation_key'])->first();
            if ($existing) {
                abort_unless($existing->created_by === $userId && $existing->type === $data['type'] && $existing->installation_id === (int) $data['installation_id'] && $existing->operation_hash === SupplyOperationFingerprint::for([...$data, 'lines' => [['product_id' => $data['product_id'], 'quantity' => $data['quantity'], 'serials' => $data['serials'] ?? null]]]), 409);

                return $existing;
            }
            $usable = $data['material_condition'] === 'usable';
            abort_unless((bool) $warehouses[$data['destination_warehouse_id']]->is_usable === $usable, 422, __('Select a destination warehouse matching the material condition.'));
            if ($data['type'] === 'internal_return') {
                $origin = StockMovement::whereKey($data['source_movement_id'])->lockForUpdate()->with('lines')->firstOrFail();
                abort_unless($origin->type === 'delivery' && $origin->installation_id === (int) $data['installation_id'], 422);
                $serials = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $data['serials'] ?? ''))));
                if ($serials && SerialNumber::where('product_id', $data['product_id'])->whereIn('serial_number', $serials)->whereHas('events', fn ($q) => $q->where('stock_movement_id', $origin->id)->where('to_warehouse_id', $data['source_warehouse_id']))->count() !== count($serials)) {
                    throw ValidationException::withMessages(['serials' => __('The serial numbers do not belong to the original delivery.')]);
                }
                $original = $origin->lines->where('quantity', '>', 0)->where('warehouse_id', (int) $data['source_warehouse_id'])->where('product_id', (int) $data['product_id'])->sum('quantity');
                $already = DB::table('stock_movement_lines')->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')->where('stock_movements.source_movement_id', $origin->id)->where('stock_movement_lines.quantity', '>', 0)->where('product_id', $data['product_id'])->sum('quantity');
                if ((float) $data['quantity'] > $original - $already + 0.0005) {
                    throw ValidationException::withMessages(['quantity' => __('The returned quantity exceeds the remaining delivery.')]);
                }
            }
            // Move this installation's commitments with the material; never consume them.
            $reservations = StockReservation::where('installation_id', $data['installation_id'])->where('warehouse_id', $data['source_warehouse_id'])->where('product_id', $data['product_id'])->where('status', 'active')->orderBy('id')->lockForUpdate()->get();
            $remaining = (float) $data['quantity'];
            $moved = [];
            foreach ($reservations as $reservation) {
                $quantity = min($remaining, (float) $reservation->quantity - (float) $reservation->consumed_quantity);
                if ($quantity <= 0) {
                    continue;
                }
                $reservation->update(['quantity' => (float) $reservation->quantity - $quantity, 'status' => (float) $reservation->quantity - $quantity <= (float) $reservation->consumed_quantity ? 'released' : 'active']);
                if ($usable) {
                    $moved[] = [...$reservation->only(['planning_scenario_request_id', 'sales_document_id', 'reserved_until']), 'installation_id' => $reservation->installation_id, 'work_order_id' => $reservation->work_order_id, 'product_id' => $reservation->product_id, 'warehouse_id' => $data['destination_warehouse_id'], 'quantity' => $quantity, 'status' => 'active'];
                }
                $remaining -= $quantity;
            }
            $movement = $this->ledger->post([
                ...$data, 'lines' => [['product_id' => $data['product_id'], 'quantity' => $data['quantity'], 'serials' => $data['serials'] ?? null]],
            ], $userId);
            foreach ($moved as $reservation) {
                StockReservation::create($reservation);
            }

            return $movement;
        });
    }
}
