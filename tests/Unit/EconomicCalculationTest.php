<?php

namespace Tests\Unit;

use App\Services\Quotes\QuoteCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EconomicCalculationTest extends TestCase
{
    public function test_positive_gross_amount_and_full_discount_cannot_produce_a_negative_discount(): void
    {
        $this->expectException(ValidationException::class);
        (new QuoteCalculator)->calculate([['quantity' => 3, 'unit_price' => 10, 'discount_percent' => 100, 'tax_percent' => 21, 'total_with_tax' => 121]]);
    }

    public function test_three_decimal_prices_and_existing_line_rounding_remain_consistent(): void
    {
        $totals = (new QuoteCalculator)->calculate([['quantity' => '3', 'unit_price' => '0.333', 'discount_percent' => '10', 'tax_percent' => '21']]);
        $this->assertSame(1.0, $totals['subtotal']);
        $this->assertSame(0.1, $totals['discount_amount']);
        $this->assertSame(0.19, $totals['tax_amount']);
        $this->assertSame(1.09, $totals['amount']);
    }

    public function test_zero_gross_with_full_discount_remains_valid(): void
    {
        $totals = (new QuoteCalculator)->calculate([['quantity' => 1, 'unit_price' => 10, 'discount_percent' => 100, 'tax_percent' => 21, 'total_with_tax' => 0]]);
        $this->assertSame(0.0, $totals['amount']);
        $this->assertSame(0.0, $totals['discount_amount']);
    }
}
