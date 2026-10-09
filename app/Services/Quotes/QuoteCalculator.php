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
            if (isset($line['total_with_tax']) && $line['total_with_tax'] !== '') {
                $lineTotal = round((float) $line['total_with_tax'], 2);
                $taxable = round($lineTotal / (1 + (float) $line['tax_percent'] / 100), 2);
                $discountFactor = 1 - (float) ($line['discount_percent'] ?? 0) / 100;
                $lineSubtotal = $discountFactor > 0 ? round($taxable / $discountFactor, 2) : 0;
                $lineDiscount = round($lineSubtotal - $taxable, 2);
                $lineTax = round($lineTotal - $taxable, 2);
                $line['unit_price'] = round($lineSubtotal / (float) $line['quantity'], 3);
            }
            $calculated[] = [...$line, 'position' => $position + 1, 'subtotal' => $lineSubtotal, 'discount_amount' => $lineDiscount, 'tax_amount' => $lineTax, 'total' => $lineTotal];
            if (array_key_exists('is_selected', $line) && ! filter_var($line['is_selected'], FILTER_VALIDATE_BOOL)) {
                continue;
            }
            $subtotal += $lineSubtotal;
            $discount += $lineDiscount;
            $tax += $lineTax;
            $total += $lineTotal;
        }

        return ['lines' => $calculated, 'subtotal' => round($subtotal, 2), 'discount_amount' => round($discount, 2), 'tax_amount' => round($tax, 2), 'amount' => round($total, 2)];
    }
}
