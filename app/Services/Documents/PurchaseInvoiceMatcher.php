<?php

namespace App\Services\Documents;

use App\Models\PurchaseDocument;
use App\Models\PurchaseSetting;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceMatcher
{
    public function match(PurchaseDocument $order, array $submittedLines, ?float $submittedShippingCost = null): array
    {
        $summaryData = $this->summary($order);
        $summary = $summaryData['lines']->keyBy('line_id');
        $submitted = collect($submittedLines)->keyBy(fn (array $line) => (int) $line['line_id']);
        $unknown = $submitted->keys()->diff($summary->keys());
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => __('The invoice contains lines outside this purchase order.')]);
        }

        $useDefaults = $submitted->isEmpty();
        $lines = $order->lines->map(function ($line) use ($submitted, $summary, $useDefaults) {
            $progress = $summary->get($line->id);
            $input = $submitted->get($line->id);
            $quantity = round((float) ($input['quantity'] ?? ($useDefaults ? $progress['pending_quantity'] : 0)), 3);
            $unitPrice = round((float) ($input['unit_price'] ?? $line->unit_price), 2);
            if ($quantity <= 0) {
                return null;
            }

            $expectedQuantity = min($quantity, (float) $progress['pending_quantity']);
            $base = round($quantity * $unitPrice * (1 - (float) $line->discount_percent / 100), 2);
            $tax = round($base * (float) $line->tax_percent / 100, 2);
            $expectedBase = round($expectedQuantity * (float) $line->unit_price * (1 - (float) $line->discount_percent / 100), 2);
            $expectedTax = round($expectedBase * (float) $line->tax_percent / 100, 2);

            return [
                'source_line_id' => $line->id,
                'product_id' => $line->product_id,
                'position' => $line->position,
                'description' => $line->description,
                'quantity' => $quantity,
                'received_quantity' => 0,
                'returned_quantity' => 0,
                'unit_price' => $unitPrice,
                'discount_percent' => $line->discount_percent,
                'tax_percent' => $line->tax_percent,
                'subtotal' => $base,
                'tax_amount' => $tax,
                'total' => $base + $tax,
                'expected_total' => $expectedBase + $expectedTax,
                'quantity_variance' => round(max(0, $quantity - (float) $progress['pending_quantity']), 3),
                'price_variance' => round($unitPrice - (float) $line->unit_price, 2),
                'amount_variance' => round(($base + $tax) - ($expectedBase + $expectedTax), 2),
            ];
        })->filter()->values();

        $expectedShippingCost = (float) $summaryData['shipping']['pending_cost'];
        $shippingCost = round($submittedShippingCost ?? $expectedShippingCost, 2);
        if ($shippingCost < 0) {
            throw ValidationException::withMessages(['shipping_cost' => __('Transport cost cannot be negative.')]);
        }
        if ($lines->isEmpty() && $shippingCost <= 0) {
            throw ValidationException::withMessages(['lines' => __('Enter at least one pending quantity to invoice.')]);
        }

        $shippingVariance = round($shippingCost - $expectedShippingCost, 2);
        $settings = PurchaseSetting::current();
        $shippingTaxRate = (float) $settings->shipping_tax_percent;
        $shippingTax = round($shippingCost * $shippingTaxRate / 100 + 1e-9, 2);
        $hasVariance = $this->exceedsTolerance($shippingVariance, $expectedShippingCost, $settings)
            || $lines->contains(fn (array $line) => abs($line['quantity_variance']) > 0.0005
                || $this->exceedsTolerance($line['amount_variance'], ($line['total'] ?? 0) - $line['amount_variance'], $settings));
        $lineSubtotal = round((float) $lines->sum('subtotal'), 2);
        $lineTax = round((float) $lines->sum('tax_amount'), 2);

        return [
            'matching_status' => $hasVariance ? 'review_required' : 'matched',
            'variance_amount' => round((float) $lines->sum('amount_variance') + $shippingVariance * (1 + $shippingTaxRate / 100), 2),
            'totals' => [
                'subtotal' => round($lineSubtotal + $shippingCost, 2),
                'tax_amount' => round($lineTax + $shippingTax, 2),
                'shipping_cost' => $shippingCost,
                'amount' => round($lineSubtotal + $shippingCost + $lineTax + $shippingTax, 2),
            ],
            'lines' => $lines->map(fn (array $line) => collect($line)->except(['quantity_variance', 'price_variance', 'amount_variance', 'expected_total'])->all())->all(),
        ];
    }

    /** @return array{status: string, lines: Collection<int, array<string, mixed>>} */
    public function summary(PurchaseDocument $order): array
    {
        $order->loadMissing('lines.product');
        $invoices = PurchaseDocument::with('lines')->where('type', 'supplier_invoice')->where('source_document_id', $order->id)->get();
        $invoiceLines = $invoices->flatMap->lines;
        $credits = PurchaseDocument::with('lines')->where('type', 'supplier_credit_note')->whereIn('source_document_id', $invoices->pluck('id'))->get();

        $lines = $order->lines->map(function ($line) use ($invoiceLines, $credits) {
            $linkedInvoiceLines = $invoiceLines->where('source_line_id', $line->id);
            $invoiceLineIds = $linkedInvoiceLines->pluck('id');
            $invoiced = (float) $linkedInvoiceLines->sum('quantity');
            $credited = abs((float) $credits->flatMap->lines->whereIn('source_line_id', $invoiceLineIds)->sum('quantity'));
            $netInvoiced = round($invoiced - $credited, 3);
            $available = $line->product->track_stock
                ? max(0, (float) $line->received_quantity - (float) $line->returned_quantity)
                : (float) $line->quantity;

            return [
                'line_id' => $line->id,
                'ordered_quantity' => (float) $line->quantity,
                'received_quantity' => (float) $line->received_quantity,
                'returned_quantity' => (float) $line->returned_quantity,
                'available_to_invoice' => round($available, 3),
                'invoiced_quantity' => $netInvoiced,
                'pending_quantity' => round(max(0, $available - $netInvoiced), 3),
                'over_invoiced_quantity' => round(max(0, $netInvoiced - $available), 3),
            ];
        });

        $hasInvoices = $lines->contains(fn (array $line) => $line['invoiced_quantity'] > 0.0005);
        $hasOverbilling = $lines->contains(fn (array $line) => $line['over_invoiced_quantity'] > 0.0005);
        $creditedShipping = (float) $credits->sum('shipping_cost');
        $invoicedShipping = round((float) $invoices->sum('shipping_cost') + $creditedShipping, 2);
        $pendingShipping = round(max(0, (float) $order->shipping_cost - $invoicedShipping), 2);
        $hasOverbilling = $hasOverbilling || $invoicedShipping > (float) $order->shipping_cost + 0.005;
        $hasInvoices = $hasInvoices || $invoicedShipping > 0.005;
        $complete = $hasInvoices
            && $lines->every(fn (array $line) => $line['invoiced_quantity'] >= $line['ordered_quantity'] - 0.0005)
            && $pendingShipping <= 0.005;
        $status = $hasOverbilling ? 'over_invoiced' : ($complete ? 'invoiced' : ($hasInvoices ? 'partially_invoiced' : 'not_invoiced'));
        $shipping = ['ordered_cost' => (float) $order->shipping_cost, 'invoiced_cost' => $invoicedShipping, 'pending_cost' => $pendingShipping];

        return compact('status', 'lines', 'shipping');
    }

    public function refresh(PurchaseDocument $order): string
    {
        $status = $this->summary($order)['status'];
        $order->update(['invoicing_status' => $status]);

        return $status;
    }

    public function creditable(PurchaseDocument $invoice): Collection
    {
        $invoice->loadMissing('lines');
        $credited = PurchaseDocument::with('lines')
            ->where('type', 'supplier_credit_note')
            ->where('source_document_id', $invoice->id)
            ->get()
            ->flatMap->lines
            ->groupBy('source_line_id');

        return $invoice->lines->map(fn ($line) => [
            'line_id' => $line->id,
            'description' => $line->description,
            'invoiced_quantity' => (float) $line->quantity,
            'credited_quantity' => abs((float) $credited->get($line->id, collect())->sum('quantity')),
            'creditable_quantity' => round(max(0, (float) $line->quantity - abs((float) $credited->get($line->id, collect())->sum('quantity'))), 3),
            'unit_price' => (float) $line->unit_price,
        ]);
    }

    public function creditableShipping(PurchaseDocument $invoice): float
    {
        $credited = (float) PurchaseDocument::where('type', 'supplier_credit_note')
            ->where('source_document_id', $invoice->id)
            ->sum('shipping_cost');

        return round(max(0, (float) $invoice->shipping_cost + $credited), 2);
    }

    public function details(PurchaseDocument $invoice): Collection
    {
        $invoice->loadMissing(['lines.sourceLine.product']);

        return $invoice->lines->map(function ($line) {
            $orderLine = $line->sourceLine;

            return [
                'line_id' => $line->id,
                'description' => $line->description,
                'ordered_quantity' => (float) ($orderLine?->quantity ?? 0),
                'received_quantity' => max(0, (float) ($orderLine?->received_quantity ?? 0) - (float) ($orderLine?->returned_quantity ?? 0)),
                'invoiced_quantity' => (float) $line->quantity,
                'ordered_unit_price' => (float) ($orderLine?->unit_price ?? 0),
                'invoiced_unit_price' => (float) $line->unit_price,
                'quantity_variance' => round(max(0, (float) $line->quantity - max(0, (float) ($orderLine?->received_quantity ?? 0) - (float) ($orderLine?->returned_quantity ?? 0))), 3),
                'price_variance' => round((float) $line->unit_price - (float) ($orderLine?->unit_price ?? 0), 2),
            ];
        });
    }

    private function exceedsTolerance(float $variance, float $expectedAmount, PurchaseSetting $settings): bool
    {
        $allowed = max(
            (float) $settings->matching_amount_tolerance,
            abs($expectedAmount) * (float) $settings->matching_percent_tolerance / 100,
        );

        return abs($variance) > $allowed + 0.005;
    }
}
