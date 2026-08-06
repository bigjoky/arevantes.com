<?php

namespace App\Services\Supply;

use App\Models\PurchaseDocument;
use App\Models\PurchaseSetting;
use App\Models\SupplierProduct;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SupplyAnalytics
{
    public function dashboard(int $months = 12): array
    {
        $start = today()->startOfMonth()->subMonths($months - 1);
        $spendDocuments = PurchaseDocument::with('supplier:id,legal_name,trade_name')
            ->whereIn('type', ['supplier_invoice', 'supplier_credit_note'])
            ->whereDate('issue_date', '>=', $start)
            ->get();
        $commitments = $this->commitments();
        $supplierRows = $this->supplierExposure($spendDocuments, $commitments);
        $totalExposure = (float) $supplierRows->sum('exposure');

        return [
            'metrics' => [
                'period_spend' => round((float) $spendDocuments->sum('amount'), 2),
                'open_commitments' => round((float) $commitments->sum('amount'), 2),
                'total_exposure' => round($totalExposure, 2),
                'supplier_concentration' => $totalExposure > 0 ? round((float) $supplierRows->max('exposure') / $totalExposure * 100, 1) : null,
                'open_orders' => $commitments->count(),
            ],
            'monthly_spend' => collect(range($months - 1, 0))->map(function (int $offset) use ($spendDocuments): array {
                $month = today()->startOfMonth()->subMonths($offset);

                return [
                    'month' => $month->format('Y-m'),
                    'label' => $month->format('M Y'),
                    'amount' => round((float) $spendDocuments
                        ->filter(fn (PurchaseDocument $document) => $document->issue_date->format('Y-m') === $month->format('Y-m'))
                        ->sum('amount'), 2),
                ];
            }),
            'suppliers' => $supplierRows->take(10)->values(),
            'commitments' => $commitments->sortByDesc('amount')->take(20)->values(),
            'price_alerts' => $this->priceAlerts(),
        ];
    }

    private function commitments(): Collection
    {
        $shippingTax = (float) PurchaseSetting::current()->shipping_tax_percent;
        $orders = PurchaseDocument::query()
            ->with([
                'supplier:id,legal_name,trade_name',
                'lines:id,purchase_document_id,quantity,unit_price,discount_percent,tax_percent',
                'derivedDocuments' => fn ($query) => $query
                    ->where('type', 'supplier_invoice')
                    ->select(['id', 'source_document_id', 'shipping_cost']),
                'derivedDocuments.lines:id,purchase_document_id,source_line_id,quantity',
            ])
            ->where('type', 'purchase_order')
            ->whereIn('status', ['ordered', 'partially_received', 'received'])
            ->get();

        return $orders->map(function (PurchaseDocument $order) use ($shippingTax): ?array {
            $invoiceLines = $order->derivedDocuments->flatMap->lines
                ->groupBy('source_line_id')
                ->map(fn (Collection $lines) => (float) $lines->sum('quantity'));
            $lineCommitment = $order->lines->sum(function ($line) use ($invoiceLines): float {
                $pending = max(0, (float) $line->quantity - (float) ($invoiceLines[$line->id] ?? 0));
                $base = $pending * (float) $line->unit_price * (1 - (float) $line->discount_percent / 100);

                return $base * (1 + (float) $line->tax_percent / 100);
            });
            $invoicedShipping = (float) $order->derivedDocuments->sum('shipping_cost');
            $pendingShipping = max(0, (float) $order->shipping_cost - $invoicedShipping);
            $amount = round($lineCommitment + $pendingShipping * (1 + $shippingTax / 100), 2);
            if ($amount <= 0.005) {
                return null;
            }

            return [
                'id' => $order->id,
                'number' => $order->number,
                'supplier_id' => $order->supplier_id,
                'supplier' => $order->supplier?->trade_name ?: $order->supplier?->legal_name ?: __('Supplier'),
                'status' => $order->status,
                'eta' => ($order->supplier_confirmed_delivery_date ?: $order->expected_delivery_date)?->toDateString(),
                'amount' => $amount,
            ];
        })->filter()->values();
    }

    private function supplierExposure(Collection $spendDocuments, Collection $commitments): Collection
    {
        $spend = $spendDocuments->groupBy('supplier_id');
        $committed = $commitments->groupBy('supplier_id');
        $supplierIds = $spend->keys()->merge($committed->keys())->unique();

        return $supplierIds->map(function ($supplierId) use ($spend, $committed): array {
            $documents = $spend->get($supplierId, collect());
            $orders = $committed->get($supplierId, collect());
            $supplier = $documents->first()?->supplier;
            $spendAmount = round((float) $documents->sum('amount'), 2);
            $commitmentAmount = round((float) $orders->sum('amount'), 2);

            return [
                'id' => (int) $supplierId,
                'name' => $supplier?->trade_name ?: $supplier?->legal_name ?: $orders->first()['supplier'],
                'spend' => $spendAmount,
                'commitment' => $commitmentAmount,
                'exposure' => round(max(0, $spendAmount) + $commitmentAmount, 2),
            ];
        })->sortByDesc('exposure')->values();
    }

    private function priceAlerts(): Collection
    {
        return SupplierProduct::with(['supplier:id,legal_name,trade_name', 'product:id,sku,name', 'prices'])
            ->where('is_active', true)
            ->get()
            ->map(function (SupplierProduct $supplierProduct): ?array {
                $prices = $supplierProduct->prices->take(2)->values();
                if ($prices->count() < 2 || (float) $prices[1]->unit_price <= 0) {
                    return null;
                }
                $change = ((float) $prices[0]->unit_price - (float) $prices[1]->unit_price) / (float) $prices[1]->unit_price * 100;

                return [
                    'supplier_product_id' => $supplierProduct->id,
                    'supplier_id' => $supplierProduct->supplier_id,
                    'supplier' => $supplierProduct->supplier->trade_name ?: $supplierProduct->supplier->legal_name,
                    'product' => $supplierProduct->product->name,
                    'sku' => $supplierProduct->product->sku,
                    'previous_price' => round((float) $prices[1]->unit_price, 2),
                    'current_price' => round((float) $prices[0]->unit_price, 2),
                    'change_percent' => round($change, 1),
                    'recorded_at' => Carbon::parse($prices[0]->recorded_at)->toDateString(),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $alert) => abs($alert['change_percent']))
            ->take(10)
            ->values();
    }
}
