<?php

namespace App\Services\Quotes;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

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
            if (isset($line['total_with_tax']) && $line['total_with_tax'] !== '') {
                $lineTotal = BigDecimal::of((string) $line['total_with_tax'])->toScale(2, RoundingMode::HalfUp);
                $factor = BigDecimal::of((string) $line['tax_percent'])->multipliedBy('0.01')->plus('1');
                $taxable = $lineTotal->dividedBy($factor, 2, RoundingMode::HalfUp);
                $discountFactor = BigDecimal::of('1')->minus(BigDecimal::of((string) ($line['discount_percent'] ?? 0))->multipliedBy('0.01'));
                if (! $discountFactor->isPositive() && $lineTotal->isPositive()) {
                    throw ValidationException::withMessages(["lines.{$position}.total_with_tax" => 'Un importe IVA incluido positivo no es compatible con un descuento del 100 %.']);
                }
                $lineSubtotal = $discountFactor->isPositive() ? $taxable->dividedBy($discountFactor, 2, RoundingMode::HalfUp) : BigDecimal::of('0.00');
                $lineDiscount = $lineSubtotal->minus($taxable);
                $lineTax = $lineTotal->minus($taxable);
                $line['unit_price'] = $lineSubtotal->dividedBy((string) $line['quantity'], 3, RoundingMode::HalfUp)->toFloat();
            }
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
