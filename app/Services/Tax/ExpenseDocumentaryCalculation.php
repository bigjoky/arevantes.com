<?php

namespace App\Services\Tax;

use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ExpenseDocumentaryCalculation
{
    public const MODE = 'grouped_v1';

    public function __construct(private SupplierInvoiceCalculator $calculator) {}

    public function calculate(array $data, ?string $reason): array
    {
        try {
            if (! in_array($data['tax_regime'], ['standard', 'exempt', 'non_subject'], true)
                || $data['currency_code'] !== 'EUR'
                || ! BigDecimal::of($this->decimal($data['withholding_rate'], 3))->isZero()
                || ! BigDecimal::of($this->decimal($data['withholding_amount'], 2))->isZero()) {
                throw new InvalidArgumentException('Unsupported regime, currency or withholding.');
            }
            $lines = $quotas = $sources = $documentaryLines = [];
            foreach ($data['tax_lines'] ?? [] as $position => $line) {
                if ($line['kind'] !== 'vat') {
                    throw new InvalidArgumentException('Surcharges are not supported in this version.');
                }
                $id = $line['calculation_id'] ?? null;
                if (! is_string($id) || isset($sources[$id])) {
                    throw new InvalidArgumentException('Stable unique calculation IDs are required.');
                }
                $base = $this->decimal($line['taxable_base'], 2);
                $rate = $this->decimal($line['tax_rate'], 3);
                $deductible = $this->decimal($line['deductible_percent'], 2);
                $quota = BigDecimal::of($this->decimal($line['tax_amount'], 2));
                $key = $data['tax_regime'].':'.BigDecimal::of($rate)->toScale(3);
                $quotas[$key] = ($quotas[$key] ?? BigDecimal::of('0.00'))->plus($quota);
                $lines[] = ['id' => $id, 'quantity' => '1', 'unit_price' => $base, 'documentary_base' => $base,
                    'tax_rate' => $rate, 'deductible_percent' => $deductible, 'tax_treatment' => $data['tax_regime']];
                $sources[$id] = ['position' => $position + 1, 'exemption_reason' => $line['exemption_reason'] ?? null];
                $documentaryLines[] = ['calculation_id' => $id, 'kind' => 'vat', 'taxable_base' => $base,
                    'tax_rate' => $rate, 'tax_amount' => (string) $quota->toScale(2),
                    'deductible_percent' => $deductible, 'exemption_reason' => $line['exemption_reason'] ?? null];
            }
            $overrides = [];
            foreach ($quotas as $key => $quota) {
                $overrides[] = ['group_key' => $key, 'tax_amount' => (string) $quota->toScale(2), 'reason' => $reason];
            }
            $result = $this->calculator->calculate($lines, $overrides);
            // Expenses use numeric(12,2); reject overflow before any write or float adapter.
            foreach (['subtotal', 'tax_amount', 'total'] as $field) {
                if (BigDecimal::of($result['totals'][$field])->isGreaterThan('9999999999.99')) {
                    throw new InvalidArgumentException('Expense storage limit exceeded.');
                }
            }
            $persisted = [];
            foreach ($result['lines'] as $line) {
                $persisted[] = [...$sources[$line['id']], 'kind' => 'vat', 'taxable_base' => $line['documentary_base'],
                    'tax_rate' => $line['tax_rate'], 'tax_amount' => $line['tax_amount'],
                    'deductible_percent' => $line['deductible_percent'], 'deductible_amount' => $line['deductible_amount']];
            }
            $evidence = ['adapter_version' => self::MODE, 'input' => ['lines' => $lines, 'quota_overrides' => $overrides],
                'source_tax_lines' => $documentaryLines, 'result' => $result];
            $evidence['sha256'] = hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR));

            return ['tax_lines' => $persisted, 'subtotal' => $result['totals']['subtotal'], 'tax_amount' => $result['totals']['tax_amount'],
                'amount' => $result['totals']['total'], 'tax_calculation_version' => SupplierInvoiceCalculator::VERSION,
                'tax_calculation_snapshot' => $evidence];
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['calculation_mode' => __('The grouped calculation requires supported fiscal data, stable line IDs, exact decimals and justified rounding adjustments.')]);
        }
    }

    private function decimal(mixed $value, int $scale): string
    {
        if (! (is_string($value) || is_int($value)) || ! preg_match('/\A\d{1,12}(?:\.\d{1,'.$scale.'})?\z/', (string) $value)) {
            throw new InvalidArgumentException('Use exact decimal strings.');
        }

        return (string) $value;
    }
}
