<?php

declare(strict_types=1);

namespace App\Services\Tax;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Pure, versioned calculation; never reads or rewrites persisted documents.
 * Inputs are decimal strings and positive magnitudes. Credit notes reverse all
 * output amounts. Stable line IDs determine penny allocation, not array order.
 */
final class SupplierInvoiceCalculator
{
    public const VERSION = 'supplier-vat-v1';

    public function calculate(array $lines, array $quotaOverrides = [], string $documentType = 'invoice', string $currency = 'EUR'): array
    {
        if ($currency !== 'EUR' || ! in_array($documentType, ['invoice', 'credit_note'], true) || count($lines) < 1 || count($lines) > 500) {
            throw new InvalidArgumentException('Unsupported currency, document type or line count.');
        }
        $details = $groups = [];
        foreach ($lines as $line) {
            if (! is_array($line) || array_diff(array_keys($line), ['id', 'quantity', 'unit_price', 'discount_percent', 'tax_rate', 'deductible_percent', 'tax_treatment', 'documentary_base', 'base_adjustment_reason'])) {
                throw new InvalidArgumentException('Unsupported line fields.');
            }
            $id = $line['id'] ?? null;
            if (! is_string($id) || ! preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $id) || isset($details[$id])) {
                throw new InvalidArgumentException('Each line needs a unique, stable string ID.');
            }
            $quantity = $this->decimal($line['quantity'] ?? null, 'quantity');
            $price = $this->decimal($line['unit_price'] ?? null, 'unit_price');
            $discount = $this->percent($line['discount_percent'] ?? '0', 'discount_percent');
            $rate = $this->percent($line['tax_rate'] ?? null, 'tax_rate', 3);
            $deductible = $this->percent($line['deductible_percent'] ?? '100', 'deductible_percent');
            $treatment = $line['tax_treatment'] ?? 'standard';
            if ($quantity->isZero() || ! in_array($treatment, ['standard', 'exempt', 'non_subject'], true)
                || ($treatment !== 'standard' && ! $rate->isZero())) {
                throw new InvalidArgumentException('Invalid quantity or unsupported tax treatment.');
            }
            $rawBase = $quantity->multipliedBy($price)->multipliedBy(BigDecimal::of('1')->minus($discount->multipliedBy('0.01')));
            $calculatedBase = $rawBase->toScale(2, RoundingMode::HalfUp);
            $base = array_key_exists('documentary_base', $line)
                ? $this->decimal($line['documentary_base'], 'documentary_base', 2)->toScale(2)
                : $calculatedBase;
            $adjustment = $base->minus($calculatedBase);
            $reason = $adjustment->isZero() ? null : $this->reason($line['base_adjustment_reason'] ?? null);
            $key = $treatment.':'.$rate->toScale(3);
            $details[$id] = [
                'id' => $id, 'quantity' => (string) $quantity, 'unit_price' => (string) $price,
                'discount_percent' => (string) $discount, 'tax_rate' => (string) $rate->toScale(3),
                'tax_treatment' => $treatment, 'group_key' => $key,
                'calculated_base_precise' => $rawBase->strippedOfTrailingZeros(), 'calculated_base' => $calculatedBase,
                'documentary_base' => $base, 'base_adjustment' => $adjustment,
                'base_adjustment_reason' => $reason, 'base_source' => array_key_exists('documentary_base', $line) ? 'documentary' : 'calculated',
                'deductible_percent' => (string) $deductible,
            ];
            $groups[$key]['rate'] = $rate;
            $groups[$key]['treatment'] = $treatment;
            $groups[$key]['bases'][$id] = $base;
        }

        $overrides = [];
        foreach ($quotaOverrides as $override) {
            if (! is_array($override) || array_diff(array_keys($override), ['group_key', 'tax_amount', 'reason'])) {
                throw new InvalidArgumentException('Unsupported VAT override fields.');
            }
            $key = $override['group_key'] ?? '';
            if (! is_string($key) || ! isset($groups[$key]) || isset($overrides[$key])) {
                throw new InvalidArgumentException('Unknown or duplicate VAT group override.');
            }
            $overrides[$key] = $override;
        }

        ksort($groups, SORT_STRING);
        $summary = [];
        $subtotal = $taxTotal = $deductibleTotal = BigDecimal::of('0.00');
        foreach ($groups as $key => $group) {
            $base = $this->sum($group['bases']);
            $rawTaxes = [];
            foreach ($group['bases'] as $id => $lineBase) {
                $rawTaxes[$id] = $lineBase->multipliedBy($group['rate'])->multipliedBy('0.01');
            }
            $calculatedTax = $this->sum($rawTaxes)->toScale(2, RoundingMode::HalfUp);
            $tax = isset($overrides[$key]) ? $this->decimal($overrides[$key]['tax_amount'] ?? null, 'tax_amount', 2)->toScale(2) : $calculatedTax;
            $adjustment = $tax->minus($calculatedTax);
            if ($adjustment->abs()->isGreaterThan('0.02') || $tax->isGreaterThan($base)
                || ($group['rate']->isZero() && ! $tax->isZero())) {
                throw new InvalidArgumentException('The documentary VAT quota requires review outside the rounding policy.');
            }
            $reason = $adjustment->isZero() ? null : $this->reason($overrides[$key]['reason'] ?? null);
            $allocated = $this->allocate($rawTaxes, $tax, $group['bases']);
            $deductionGroups = [];
            foreach ($allocated as $id => $quota) {
                $details[$id]['tax_amount'] = $quota;
                $percentage = (string) BigDecimal::of($details[$id]['deductible_percent'])->toScale(6);
                $deductionGroups[$percentage][$id] = $quota->multipliedBy($percentage)->multipliedBy('0.01');
            }
            $groupDeduction = BigDecimal::of('0.00');
            foreach ($deductionGroups as $rawDeductions) {
                $target = $this->sum($rawDeductions)->toScale(2, RoundingMode::HalfUp);
                foreach ($this->allocate($rawDeductions, $target, array_intersect_key($allocated, $rawDeductions)) as $id => $quota) {
                    $details[$id]['deductible_amount'] = $quota;
                    $details[$id]['non_deductible_amount'] = $details[$id]['tax_amount']->minus($quota);
                }
                $groupDeduction = $groupDeduction->plus($target);
            }
            $summary[] = [
                'group_key' => $key, 'tax_treatment' => $group['treatment'], 'tax_rate' => (string) $group['rate']->toScale(3),
                'taxable_base' => $base, 'calculated_tax' => $calculatedTax, 'tax_amount' => $tax,
                'tax_adjustment' => $adjustment, 'tax_adjustment_reason' => $reason,
                'quota_source' => isset($overrides[$key]) ? 'documentary' : 'calculated',
                'deductible_amount' => $groupDeduction, 'non_deductible_amount' => $tax->minus($groupDeduction),
            ];
            $subtotal = $subtotal->plus($base);
            $taxTotal = $taxTotal->plus($tax);
            $deductibleTotal = $deductibleTotal->plus($groupDeduction);
        }
        foreach ($details as &$line) {
            $line['total'] = $line['documentary_base']->plus($line['tax_amount']);
        }
        unset($line);
        $result = [
            'calculation_version' => self::VERSION, 'document_type' => $documentType, 'currency' => $currency,
            'rounding_mode' => 'HALF_UP', 'quota_tolerance_per_group' => '0.02',
            'lines' => array_values($details), 'tax_groups' => $summary,
            'totals' => ['subtotal' => $subtotal, 'tax_amount' => $taxTotal, 'deductible_amount' => $deductibleTotal,
                'non_deductible_amount' => $taxTotal->minus($deductibleTotal), 'total' => $subtotal->plus($taxTotal)],
        ];

        return $this->serialize($result, $documentType === 'credit_note');
    }

    private function decimal(mixed $value, string $field, int $scale = 6): BigDecimal
    {
        if (! is_string($value) || ! preg_match('/\A\d{1,12}(?:\.\d{1,'.$scale.'})?\z/', $value)) {
            throw new InvalidArgumentException("{$field} must be a non-negative decimal string with at most {$scale} decimals.");
        }

        return BigDecimal::of($value);
    }

    private function percent(mixed $value, string $field, int $scale = 6): BigDecimal
    {
        $number = $this->decimal($value, $field, $scale);
        if ($number->isGreaterThan('100')) {
            throw new InvalidArgumentException("{$field} exceeds 100.");
        }

        return $number;
    }

    private function reason(mixed $reason): string
    {
        if (! is_string($reason) || mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('A documentary adjustment requires a reason between 5 and 2000 characters.');
        }

        return trim($reason);
    }

    private function sum(array $amounts): BigDecimal
    {
        return array_reduce($amounts, fn (BigDecimal $sum, BigDecimal $value) => $sum->plus($value), BigDecimal::of('0.00'));
    }

    /** Allocate signed penny corrections over positive magnitudes, preserving totals. */
    private function allocate(array $raw, BigDecimal $target, array $limits): array
    {
        $allocated = array_map(fn (BigDecimal $value) => $value->toScale(2, RoundingMode::Down), $raw);
        $remaining = $target->minus($this->sum($allocated))->multipliedBy('100')->toInt();
        $ids = array_keys($raw);
        usort($ids, function ($a, $b) use ($raw, $allocated, $remaining) {
            $comparison = $raw[$a]->minus($allocated[$a])->compareTo($raw[$b]->minus($allocated[$b]));

            return ($remaining > 0 ? -$comparison : $comparison) ?: strcmp((string) $a, (string) $b);
        });
        while ($remaining !== 0) {
            $progress = false;
            foreach ($ids as $id) {
                if (($remaining > 0 && ($raw[$id]->isZero() || $allocated[$id]->isGreaterThanOrEqualTo($limits[$id]))) || ($remaining < 0 && $allocated[$id]->isZero())) {
                    continue;
                }
                $positive = $remaining > 0;
                $allocated[$id] = $allocated[$id]->plus($positive ? '0.01' : '-0.01');
                $remaining += $positive ? -1 : 1;
                $progress = true;
                if ($remaining === 0) {
                    break;
                }
            }
            if (! $progress) {
                throw new InvalidArgumentException('The documentary quota cannot be allocated to these lines.');
            }
        }

        return $allocated;
    }

    private function serialize(array $values, bool $credit): array
    {
        foreach ($values as &$value) {
            if ($value instanceof BigDecimal) {
                $value = (string) ($credit ? $value->negated() : $value);
            } elseif (is_array($value)) {
                $value = $this->serialize($value, $credit);
            }
        }

        return $values;
    }
}
