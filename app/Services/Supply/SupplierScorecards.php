<?php

namespace App\Services\Supply;

use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierQualityIncident;
use Illuminate\Support\Collection;

class SupplierScorecards
{
    public function all(): Collection
    {
        $suppliers = Supplier::withCount(['products as catalog_items'])->orderBy('legal_name')->get();
        $orders = PurchaseDocument::with(['receipts', 'lines'])
            ->where('type', 'purchase_order')
            ->get()
            ->groupBy('supplier_id');
        $invoices = PurchaseDocument::where('type', 'supplier_invoice')
            ->get()
            ->groupBy('supplier_id');
        $spend = PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])
            ->whereDate('issue_date', '>=', today()->subYear())
            ->get()
            ->groupBy('supplier_id');
        $incidents = SupplierQualityIncident::with('purchaseDocument:id,supplier_id')
            ->get()
            ->groupBy(fn (SupplierQualityIncident $incident) => $incident->purchaseDocument->supplier_id);

        return $suppliers->map(function (Supplier $supplier) use ($orders, $invoices, $spend, $incidents): array {
            $supplierOrders = $orders->get($supplier->id, collect());
            $supplierInvoices = $invoices->get($supplier->id, collect());
            $supplierIncidents = $incidents->get($supplier->id, collect());
            $eligibleDeliveries = $supplierOrders->filter(fn (PurchaseDocument $order) => $order->expected_delivery_date
                && $order->status === 'received'
                && $order->receipts->isNotEmpty());
            $onTime = $eligibleDeliveries->filter(function (PurchaseDocument $order): bool {
                $completedAt = $order->receipts->max('received_at');

                return $completedAt && $completedAt->toDateString() <= $order->expected_delivery_date->toDateString();
            })->count();
            $delays = $eligibleDeliveries->map(function (PurchaseDocument $order): int {
                $completedAt = $order->receipts->max('received_at');
                if (! $completedAt || $completedAt->toDateString() <= $order->expected_delivery_date->toDateString()) {
                    return 0;
                }

                return (int) $order->expected_delivery_date->diffInDays($completedAt);
            });
            $received = (float) $supplierOrders->flatMap->lines->sum('received_quantity');
            $returned = (float) $supplierOrders->flatMap->lines->sum('returned_quantity');
            $rejected = (float) $supplierIncidents->sum('rejected_quantity');
            $returnRate = $received > 0 ? $returned / $received : null;
            $inspected = $received + $rejected;
            $rejectionRate = $inspected > 0 ? $rejected / $inspected : null;
            $defectRate = $inspected > 0 ? ($returned + $rejected) / $inspected : null;
            $matched = $supplierInvoices->whereIn('matching_status', ['matched', 'approved'])->count();

            $deliveryScore = $eligibleDeliveries->isNotEmpty() ? round($onTime / $eligibleDeliveries->count() * 100, 1) : null;
            $qualityScore = $defectRate !== null ? round(max(0, 100 - $defectRate * 100), 1) : null;
            $matchingScore = $supplierInvoices->isNotEmpty() ? round($matched / $supplierInvoices->count() * 100, 1) : null;
            $parts = collect([
                $deliveryScore !== null ? [$deliveryScore, 50] : null,
                $qualityScore !== null ? [$qualityScore, 30] : null,
                $matchingScore !== null ? [$matchingScore, 20] : null,
            ])->filter();
            $overall = $parts->isNotEmpty()
                ? round($parts->sum(fn (array $part) => $part[0] * $part[1]) / $parts->sum(fn (array $part) => $part[1]), 1)
                : null;

            return [
                'id' => $supplier->id,
                'name' => $supplier->trade_name ?: $supplier->legal_name,
                'legal_name' => $supplier->legal_name,
                'is_active' => $supplier->is_active,
                'catalog_items' => $supplier->catalog_items,
                'orders' => $supplierOrders->count(),
                'annual_spend' => round((float) $spend->get($supplier->id, collect())->sum('amount'), 2),
                'delivery_score' => $deliveryScore,
                'quality_score' => $qualityScore,
                'matching_score' => $matchingScore,
                'overall_score' => $overall,
                'on_time_deliveries' => $onTime,
                'measured_deliveries' => $eligibleDeliveries->count(),
                'average_delay_days' => $delays->isNotEmpty() ? round((float) $delays->average(), 1) : null,
                'return_rate' => $returnRate !== null ? round($returnRate * 100, 2) : null,
                'rejection_rate' => $rejectionRate !== null ? round($rejectionRate * 100, 2) : null,
                'open_quality_incidents' => $supplierIncidents->where('status', 'open')->count(),
            ];
        });
    }

    public function for(Supplier $supplier): array
    {
        return $this->all()->firstWhere('id', $supplier->id) ?? [];
    }
}
