<?php

namespace App\Services\Quotes;

class QuoteCalculator
{
    public function calculate(array $lines): array
    {
        $subtotal = $discount = $tax = $total = 0;
        $calculated = [];

        foreach ($lines as $position => $line) {
            $lineSubtotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $lineDiscount = round($lineSubtotal * (float) ($line['discount_percent'] ?? 0) / 100, 2);
            $taxable = $lineSubtotal - $lineDiscount;
            $lineTax = round($taxable * (float) $line['tax_percent'] / 100, 2);
            $lineTotal = $taxable + $lineTax;
            $calculated[] = [...$line, 'position' => $position + 1, 'subtotal' => $lineSubtotal, 'discount_amount' => $lineDiscount, 'tax_amount' => $lineTax, 'total' => $lineTotal];
            $subtotal += $lineSubtotal;
            $discount += $lineDiscount;
            $tax += $lineTax;
            $total += $lineTotal;
        }

        return ['lines' => $calculated, 'subtotal' => round($subtotal, 2), 'discount_amount' => round($discount, 2), 'tax_amount' => round($tax, 2), 'amount' => round($total, 2)];
    }
}
