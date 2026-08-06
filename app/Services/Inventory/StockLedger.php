<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLedger
{
    public function post(array $data, int $userId): StockMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $number = 'MOV-'.now()->format('Ymd').'-'.str_pad((string) (StockMovement::lockForUpdate()->count() + 1), 5, '0', STR_PAD_LEFT);
            $movement = StockMovement::create(['created_by' => $userId, 'sales_document_id' => $data['sales_document_id'] ?? null, 'purchase_document_id' => $data['purchase_document_id'] ?? null, 'installation_id' => $data['installation_id'] ?? null, 'work_order_id' => $data['work_order_id'] ?? null, 'number' => $number, 'type' => $data['type'], 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null, 'posted_at' => now()]);
            foreach ($data['lines'] as $input) {
                $product = Product::findOrFail($input['product_id']);
                if (! $product->track_stock) {
                    throw ValidationException::withMessages(['lines' => __(':product does not track stock.', ['product' => $product->name])]);
                }
                $quantity = abs((float) $input['quantity']);
                $serials = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $input['serials'] ?? ''))));
                if ($product->track_serials && floor($quantity) !== $quantity) {
                    throw ValidationException::withMessages(['lines' => __('Serialized products must use whole quantities.')]);
                }
                if ($product->track_serials && count($serials) !== (int) $quantity) {
                    throw ValidationException::withMessages(['lines' => __('Provide one serial number per unit for :product.', ['product' => $product->name])]);
                }

                if ($data['type'] === 'transfer') {
                    $this->line($movement, $data['source_warehouse_id'], $product->id, -$quantity, $input['unit_cost'] ?? null);
                    $this->assertAvailable($data['source_warehouse_id'], $product->id);
                    $this->line($movement, $data['destination_warehouse_id'], $product->id, $quantity, $input['unit_cost'] ?? null);
                    $this->transferSerials($movement, $product->id, $data['source_warehouse_id'], $data['destination_warehouse_id'], $serials);
                } else {
                    $signed = $data['type'] === 'receipt' ? $quantity : ($data['type'] === 'issue' ? -$quantity : (float) $input['quantity']);
                    $line = $this->line($movement, $data['warehouse_id'], $product->id, $signed, $input['unit_cost'] ?? null);
                    if ($signed < 0) {
                        $this->assertAvailable($data['warehouse_id'], $product->id, $data['installation_id'] ?? null, $data['reservation_sales_document_id'] ?? null);
                        if ($data['type'] === 'purchase_return') {
                            $this->returnSerials($movement, $product->id, $data['warehouse_id'], $serials, $line);
                        } elseif ($data['type'] === 'adjustment' && $product->track_serials) {
                            $this->markMissingSerials($movement, $product->id, $data['warehouse_id'], $serials, $line);
                        } else {
                            $this->issueSerials($movement, $product->id, $data['warehouse_id'], $serials, $line, $data['installation_id'] ?? null);
                        }
                    }
                    if ($signed > 0 && $product->track_serials) {
                        $this->receiveSerials($movement, $product->id, $data['warehouse_id'], $serials, $line);
                    }
                }
            }

            return $movement;
        });
    }

    private function line(StockMovement $movement, int $warehouseId, int $productId, float $quantity, mixed $cost): StockMovementLine
    {
        return $movement->lines()->create(['warehouse_id' => $warehouseId, 'product_id' => $productId, 'quantity' => $quantity, 'unit_cost' => $cost]);
    }

    private function assertAvailable(int $warehouseId, int $productId, ?int $installationId = null, ?int $salesDocumentId = null): void
    {
        $onHand = (float) StockMovementLine::where('warehouse_id', $warehouseId)->where('product_id', $productId)->sum('quantity');
        $reserved = StockReservation::where('warehouse_id', $warehouseId)->where('product_id', $productId)->where('status', 'active')->get()
            ->reject(fn ($reservation) => ($salesDocumentId && $reservation->sales_document_id === $salesDocumentId)
                || (! $reservation->sales_document_id && $installationId && $reservation->installation_id === $installationId))
            ->sum(fn ($reservation) => (float) $reservation->quantity - (float) $reservation->consumed_quantity);
        if ($onHand - $reserved < 0) {
            throw ValidationException::withMessages(['lines' => __('Insufficient available stock for this movement.')]);
        }
    }

    private function receiveSerials(StockMovement $movement, int $productId, int $warehouseId, array $serials, StockMovementLine $line): void
    {
        foreach ($serials as $serial) {
            $existing = SerialNumber::where('serial_number', $serial)->first();
            if ($existing) {
                if ($existing->product_id !== $productId || $existing->status !== 'missing') {
                    throw ValidationException::withMessages(['lines' => __('Serial number :serial is already registered and cannot be received.', ['serial' => $serial])]);
                }
                $existing->update(['warehouse_id' => $warehouseId, 'installation_id' => null, 'received_line_id' => $line->id, 'issued_line_id' => null, 'status' => 'available', 'received_at' => now(), 'issued_at' => null]);
                $this->recordSerialEvent($existing, $movement, 'recovered', null, $warehouseId);

                continue;
            }
            $item = SerialNumber::create(['product_id' => $productId, 'warehouse_id' => $warehouseId, 'received_line_id' => $line->id, 'serial_number' => $serial, 'status' => 'available', 'received_at' => now()]);
            $this->recordSerialEvent($item, $movement, 'received', null, $warehouseId);
        }
    }

    private function markMissingSerials(StockMovement $movement, int $productId, int $warehouseId, array $serials, StockMovementLine $line): void
    {
        foreach ($serials as $serial) {
            $item = SerialNumber::where('product_id', $productId)->where('warehouse_id', $warehouseId)->where('serial_number', $serial)->where('status', 'available')->first();
            if (! $item) {
                throw ValidationException::withMessages(['lines' => __('Serial number :serial is not available for the stock-count adjustment.', ['serial' => $serial])]);
            }
            $item->update(['warehouse_id' => null, 'issued_line_id' => $line->id, 'status' => 'missing', 'issued_at' => now()]);
            $this->recordSerialEvent($item, $movement, 'missing', $warehouseId);
        }
    }

    private function issueSerials(StockMovement $movement, int $productId, int $warehouseId, array $serials, StockMovementLine $line, ?int $installationId): void
    {
        foreach ($serials as $serial) {
            $item = SerialNumber::where('product_id', $productId)->where('warehouse_id', $warehouseId)->where('serial_number', $serial)->where('status', 'available')->first();
            if (! $item) {
                throw ValidationException::withMessages(['lines' => __('Serial number :serial is not available in the selected warehouse.', ['serial' => $serial])]);
            } $item->update(['warehouse_id' => null, 'installation_id' => $installationId, 'issued_line_id' => $line->id, 'status' => $installationId ? 'installed' : 'issued', 'issued_at' => now()]);
            $this->recordSerialEvent($item, $movement, $installationId ? 'installed' : 'issued', $warehouseId, null, $installationId);
        }
    }

    private function returnSerials(StockMovement $movement, int $productId, int $warehouseId, array $serials, StockMovementLine $line): void
    {
        foreach ($serials as $serial) {
            $item = SerialNumber::where('product_id', $productId)->where('warehouse_id', $warehouseId)->where('serial_number', $serial)->where('status', 'available')->first();
            if (! $item) {
                throw ValidationException::withMessages(['lines' => __('Serial number :serial is not available for return.', ['serial' => $serial])]);
            }
            $item->update(['warehouse_id' => null, 'issued_line_id' => $line->id, 'status' => 'returned', 'issued_at' => now()]);
            $this->recordSerialEvent($item, $movement, 'returned', $warehouseId);
        }
    }

    private function transferSerials(StockMovement $movement, int $productId, int $source, int $destination, array $serials): void
    {
        foreach ($serials as $serial) {
            $item = SerialNumber::where('product_id', $productId)->where('warehouse_id', $source)->where('serial_number', $serial)->where('status', 'available')->firstOrFail();
            $item->update(['warehouse_id' => $destination]);
            $this->recordSerialEvent($item, $movement, 'transferred', $source, $destination);
        }
    }

    private function recordSerialEvent(SerialNumber $serial, StockMovement $movement, string $type, ?int $fromWarehouseId = null, ?int $toWarehouseId = null, ?int $installationId = null): void
    {
        $serial->events()->create([
            'stock_movement_id' => $movement->id,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $toWarehouseId,
            'installation_id' => $installationId,
            'event_type' => $type,
            'occurred_at' => $movement->posted_at,
        ]);
    }
}
