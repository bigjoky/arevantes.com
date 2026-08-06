<?php

namespace App\Services\Documents;

use App\Models\Quote;
use App\Models\SalesDocument;
use Illuminate\Support\Collection;

class QuoteBillingPlan
{
    /** @return array{allocated_percentage: float, issued_percentage: float, remaining_percentage: float, allocated_amount: float, issued_amount: float, documents: array<int, array<string, mixed>>} */
    public function summary(Quote $quote): array
    {
        $documents = SalesDocument::query()
            ->where('quote_id', $quote->id)
            ->where('type', 'invoice')
            ->orderBy('billing_sequence')
            ->orderBy('id')
            ->get();
        $allocatedPercentage = round((float) $documents->sum(fn ($document) => (float) ($document->billing_percentage ?? 100)), 2);
        $issued = $documents->where('status', '!=', 'draft');

        return [
            'allocated_percentage' => $allocatedPercentage,
            'issued_percentage' => round((float) $issued->sum(fn ($document) => (float) ($document->billing_percentage ?? 100)), 2),
            'remaining_percentage' => max(0, round(100 - $allocatedPercentage, 2)),
            'allocated_amount' => round((float) $documents->sum('amount'), 2),
            'issued_amount' => round((float) $issued->sum('amount'), 2),
            'documents' => $documents->map(fn ($document) => [
                'id' => $document->id,
                'number' => $document->number,
                'status' => $document->status,
                'billing_percentage' => (float) ($document->billing_percentage ?? 100),
                'billing_sequence' => $document->billing_sequence,
                'billing_label' => $document->billing_label,
                'is_acceptance_invoice' => $document->is_acceptance_invoice,
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
        $quote->loadMissing('lines');
        $previousByPosition = $isFinal ? $this->previousLines($quote) : collect();
        $ratio = $percentage / 100;
        $lines = $quote->lines->map(function ($line) use ($ratio, $isFinal, $previousByPosition): array {
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
        })->all();

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
