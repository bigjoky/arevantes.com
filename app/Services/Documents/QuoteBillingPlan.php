<?php

namespace App\Services\Documents;

use App\Models\Quote;
use App\Models\SalesDocument;
use Illuminate\Support\Collection;

class QuoteBillingPlan
{
    /** @return array{allocated_percentage: float, issued_percentage: float, remaining_percentage: float, allocated_amount: float, issued_amount: float, remaining_amount: float, documents: array<int, array<string, mixed>>} */
    public function summary(Quote $quote, ?int $excludeDocumentId = null): array
    {
        $documents = SalesDocument::query()
            ->where('quote_id', $quote->id)
            ->where('type', 'invoice')
            ->when($excludeDocumentId, fn ($query) => $query->whereKeyNot($excludeDocumentId))
            ->orderBy('billing_sequence')
            ->orderBy('id')
            ->get();
        $issued = $documents->where('status', '!=', 'draft');
        $allocatedAmount = round((float) $documents->sum('amount'), 2);
        $issuedAmount = round((float) $issued->sum('amount'), 2);
        $quoteAmount = round((float) $quote->amount, 2);
        $allocatedPercentage = $quoteAmount > 0
            ? min(100, round($allocatedAmount / $quoteAmount * 100, 2))
            : round((float) $documents->sum(fn ($document) => (float) ($document->billing_percentage ?? 100)), 2);
        $issuedPercentage = $quoteAmount > 0
            ? min(100, round($issuedAmount / $quoteAmount * 100, 2))
            : round((float) $issued->sum(fn ($document) => (float) ($document->billing_percentage ?? 100)), 2);

        return [
            'allocated_percentage' => $allocatedPercentage,
            'issued_percentage' => $issuedPercentage,
            'remaining_percentage' => max(0, round(100 - $allocatedPercentage, 2)),
            'allocated_amount' => $allocatedAmount,
            'issued_amount' => $issuedAmount,
            'remaining_amount' => max(0, round($quoteAmount - $allocatedAmount, 2)),
            'documents' => $documents->map(fn ($document) => [
                'id' => $document->id,
                'number' => $document->number,
                'status' => $document->status,
                'billing_percentage' => (float) ($document->billing_percentage ?? 100),
                'billing_sequence' => $document->billing_sequence,
                'billing_label' => $document->billing_label,
                'is_acceptance_invoice' => $document->is_acceptance_invoice,
                'collect_before_invoice' => $document->collect_before_invoice,
                'amount' => (float) $document->amount,
                'paid_amount' => (float) $document->paid_amount,
                'issue_date' => $document->issue_date?->toDateString(),
                'due_date' => $document->due_date?->toDateString(),
            ])->all(),
        ];
    }

    /** @return array{totals: array{subtotal: float, discount_amount: float, tax_amount: float, amount: float}, lines: array<int, array<string, mixed>>} */
    public function installment(Quote $quote, float $percentage, bool $isFinal): array
    {
        $amount = $isFinal
            ? $this->summary($quote)['remaining_amount']
            : round((float) $quote->amount * $percentage / 100, 2);

        return $this->installmentForAmount($quote, $amount, $isFinal);
    }

    /** @return array{totals: array{subtotal: float, discount_amount: float, tax_amount: float, amount: float}, lines: array<int, array<string, mixed>>} */
    public function installmentForAmount(Quote $quote, float $amount, bool $isFinal, ?int $excludeDocumentId = null): array
    {
        $quote->loadMissing('lines');
        $previousByPosition = $isFinal ? $this->previousLines($quote, $excludeDocumentId) : collect();
        $ratio = (float) $quote->amount > 0 ? $amount / (float) $quote->amount : 0;
        $lines = $quote->lines->where('is_selected', true)->map(function ($line) use ($ratio, $isFinal, $previousByPosition): array {
            $previous = $previousByPosition->get($line->position);
            $scale = fn (string $field): float => $isFinal
                ? round((float) $line->{$field} - (float) ($previous?->{$field} ?? 0), 2)
                : round((float) $line->{$field} * $ratio, 2);

            $subtotal = max(0, $scale('subtotal'));
            $discount = min($subtotal, max(0, $scale('discount_amount')));
            $tax = max(0, $scale('tax_amount'));

            return [
                ...$line->only(['product_id', 'unit_id', 'position', 'description', 'quantity', 'discount_percent', 'tax_percent']),
                'unit_price' => max(0, $scale('unit_price')),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total' => round($subtotal - $discount + $tax, 2),
            ];
        })->values()->all();

        $paidPositions = $quote->lines->where('is_selected', true)->filter(fn ($line) => (float) $line->total > 0)->pluck('position')->flip();
        $taxedPositions = $quote->lines->where('is_selected', true)->filter(fn ($line) => (float) $line->tax_amount > 0)->pluck('position')->flip();
        $difference = round($amount - (float) collect($lines)->sum('total'), 2);
        // Allocate rounding to paid lines, keeping free lines and exempt VAT intact.
        foreach (array_reverse(array_keys($lines)) as $index) {
            if ($difference == 0 || ! $taxedPositions->has($lines[$index]['position']) || ($difference < 0 && $lines[$index]['tax_amount'] <= 0)) {
                continue;
            }
            $adjustment = max(-$lines[$index]['tax_amount'], $difference);
            $lines[$index]['tax_amount'] = round($lines[$index]['tax_amount'] + $adjustment, 2);
            $lines[$index]['total'] = round($lines[$index]['total'] + $adjustment, 2);
            $difference = round($difference - $adjustment, 2);
        }
        foreach (array_reverse(array_keys($lines)) as $index) {
            if ($difference == 0 || ! $paidPositions->has($lines[$index]['position'])) {
                continue;
            }
            $net = $lines[$index]['subtotal'] - $lines[$index]['discount_amount'];
            $adjustment = max(-$net, $difference);
            $lines[$index]['subtotal'] = round($lines[$index]['subtotal'] + $adjustment, 2);
            $lines[$index]['total'] = round($lines[$index]['total'] + $adjustment, 2);
            $difference = round($difference - $adjustment, 2);
        }

        return [
            'totals' => [
                'subtotal' => round((float) collect($lines)->sum('subtotal'), 2),
                'discount_amount' => round((float) collect($lines)->sum('discount_amount'), 2),
                'tax_amount' => round((float) collect($lines)->sum('tax_amount'), 2),
                'amount' => round((float) collect($lines)->sum('total'), 2),
            ],
            'lines' => $lines,
        ];
    }

    private function previousLines(Quote $quote, ?int $excludeDocumentId = null): Collection
    {
        return SalesDocument::query()
            ->where('quote_id', $quote->id)
            ->where('type', 'invoice')
            ->when($excludeDocumentId, fn ($query) => $query->whereKeyNot($excludeDocumentId))
            ->with('lines')
            ->get()
            ->flatMap->lines
            ->groupBy('position')
            ->map(fn ($lines) => (object) [
                'unit_price' => $lines->sum('unit_price'),
                'subtotal' => $lines->sum('subtotal'),
                'discount_amount' => $lines->sum('discount_amount'),
                'tax_amount' => $lines->sum('tax_amount'),
                'total' => $lines->sum('total'),
            ]);
    }
}
