<?php

namespace Tests\Unit;

use App\Services\Tax\SupplierInvoiceCalculator;
use Brick\Math\BigDecimal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupplierInvoiceCalculatorTest extends TestCase
{
    private function line(string $id = 'a', string $price = '0.03', array $extra = []): array
    {
        return [...['id' => $id, 'quantity' => '1', 'unit_price' => $price, 'tax_rate' => '21'], ...$extra];
    }

    public function test_vat_is_rounded_once_per_group_and_allocated_by_stable_id(): void
    {
        $engine = new SupplierInvoiceCalculator;
        $lines = [$this->line('b'), $this->line('a')];
        $result = $engine->calculate($lines);
        $this->assertSame('0.06', $result['totals']['subtotal']);
        $this->assertSame('0.01', $result['totals']['tax_amount']);
        $this->assertSame('0.07', $result['totals']['total']);
        $byId = array_column($result['lines'], null, 'id');
        $this->assertSame('0.01', $byId['a']['tax_amount']);
        $this->assertSame('0.00', $byId['b']['tax_amount']);
        $reordered = array_column($engine->calculate(array_reverse($lines))['lines'], null, 'id');
        $this->assertSame($byId['a'], $reordered['a']);
        $this->assertSame($byId['b'], $reordered['b']);
    }

    public function test_unit_price_precision_is_not_lost_before_multiplication(): void
    {
        $result = (new SupplierInvoiceCalculator)->calculate([$this->line('a', '0.333333', ['quantity' => '3'])]);
        $this->assertSame('0.999999', $result['lines'][0]['calculated_base_precise']);
        $this->assertSame('1.00', $result['totals']['subtotal']);
        $this->assertSame('0.21', $result['totals']['tax_amount']);
    }

    public function test_documentary_base_and_quota_are_preserved_with_their_adjustments(): void
    {
        $result = (new SupplierInvoiceCalculator)->calculate([
            $this->line('a', '3.465', ['tax_rate' => '10', 'documentary_base' => '3.46', 'base_adjustment_reason' => 'Base on supplier invoice']),
        ], [['group_key' => 'standard:10.000', 'tax_amount' => '0.34', 'reason' => 'Quota on supplier invoice']]);
        $this->assertSame('3.47', $result['lines'][0]['calculated_base']);
        $this->assertSame('-0.01', $result['lines'][0]['base_adjustment']);
        $this->assertSame('0.35', $result['tax_groups'][0]['calculated_tax']);
        $this->assertSame('-0.01', $result['tax_groups'][0]['tax_adjustment']);
        $this->assertSame('3.80', $result['totals']['total']);
        $this->assertSame('0.34', $result['totals']['deductible_amount']);
        $this->assertSame('documentary', $result['lines'][0]['base_source']);
        $this->assertSame('documentary', $result['tax_groups'][0]['quota_source']);
    }

    public function test_credit_note_exactly_reverses_invoice_monetary_amounts(): void
    {
        $engine = new SupplierInvoiceCalculator;
        $lines = [$this->line('a', '3.46', ['tax_rate' => '10', 'deductible_percent' => '50']), $this->line('b', '2.35')];
        $invoice = $engine->calculate($lines);
        $credit = $engine->calculate($lines, [], 'credit_note');
        foreach ($invoice['totals'] as $field => $amount) {
            $this->assertSame((string) BigDecimal::of($amount)->negated(), $credit['totals'][$field]);
        }
        foreach ($invoice['lines'] as $index => $line) {
            foreach (['documentary_base', 'tax_amount', 'deductible_amount', 'total'] as $field) {
                $this->assertSame((string) BigDecimal::of($line[$field])->negated(), $credit['lines'][$index][$field]);
            }
        }
    }

    public function test_deduction_is_grouped_and_cannot_exceed_allocated_vat(): void
    {
        $result = (new SupplierInvoiceCalculator)->calculate([
            $this->line('a', '0.05', ['deductible_percent' => '50']),
            $this->line('b', '0.05', ['deductible_percent' => '50']),
        ]);
        $this->assertSame('0.02', $result['totals']['tax_amount']);
        $this->assertSame('0.01', $result['totals']['deductible_amount']);
        $this->assertSame('0.01', $result['totals']['non_deductible_amount']);
        foreach ($result['lines'] as $line) {
            $this->assertTrue(BigDecimal::of($line['deductible_amount'])->isLessThanOrEqualTo($line['tax_amount']));
        }
    }

    public function test_rates_and_tax_treatments_remain_separate(): void
    {
        $result = (new SupplierInvoiceCalculator)->calculate([
            $this->line('a', '100', ['tax_rate' => '21']),
            $this->line('b', '100', ['tax_rate' => '10']),
            $this->line('c', '100', ['tax_rate' => '0', 'tax_treatment' => 'exempt']),
            $this->line('d', '100', ['tax_rate' => '0', 'tax_treatment' => 'non_subject']),
        ]);
        $this->assertCount(4, $result['tax_groups']);
        $this->assertSame('31.00', $result['totals']['tax_amount']);
        $this->assertSame('431.00', $result['totals']['total']);
    }

    public function test_many_lines_conserve_group_totals_without_accumulating_rounding(): void
    {
        $lines = array_map(fn ($id) => $this->line('line-'.$id), range(1, 500));
        $result = (new SupplierInvoiceCalculator)->calculate($lines);
        $this->assertSame('3.15', $result['totals']['tax_amount']);
        $sum = BigDecimal::of('0.00');
        foreach ($result['lines'] as $line) {
            $sum = $sum->plus($line['tax_amount']);
        }
        $this->assertSame('3.15', (string) $sum);
    }

    public function test_manual_quota_allocation_never_exceeds_a_line_base(): void
    {
        $result = (new SupplierInvoiceCalculator)->calculate([
            $this->line('a', '0.01', ['tax_rate' => '49']),
            $this->line('b', '0.96', ['tax_rate' => '49']),
        ], [['group_key' => 'standard:49.000', 'tax_amount' => '0.50', 'reason' => 'Documentary group quota']]);
        $this->assertSame('0.50', $result['totals']['tax_amount']);
        foreach ($result['lines'] as $line) {
            $this->assertTrue(BigDecimal::of($line['tax_amount'])->isLessThanOrEqualTo($line['documentary_base']));
        }
    }

    public function test_discount_is_applied_with_decimal_precision(): void
    {
        $result = (new SupplierInvoiceCalculator)->calculate([$this->line('a', '19.999999', ['quantity' => '3', 'discount_percent' => '12.5'])]);
        $this->assertSame('52.50', $result['totals']['subtotal']);
        $this->assertSame('11.03', $result['totals']['tax_amount']);
        $this->assertSame('63.53', $result['totals']['total']);
    }

    public function test_quota_tolerance_is_per_group_not_multiplied_by_line_count(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SupplierInvoiceCalculator)->calculate([$this->line('a', '100'), $this->line('b', '100')], [
            ['group_key' => 'standard:21.000', 'tax_amount' => '42.03', 'reason' => 'Outside group policy'],
        ]);
    }

    public static function invalidInputs(): array
    {
        return [
            'binary float' => [['unit_price' => 0.1]],
            'scientific notation' => [['unit_price' => '1e3']],
            'localized separator' => [['unit_price' => '3,46']],
            'excess precision' => [['unit_price' => '0.1234567']],
            'negative quantity' => [['quantity' => '-1']],
            'zero quantity' => [['quantity' => '0']],
            'invalid discount' => [['discount_percent' => '101']],
            'invalid deduction' => [['deductible_percent' => '101']],
            'unsupported regime' => [['tax_treatment' => 'reverse_charge']],
            'taxed exemption' => [['tax_treatment' => 'exempt']],
            'base missing reason' => [['documentary_base' => '1.00']],
            'base excess decimals' => [['documentary_base' => '0.031']],
            'unsupported surcharge' => [['tax_kind' => 'equivalence_surcharge']],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_fail_instead_of_silently_changing_values(array $extra): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SupplierInvoiceCalculator)->calculate([$this->line(extra: $extra)]);
    }

    public function test_duplicate_ids_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SupplierInvoiceCalculator)->calculate([$this->line(), $this->line()]);
    }

    public function test_unknown_group_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SupplierInvoiceCalculator)->calculate([$this->line()], [['group_key' => 'standard:10.000', 'tax_amount' => '0.01']]);
    }

    public function test_adjusted_quota_requires_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SupplierInvoiceCalculator)->calculate([$this->line('a', '100')], [['group_key' => 'standard:21.000', 'tax_amount' => '21.01']]);
    }

    public function test_unsupported_currency_is_not_silently_treated_as_euros(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SupplierInvoiceCalculator)->calculate([$this->line()], currency: 'USD');
    }

    public function test_deterministic_matrix_conserves_bases_quotas_deductions_and_totals(): void
    {
        $engine = new SupplierInvoiceCalculator;
        foreach (['0', '4', '10', '21', '49', '100'] as $rate) {
            foreach (['0', '25', '50', '100'] as $percent) {
                $lines = [];
                foreach (range(1, 40) as $i) {
                    $lines[] = $this->line('line-'.$i, '0.'.str_pad((string) (($i * 17) % 100), 2, '0', STR_PAD_LEFT), ['tax_rate' => $rate, 'deductible_percent' => $percent]);
                }
                $result = $engine->calculate($lines);
                $this->assertSame($result, $engine->calculate($lines));
                $tax = $deductible = $base = $total = BigDecimal::of('0.00');
                foreach ($result['lines'] as $line) {
                    $tax = $tax->plus($line['tax_amount']);
                    $base = $base->plus($line['documentary_base']);
                    $total = $total->plus($line['total']);
                    $deductible = $deductible->plus($line['deductible_amount']);
                    $this->assertTrue(BigDecimal::of($line['deductible_amount'])->isLessThanOrEqualTo($line['tax_amount']));
                    $this->assertTrue(BigDecimal::of($line['non_deductible_amount'])->isGreaterThanOrEqualTo('0'));
                }
                $this->assertSame((string) $tax, $result['totals']['tax_amount']);
                $this->assertSame((string) $base, $result['totals']['subtotal']);
                $this->assertSame((string) $total, $result['totals']['total']);
                $this->assertSame((string) $deductible, $result['totals']['deductible_amount']);
            }
        }
    }
}
