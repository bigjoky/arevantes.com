<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteSelection
{
    public function apply(Quote $quote, array $selectedLineIds): Quote
    {
        return DB::transaction(function () use ($quote, $selectedLineIds): Quote {
            $quote = Quote::lockForUpdate()->with(['lines.components', 'lines.product'])->findOrFail($quote->id);
            $selected = collect($selectedLineIds)->map(fn ($id) => (int) $id)->unique();
            $allowed = $quote->lines->whereIn('selection_mode', ['optional', 'alternative'])->pluck('id');
            if ($selected->diff($allowed)->isNotEmpty()) {
                throw ValidationException::withMessages(['selected_line_ids' => __('The selected options do not belong to this quote.')]);
            }

            $alternativeGroups = $quote->lines->where('selection_mode', 'alternative')->groupBy(fn ($line) => $line->option_group ?: 'line-'.$line->id);
            foreach ($alternativeGroups as $group => $lines) {
                if ($lines->whereIn('id', $selected)->count() > 1) {
                    throw ValidationException::withMessages(['selected_line_ids' => __('Choose only one alternative from group :group.', ['group' => $group])]);
                }
            }

            foreach ($quote->lines as $line) {
                $line->update(['is_selected' => $line->selection_mode === 'included' || $selected->contains($line->id)]);
            }

            $quote->load('lines.components');
            $totals = app(QuoteCalculator::class)->calculate($quote->lines->map(fn ($line) => $line->only(['product_id', 'unit_id', 'tax_rate_id', 'position', 'selection_mode', 'option_group', 'is_selected', 'description', 'quantity', 'unit_price', 'total_with_tax', 'discount_percent', 'tax_percent']))->all());
            $cost = $quote->lines->where('is_selected', true)->sum(function ($line): float {
                if ($line->components->isNotEmpty()) {
                    return $line->components->sum(fn ($component) => $line->effectiveComponentQuantity($component) * (float) $component->planned_unit_cost);
                }

                return (float) $line->quantity * (float) ($line->product?->cost_price ?? 0);
            });
            $netRevenue = (float) $totals['subtotal'] - (float) $totals['discount_amount'];
            $version = $quote->version + 1;
            $quote->update([
                'version' => $version,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'amount' => $totals['amount'],
                'expected_margin_amount' => round($netRevenue - $cost, 2),
            ]);
            $quote->versions()->create([
                'version' => $version,
                'change_note' => __('Customer selection updated.'),
                'subtotal' => $quote->subtotal,
                'discount_amount' => $quote->discount_amount,
                'tax_amount' => $quote->tax_amount,
                'amount' => $quote->amount,
                'expected_margin_amount' => $quote->expected_margin_amount,
                'lines' => $quote->lines->map(fn ($line) => [...$line->toArray(), 'components' => $line->components->toArray()])->all(),
                'notes' => $quote->notes,
            ]);

            return $quote->refresh();
        });
    }
}
