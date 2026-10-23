<?php

namespace Tests\Unit;

use App\Services\Quotes\QuoteCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuoteCalculatorTest extends TestCase
{
    public static function inclusiveTotals(): array
    {
        return [
            [100, 21, 0, 1, 82.64, 17.36],
            [100, 21, 10, 3, 82.64, 17.36],
            [0.03, 21, 0, 1000, 0.02, 0.01],
            [121, 21, 0, 0.001, 100.0, 21.0],
            [100, 0, 25, 7, 100.0, 0.0],
            [0, 21, 100, 1, 0.0, 0.0],
        ];
    }

    #[DataProvider('inclusiveTotals')]
    public function test_inclusive_total_is_preserved(float $gross, float $rate, float $discount, float $quantity, float $base, float $tax): void
    {
        $result = (new QuoteCalculator)->calculate([[
            'quantity' => $quantity, 'unit_price' => 0, 'total_with_tax' => $gross,
            'discount_percent' => $discount, 'tax_percent' => $rate,
        ]]);
        $this->assertEquals($gross, $result['amount']);
        $this->assertEqualsWithDelta($base, $result['subtotal'] - $result['discount_amount'], 0.000001);
        $this->assertEquals($tax, $result['tax_amount']);
        $this->assertEquals($gross, $result['lines'][0]['total']);
    }

    public function test_third_decimal_is_multiplied_before_rounding_and_unselected_lines_are_excluded(): void
    {
        $result = (new QuoteCalculator)->calculate([
            ['quantity' => 100, 'unit_price' => '0.333', 'discount_percent' => 0, 'tax_percent' => 21],
            ['quantity' => 3, 'unit_price' => 0, 'total_with_tax' => 100, 'discount_percent' => 10, 'tax_percent' => 21, 'is_selected' => false],
        ]);
        $this->assertEquals(33.30, $result['subtotal']);
        $this->assertEquals(40.29, $result['amount']);
        $this->assertEquals(100, $result['lines'][1]['total']);
    }
}
