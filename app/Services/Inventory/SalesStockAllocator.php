<?php

namespace App\Services\Inventory;

use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Services\Documents\SalesDocumentConversion;
use App\Services\Operations\FinancialClearanceGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesStockAllocator
{
    public function __construct(
        private SalesDocumentConversion $conversion,
        private FinancialClearanceGuard $financialClearance,
    ) {}

    /** @return array{lines: array<int, array<string, mixed>>, required: float, reserved: float, available: float, inbound: float, physical_shortage: float, shortage: float} */
    public function status(SalesDocument $order, ?array $summary = null): array
    {
        $summary ??= $this->conversion->summary($order);
        $requirements = $this->requirements($order, $summary);
        $own = $this->activeReservations($order)->groupBy('product_id')->map->sum('remaining');
        $allReserved = StockReservation::query()->where('status', 'active')->get()
            ->groupBy('product_id')->map(fn ($rows) => $rows->sum(fn ($row) => (float) $row->quantity - (float) $row->consumed_quantity));
        $onHand = StockMovementLine::query()->selectRaw('product_id, sum(quantity) as quantity')->groupBy('product_id')->pluck('quantity', 'product_id');
        $inbound = $this->inboundQuantities($order);

        $lines = $requirements->map(function (float $required, int $productId) use ($order, $own, $allReserved, $onHand, $inbound): array {
            $reserved = min($required, (float) ($own[$productId] ?? 0));
            $free = max(0, (float) ($onHand[$productId] ?? 0) - (float) ($allReserved[$productId] ?? 0));
            $incoming = max(0, (float) ($inbound[$productId] ?? 0));
            $physicalShortage = max(0, $required - $reserved - $free);
            $product = $order->lines->firstWhere('product_id', $productId)?->product;

            return [
                'product_id' => $productId,
                'name' => $product?->name,
                'sku' => $product?->sku,
                'required' => round($required, 3),
                'reserved' => round($reserved, 3),
                'available' => round($free, 3),
                'inbound' => round($incoming, 3),
                'physical_shortage' => round($physicalShortage, 3),
                'shortage' => round(max(0, $physicalShortage - $incoming), 3),
            ];
        })->values();

        return [
            'lines' => $lines->all(),
            'required' => round($lines->sum('required'), 3),
            'reserved' => round($lines->sum('reserved'), 3),
            'available' => round($lines->sum('available'), 3),
            'inbound' => round($lines->sum('inbound'), 3),
            'physical_shortage' => round($lines->sum('physical_shortage'), 3),
            'shortage' => round($lines->sum('shortage'), 3),
        ];
    }

    public function reserve(SalesDocument $order, Warehouse $warehouse, ?string $reservedUntil = null): int
    {
        return DB::transaction(function () use ($order, $warehouse, $reservedUntil): int {
            $order = SalesDocument::lockForUpdate()->with(['lines.product', 'installation'])->findOrFail($order->id);
            abort_unless($order->type === 'order', 422, __('Only sales orders can reserve stock.'));
            if ($order->installation) {
                $this->financialClearance->assertAllows($order->installation, 'reserve');
            }
            Warehouse::lockForUpdate()->findOrFail($warehouse->id);

            $requirements = $this->requirements($order, $this->conversion->summary($order));
            $own = $this->activeReservations($order)->groupBy('product_id')->map->sum('remaining');
            $created = 0;

            foreach ($requirements as $productId => $required) {
                $quantity = round(max(0, $required - (float) ($own[$productId] ?? 0)), 3);
                if ($quantity === 0.0) {
                    continue;
                }

                $onHand = (float) StockMovementLine::where('warehouse_id', $warehouse->id)->where('product_id', $productId)->sum('quantity');
                $reserved = (float) StockReservation::where('warehouse_id', $warehouse->id)->where('product_id', $productId)->where('status', 'active')->selectRaw('coalesce(sum(quantity - consumed_quantity), 0) total')->value('total');
                if ($onHand - $reserved < $quantity) {
                    $product = $order->lines->firstWhere('product_id', $productId)?->product;
                    throw ValidationException::withMessages(['warehouse_id' => __('There is not enough free stock of :product in this warehouse.', ['product' => $product?->name])]);
                }

                StockReservation::create([
                    'installation_id' => $order->installation_id,
                    'sales_document_id' => $order->id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'status' => 'active',
                    'reserved_until' => $reservedUntil,
                ]);
                $created++;
            }

            if ($created === 0) {
                throw ValidationException::withMessages(['warehouse_id' => __('All pending stock is already reserved or delivered.')]);
            }

            return $created;
        });
    }

    /** @param array<int, array{product_id: int, quantity: float}> $stockLines */
    public function consumeForDelivery(SalesDocument $delivery, int $warehouseId, array $stockLines): void
    {
        $orderId = $delivery->sourceDocument?->type === 'order' ? $delivery->source_document_id : null;
        if (! $orderId && ! $delivery->installation_id) {
            return;
        }

        foreach ($stockLines as $stockLine) {
            $remaining = (float) $stockLine['quantity'];
            $reservations = StockReservation::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $stockLine['product_id'])
                ->where('status', 'active')
                ->where(function ($query) use ($delivery, $orderId): void {
                    if ($orderId) {
                        $query->where('sales_document_id', $orderId);
                    }
                    if ($delivery->installation_id) {
                        $method = $orderId ? 'orWhere' : 'where';
                        $query->{$method}(fn ($query) => $query->whereNull('sales_document_id')->where('installation_id', $delivery->installation_id));
                    }
                })
                ->orderByDesc('sales_document_id')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $available = (float) $reservation->quantity - (float) $reservation->consumed_quantity;
                $consumed = min($available, $remaining);
                if ($consumed <= 0) {
                    continue;
                }
                $newConsumed = round((float) $reservation->consumed_quantity + $consumed, 3);
                $reservation->update([
                    'consumed_quantity' => $newConsumed,
                    'status' => $newConsumed >= (float) $reservation->quantity ? 'fulfilled' : 'active',
                ]);
                $remaining = round($remaining - $consumed, 3);
                if ($remaining <= 0) {
                    break;
                }
            }
        }
    }

    private function requirements(SalesDocument $order, ?array $summary): Collection
    {
        $remainingByLine = collect($summary['lines'] ?? [])->mapWithKeys(fn ($line) => [$line['id'] => max(0, (float) $line['quantity'] - (float) $line['delivered_quantity'])]);

        return $order->lines
            ->filter(fn ($line) => $line->product?->track_stock)
            ->groupBy('product_id')
            ->map(fn ($lines) => round($lines->sum(fn ($line) => (float) ($remainingByLine[$line->id] ?? 0)), 3))
            ->filter(fn ($quantity) => $quantity > 0);
    }

    private function activeReservations(SalesDocument $order): Collection
    {
        return StockReservation::query()->where('sales_document_id', $order->id)->where('status', 'active')->get()
            ->map(function ($reservation) {
                $reservation->remaining = max(0, (float) $reservation->quantity - (float) $reservation->consumed_quantity);

                return $reservation;
            });
    }

    private function inboundQuantities(SalesDocument $order): Collection
    {
        $documents = PurchaseDocument::with('lines')->where('sales_document_id', $order->id)
            ->where(function ($query): void {
                $query->where(fn ($query) => $query->where('type', 'purchase_rfq')->whereIn('status', ['draft', 'sent', 'quoted']))
                    ->orWhere(fn ($query) => $query->where('type', 'purchase_order')->whereIn('status', ['ordered', 'partially_received']));
            })->get();
        $inbound = collect();

        $documents->where('type', 'purchase_rfq')->groupBy(fn ($document) => $document->procurement_batch_id ?: 'document-'.$document->id)
            ->each(function ($batch) use ($inbound): void {
                $batch->first()->lines->groupBy('product_id')->each(function ($lines, $productId) use ($inbound): void {
                    $inbound[$productId] = (float) ($inbound[$productId] ?? 0) + $lines->sum(fn ($line) => (float) $line->quantity);
                });
            });
        $documents->where('type', 'purchase_order')->each(function ($document) use ($inbound): void {
            $document->lines->groupBy('product_id')->each(function ($lines, $productId) use ($inbound): void {
                $inbound[$productId] = (float) ($inbound[$productId] ?? 0) + $lines->sum(fn ($line) => max(0, (float) $line->quantity - (float) $line->received_quantity));
            });
        });

        return $inbound;
    }
}
