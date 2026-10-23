<?php

namespace App\Services\Quotes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class QuoteCalculator
{
    public function calculate(array $lines): array
    {
        $subtotal = $discount = $tax = $total = BigDecimal::of('0.00');
        $calculated = [];

        foreach ($lines as $position => $line) {
            $lineSubtotal = BigDecimal::of((string) $line['quantity'])->multipliedBy((string) $line['unit_price'])->toScale(2, RoundingMode::HalfUp);
            $lineDiscount = $lineSubtotal->multipliedBy((string) ($line['discount_percent'] ?? 0))->multipliedBy('0.01')->toScale(2, RoundingMode::HalfUp);
            $taxable = $lineSubtotal->minus($lineDiscount);
            $lineTax = $taxable->multipliedBy((string) $line['tax_percent'])->multipliedBy('0.01')->toScale(2, RoundingMode::HalfUp);
            $lineTotal = $taxable->plus($lineTax);
            $calculated[] = [...$line, 'position' => $position + 1, 'subtotal' => $lineSubtotal->toFloat(), 'discount_amount' => $lineDiscount->toFloat(), 'tax_amount' => $lineTax->toFloat(), 'total' => $lineTotal->toFloat()];
            if (array_key_exists('is_selected', $line) && ! filter_var($line['is_selected'], FILTER_VALIDATE_BOOL)) {
                continue;
            }
            $subtotal = $subtotal->plus($lineSubtotal);
            $discount = $discount->plus($lineDiscount);
            $tax = $tax->plus($lineTax);
            $total = $total->plus($lineTotal);
        }

        return ['lines' => $calculated, 'subtotal' => $subtotal->toFloat(), 'discount_amount' => $discount->toFloat(), 'tax_amount' => $tax->toFloat(), 'amount' => $total->toFloat()];
    }
}
