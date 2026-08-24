<?php

namespace App\Services\Finance;

use App\Models\PurchaseDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PurchaseCommitmentAnalysis
{
    /** @return Collection<int, array{remaining_net: float, orders: int}> */
    public function remainingNetForInstallations(Collection $installationIds): Collection
    {
        if ($installationIds->isEmpty()) {
            return collect();
        }

        return PurchaseDocument::query()
            ->where('type', 'purchase_order')
            ->whereIn('status', ['ordered', 'partially_received', 'received'])
            ->whereIn('installation_id', $installationIds)
            ->with(['derivedDocuments' => fn ($query) => $query
                ->where('type', 'supplier_invoice')
                ->whereNotIn('status', ['draft', 'review_required', 'cancelled'])
                ->with(['creditNotes' => fn ($credit) => $credit->whereNotIn('status', ['draft', 'cancelled'])])])
            ->get(['id', 'installation_id', 'subtotal', 'shipping_cost'])
            ->groupBy('installation_id')
            ->map(function (Collection $orders): array {
                $remaining = $orders->sum(function (PurchaseDocument $order): float {
                    $ordered = (float) $order->subtotal + (float) $order->shipping_cost;
                    $invoiced = $order->derivedDocuments->sum(fn (PurchaseDocument $invoice) => (float) $invoice->subtotal + (float) $invoice->shipping_cost)
                        + $order->derivedDocuments->flatMap->creditNotes->sum(fn (PurchaseDocument $credit) => (float) $credit->subtotal + (float) $credit->shipping_cost);

                    return max(0, $ordered - $invoiced);
                });

                return ['remaining_net' => round((float) $remaining, 2), 'orders' => $orders->count()];
            });
    }

    /** @return array{summary: array<string, float|int>, rows: Collection<int, array<string, mixed>>, bySupplier: Collection<int, array<string, mixed>>} */
    public function build(int $year): array
    {
        $periodStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $periodEnd = $periodStart->endOfYear();
        $today = CarbonImmutable::today();

        $rows = PurchaseDocument::query()
            ->where('type', 'purchase_order')
            ->whereIn('status', ['ordered', 'partially_received', 'received'])
            ->whereDate('issue_date', '<=', $periodEnd)
            ->with([
                'supplier:id,legal_name,trade_name,payment_terms_days',
                'installation:id,project_reference,site_name',
                'derivedDocuments' => fn ($query) => $query
                    ->where('type', 'supplier_invoice')
                    ->whereNotIn('status', ['draft', 'review_required', 'cancelled'])
                    ->select(['id', 'source_document_id', 'subtotal', 'shipping_cost', 'amount'])
                    ->with(['creditNotes' => fn ($query) => $query
                        ->whereNotIn('status', ['draft', 'cancelled'])
                        ->select(['id', 'source_document_id', 'subtotal', 'shipping_cost', 'amount'])]),
            ])
            ->orderBy('expected_delivery_date')
            ->orderBy('id')
            ->get([
                'id',
                'supplier_id',
                'installation_id',
                'number',
                'status',
                'issue_date',
                'expected_delivery_date',
                'supplier_confirmed_delivery_date',
                'subtotal',
                'shipping_cost',
                'amount',
            ])
            ->map(function (PurchaseDocument $order) use ($periodStart, $periodEnd, $today, $year): ?array {
                $orderedNet = (float) $order->subtotal + (float) $order->shipping_cost;
                $creditNotes = $order->derivedDocuments->flatMap->creditNotes;
                $invoicedNet = (float) $order->derivedDocuments->sum(fn (PurchaseDocument $invoice) => (float) $invoice->subtotal + (float) $invoice->shipping_cost)
                    + (float) $creditNotes->sum(fn (PurchaseDocument $credit) => (float) $credit->subtotal + (float) $credit->shipping_cost);
                $remainingNet = max(0, $orderedNet - $invoicedNet);
                $invoicedCash = (float) $order->derivedDocuments->sum('amount') + (float) $creditNotes->sum('amount');
                $remainingCash = max(0, (float) $order->amount - $invoicedCash);

                if ($remainingNet <= 0.005 && $remainingCash <= 0.005) {
                    return null;
                }

                $deliveryOn = CarbonImmutable::parse(
                    $order->supplier_confirmed_delivery_date
                        ?? $order->expected_delivery_date
                        ?? $order->issue_date,
                );
                $expectedPaymentOn = $deliveryOn->addDays((int) ($order->supplier?->payment_terms_days ?? 30));
                if ($year === $today->year && $expectedPaymentOn->isBefore($today)) {
                    $expectedPaymentOn = $today;
                }
                if ($expectedPaymentOn->isBefore($periodStart) || $expectedPaymentOn->isAfter($periodEnd)) {
                    return null;
                }

                return [
                    'id' => $order->id,
                    'number' => $order->number,
                    'status' => $order->status,
                    'supplier' => $order->supplier?->trade_name ?: $order->supplier?->legal_name,
                    'installationId' => $order->installation_id,
                    'installation' => $order->installation?->project_reference ?: $order->installation?->site_name,
                    'orderedNet' => round($orderedNet, 2),
                    'invoicedNet' => round($invoicedNet, 2),
                    'remainingNet' => round($remainingNet, 2),
                    'remainingCash' => round($remainingCash, 2),
                    'invoiceCount' => $order->derivedDocuments->count(),
                    'deliveryOn' => $deliveryOn->toDateString(),
                    'deliveryConfirmed' => $order->supplier_confirmed_delivery_date !== null,
                    'expectedPaymentOn' => $expectedPaymentOn->toDateString(),
                    'dueWithin30Days' => $expectedPaymentOn->betweenIncluded($today, $today->addDays(30)),
                    'href' => '/purchases/'.$order->id,
                ];
            })
            ->filter()
            ->values();

        $bySupplier = $rows
            ->groupBy(fn (array $row) => $row['supplier'] ?: __('Unassigned supplier'))
            ->map(fn (Collection $items, string $supplier) => [
                'supplier' => $supplier,
                'orders' => $items->count(),
                'remainingCash' => round((float) $items->sum('remainingCash'), 2),
            ])
            ->sortByDesc('remainingCash')
            ->values();

        return [
            'summary' => [
                'orders' => $rows->count(),
                'remainingNet' => round((float) $rows->sum('remainingNet'), 2),
                'remainingCash' => round((float) $rows->sum('remainingCash'), 2),
                'dueWithin30Days' => round((float) $rows->where('dueWithin30Days', true)->sum('remainingCash'), 2),
                'unconfirmedOrders' => $rows->where('deliveryConfirmed', false)->count(),
            ],
            'rows' => $rows,
            'bySupplier' => $bySupplier,
        ];
    }
}
