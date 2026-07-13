<?php

namespace App\Services\Documents;

use App\Models\PurchaseDocument;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceMatcher
{
    public function match(PurchaseDocument $order, array $submittedLines): array
    {
        $summary = $this->summary($order)['lines']->keyBy('line_id');
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
                'quantity_variance' => round(max(0, $quantity - (float) $progress['pending_quantity']), 3),
                'price_variance' => round($unitPrice - (float) $line->unit_price, 2),
                'amount_variance' => round(($base + $tax) - ($expectedBase + $expectedTax), 2),
            ];
        })->filter()->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('Enter at least one pending quantity to invoice.')]);
        }

        $hasVariance = $lines->contains(fn (array $line) => abs($line['quantity_variance']) > 0.0005 || abs($line['price_variance']) > 0.005);

        return [
            'matching_status' => $hasVariance ? 'review_required' : 'matched',
            'variance_amount' => round((float) $lines->sum('amount_variance'), 2),
            'totals' => [
                'subtotal' => round((float) $lines->sum('subtotal'), 2),
                'tax_amount' => round((float) $lines->sum('tax_amount'), 2),
                'amount' => round((float) $lines->sum('total'), 2),
            ],
            'lines' => $lines->map(fn (array $line) => collect($line)->except(['quantity_variance', 'price_variance', 'amount_variance'])->all())->all(),
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
        $complete = $hasInvoices && $lines->every(fn (array $line) => $line['invoiced_quantity'] >= $line['ordered_quantity'] - 0.0005);
        $status = $hasOverbilling ? 'over_invoiced' : ($complete ? 'invoiced' : ($hasInvoices ? 'partially_invoiced' : 'not_invoiced'));

        return compact('status', 'lines');
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
}
