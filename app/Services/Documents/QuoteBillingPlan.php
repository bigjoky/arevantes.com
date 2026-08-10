<?php

namespace App\Services\Documents;

use App\Models\Quote;
use App\Models\SalesDocument;
use Illuminate\Support\Collection;

class QuoteBillingPlan
{
    /** @return array{allocated_percentage: float, issued_percentage: float, remaining_percentage: float, allocated_amount: float, issued_amount: float, remaining_amount: float, documents: array<int, array<string, mixed>>} */
    public function summary(Quote $quote): array
    {
        $documents = SalesDocument::query()
            ->where('quote_id', $quote->id)
            ->where('type', 'invoice')
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
                'amount' => (float) $document->amount,
                'issue_date' => $document->issue_date?->toDateString(),
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
    public function installmentForAmount(Quote $quote, float $amount, bool $isFinal): array
    {
        $quote->loadMissing('lines');
        $previousByPosition = $isFinal ? $this->previousLines($quote) : collect();
        $ratio = (float) $quote->amount > 0 ? $amount / (float) $quote->amount : 0;
        $lines = $quote->lines->where('is_selected', true)->map(function ($line) use ($ratio, $isFinal, $previousByPosition): array {
            $previous = $previousByPosition->get($line->position);
            $scale = fn (string $field): float => $isFinal
                ? round((float) $line->{$field} - (float) ($previous?->{$field} ?? 0), 2)
                : round((float) $line->{$field} * $ratio, 2);

            return [
                ...$line->only(['product_id', 'unit_id', 'position', 'description', 'quantity', 'discount_percent', 'tax_percent']),
                'unit_price' => max(0, $scale('unit_price')),
                'subtotal' => max(0, $scale('subtotal')),
                'discount_amount' => max(0, $scale('discount_amount')),
                'tax_amount' => max(0, $scale('tax_amount')),
                'total' => max(0, $scale('total')),
            ];
        })->values()->all();

        if (! $isFinal && count($lines) > 0) {
            $difference = round($amount - (float) collect($lines)->sum('total'), 2);
            $last = array_key_last($lines);
            $lines[$last]['tax_amount'] = max(0, round($lines[$last]['tax_amount'] + $difference, 2));
            $lines[$last]['total'] = round($lines[$last]['subtotal'] - $lines[$last]['discount_amount'] + $lines[$last]['tax_amount'], 2);
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

    private function previousLines(Quote $quote): Collection
    {
        return SalesDocument::query()
            ->where('quote_id', $quote->id)
            ->where('type', 'invoice')
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
