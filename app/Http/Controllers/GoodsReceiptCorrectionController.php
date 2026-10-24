<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\PurchaseDocument;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\Warehouse;
use App\Services\Documents\PurchaseWorkflow;
use App\Services\Inventory\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GoodsReceiptCorrectionController extends Controller
{
    public function update(Request $request, PurchaseDocument $purchaseDocument, GoodsReceipt $receipt, StockLedger $ledger)
    {
        abort_unless($receipt->purchase_document_id === $purchaseDocument->id, 404);
        $data = $request->validate(['warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)->where('is_usable', true)], 'reason' => ['required', 'string', 'min:5', 'max:2000'], 'operation_key' => ['required', 'uuid'], 'expected_warehouse_id' => ['required', 'integer']]);
        DB::transaction(function () use ($receipt, $purchaseDocument, $data, $request, $ledger) {
            PurchaseDocument::lockForUpdate()->findOrFail($purchaseDocument->id);
            $locked = GoodsReceipt::lockForUpdate()->findOrFail($receipt->id);
            if ((int) $locked->warehouse_id === (int) $data['warehouse_id']) {
                return;
            }
            if ((int) $locked->warehouse_id !== (int) $data['expected_warehouse_id']) {
                throw ValidationException::withMessages(['warehouse_id' => 'La recepción ha cambiado. Recarga antes de corregirla.']);
            }
            $source = $locked->warehouse_id;
            if (AuditLog::where('auditable_type', $locked->getMorphClass())->where('auditable_id', $locked->id)->where('event', 'receipt_warehouse_corrected')->exists()) {
                throw ValidationException::withMessages(['warehouse_id' => 'Esta recepción ya fue corregida. Utiliza un traslado de inventario para movimientos posteriores.']);
            }
            StockMovement::whereKey($locked->stock_movement_id)->lockForUpdate()->firstOrFail();
            Warehouse::whereIn('id', [$source, (int) $data['warehouse_id']])->orderBy('id')->lockForUpdate()->get();
            $lines = $locked->movement->lines()->where('quantity', '>', 0)->with('product')->get();
            $inputs = [];
            foreach ($lines as $line) {
                if (StockMovementLine::where('warehouse_id', $source)->where('product_id', $line->product_id)->where('quantity', '<', 0)->where('id', '>', $line->id)->exists()) {
                    throw ValidationException::withMessages(['warehouse_id' => 'Hay salidas o traslados posteriores de este material. Revisa su trazabilidad antes de corregir el almacén.']);
                }
                $serials = SerialNumber::where('received_line_id', $line->id)->orderBy('id')->lockForUpdate()->get();
                if ($line->product->track_serials && ($serials->count() !== (int) $line->quantity || $serials->contains(fn ($s) => $s->warehouse_id !== $source || $s->status !== 'available'))) {
                    throw ValidationException::withMessages(['warehouse_id' => 'Las series de la recepción ya no están disponibles íntegramente en el almacén de origen.']);
                }
                $inputs[] = ['product_id' => $line->product_id, 'quantity' => $line->quantity, 'unit_cost' => $line->unit_cost, 'serials' => $serials->pluck('serial_number')->join(',')];
            }
            abort_if($inputs === [], 422, 'La recepción no tiene material trasladable.');
            $movement = $ledger->post(['type' => 'transfer', 'operation_key' => $data['operation_key'], 'source_movement_id' => $locked->stock_movement_id, 'source_warehouse_id' => $source, 'destination_warehouse_id' => (int) $data['warehouse_id'], 'purchase_document_id' => $purchaseDocument->id, 'reference' => 'Corrección '.$locked->number, 'notes' => $data['reason'], 'lines' => $inputs], $request->user()->id);
            $locked->update(['warehouse_id' => $data['warehouse_id']]);
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'receipt_warehouse_corrected', 'auditable_type' => $locked->getMorphClass(), 'auditable_id' => $locked->id, 'old_values' => ['warehouse_id' => $source], 'new_values' => ['warehouse_id' => (int) $data['warehouse_id'], 'correction_movement_id' => $movement->id, 'reason' => $data['reason']]]);
            app(PurchaseWorkflow::class)->record($purchaseDocument, 'receipt_warehouse_corrected', $request->user()->id, $purchaseDocument->status, $purchaseDocument->status, ['receipt_id' => $locked->id, 'movement_id' => $movement->id, 'reason' => $data['reason']]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Almacén corregido mediante un traslado trazable; cantidades recibidas conservadas.']);
    }
}
