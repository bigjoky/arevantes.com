<?php

namespace App\Services\Tax;

use App\Models\Expense;
use App\Models\JournalLine;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VatReport
{
    public function make(int $year, ?int $quarter = null): array
    {
        [$from, $to] = $this->period($year, $quarter);
        $issued = $this->issued($from, $to);
        $received = $this->received($from, $to);
        $output = round((float) $issued->sum('vat'), 2);
        $input = round((float) $received->sum('vat'), 2);
        $ledgerOutput = $this->ledgerVat('477', $from, $to, true);
        $ledgerInput = $this->ledgerVat('472', $from, $to, false);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'year' => $year, 'quarter' => $quarter],
            'issued' => $issued->values(), 'received' => $received->values(),
            'summary' => [
                'taxableBaseIssued' => round((float) $issued->sum('base'), 2), 'outputVat' => $output,
                'taxableBaseReceived' => round((float) $received->sum('base'), 2), 'deductibleVat' => $input,
                'result' => round($output - $input, 2),
            ],
            'rates' => $issued->concat($received)->groupBy('rate')->map(fn ($rows, $rate) => [
                'rate' => (float) $rate,
                'issuedBase' => round((float) $rows->where('direction', 'issued')->sum('base'), 2),
                'outputVat' => round((float) $rows->where('direction', 'issued')->sum('vat'), 2),
                'receivedBase' => round((float) $rows->where('direction', 'received')->sum('base'), 2),
                'inputVat' => round((float) $rows->where('direction', 'received')->sum('vat'), 2),
            ])->sortBy('rate')->values(),
            'accountingCheck' => [
                'outputVat' => $ledgerOutput, 'inputVat' => $ledgerInput,
                'outputDifference' => round($output - $ledgerOutput, 2), 'inputDifference' => round($input - $ledgerInput, 2),
                'matches' => abs($output - $ledgerOutput) < 0.01 && abs($input - $ledgerInput) < 0.01,
            ],
        ];
    }

    private function issued(Carbon $from, Carbon $to): Collection
    {
        return SalesDocument::with('lines')->whereIn('type', ['invoice', 'credit_note'])->whereNotNull('locked_at')->whereBetween('issue_date', [$from, $to])->orderBy('issue_date')->get()->flatMap(function ($document) {
            $snapshot = $document->customer_snapshot ?? [];
            $party = ($snapshot['company_name'] ?? '') ?: trim(($snapshot['first_name'] ?? '').' '.($snapshot['last_name'] ?? ''));
            $sign = $document->type === 'credit_note' ? -1 : 1;
            $groups = $document->lines->groupBy(fn ($line) => number_format((float) $line->tax_percent, 2, '.', ''));
            if ($groups->isEmpty()) {
                $groups = collect(['0.00' => collect()]);
            }

            return $groups->map(function ($lines, $rate) use ($document, $snapshot, $party, $sign) {
                $base = $lines->isEmpty() ? (float) $document->subtotal - (float) $document->discount_amount : $lines->sum(fn ($line) => (float) $line->subtotal - (float) $line->discount_amount);
                $vat = $lines->isEmpty() ? (float) $document->tax_amount : $lines->sum('tax_amount');

                return $this->row('issued', $document->issue_date, $document->number, $party ?: __('Unknown customer'), $snapshot['tax_id'] ?? $snapshot['document_number'] ?? null, (float) $rate, $sign * abs($base), $sign * abs($vat), $sign * (abs($base) + abs($vat)), 'sales_document', $document->id);
            });
        });
    }

    private function received(Carbon $from, Carbon $to): Collection
    {
        $purchases = PurchaseDocument::with(['lines', 'supplier'])->whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNot('status', 'review_required')->whereBetween('issue_date', [$from, $to])->orderBy('issue_date')->get()->flatMap(function ($document) {
            $groups = $document->lines->groupBy(fn ($line) => number_format((float) $line->tax_percent, 2, '.', ''));
            $rows = $groups->map(function ($lines, $rate) use ($document) {
                $base = (float) $lines->sum('subtotal');
                $vat = (float) $lines->sum('tax_amount');

                return $this->row('received', $document->issue_date, $document->supplier_number ?: $document->number, $document->supplier?->legal_name ?: __('Unknown supplier'), $document->supplier?->tax_id, (float) $rate, $base, $vat, $base + $vat, 'purchase_document', $document->id);
            });
            $shippingBase = (float) $document->shipping_cost;
            $shippingVat = round($shippingBase * 0.21, 2);
            $unallocatedBase = round((float) $document->subtotal - (float) $document->lines->sum('subtotal') - $shippingBase, 2);
            $unallocatedVat = round((float) $document->tax_amount - (float) $document->lines->sum('tax_amount') - $shippingVat, 2);

            if (abs($unallocatedBase) > 0.005 || abs($unallocatedVat) > 0.005) {
                $rate = (float) $this->effectiveRate($unallocatedBase, $unallocatedVat);
                $rows->push($this->row('received', $document->issue_date, $document->supplier_number ?: $document->number, $document->supplier?->legal_name ?: __('Unknown supplier'), $document->supplier?->tax_id, $rate, $unallocatedBase, $unallocatedVat, $unallocatedBase + $unallocatedVat, 'purchase_document', $document->id));
            }
            if (abs($shippingBase) > 0.005) {
                $rows->push($this->row('received', $document->issue_date, $document->supplier_number ?: $document->number, $document->supplier?->legal_name ?: __('Unknown supplier'), $document->supplier?->tax_id, 21, $shippingBase, $shippingVat, $shippingBase + $shippingVat, 'purchase_document', $document->id));
            }

            return $rows;
        });
        $expenses = Expense::with(['supplier', 'taxLines'])->whereBetween('expense_date', [$from, $to])->orderBy('expense_date')->get()->flatMap(function ($expense) {
            $party = $expense->issuer_legal_name ?: $expense->supplier?->legal_name ?: ($expense->merchant_name ?: __('Unknown merchant'));
            $taxId = $expense->issuer_tax_id ?: $expense->supplier?->tax_id;
            if ($expense->taxLines->isEmpty()) {
                return [$this->row('received', $expense->expense_date, $expense->document_number ?: $expense->number, $party, $taxId, (float) $this->effectiveRate($expense->subtotal, $expense->tax_amount), (float) $expense->subtotal, (float) $expense->tax_amount, (float) $expense->subtotal + (float) $expense->tax_amount, 'expense', $expense->id)];
            }

            return $expense->taxLines->where('kind', 'vat')->map(fn ($line) => $this->row(
                'received', $expense->expense_date, $expense->document_number ?: $expense->number,
                $party, $taxId, (float) $line->tax_rate, (float) $line->taxable_base,
                (float) $line->deductible_amount, (float) $line->taxable_base + (float) $line->tax_amount,
                'expense', $expense->id,
            ));
        });

        return $purchases->concat($expenses)->sortBy('date')->values();
    }

    private function row(string $direction, CarbonInterface $date, string $number, string $party, ?string $taxId, float $rate, float $base, float $vat, float $total, string $sourceType, int $sourceId): array
    {
        return ['direction' => $direction, 'date' => $date->toDateString(), 'number' => $number, 'party' => $party, 'tax_id' => $taxId, 'rate' => round($rate, 2), 'base' => round($base, 2), 'vat' => round($vat, 2), 'total' => round($total, 2), 'source_type' => $sourceType, 'source_id' => $sourceId];
    }

    private function effectiveRate(mixed $base, mixed $vat): string
    {
        return number_format((float) $base !== 0.0 ? (float) $vat / (float) $base * 100 : 0, 2, '.', '');
    }

    private function period(int $year, ?int $quarter): array
    {
        $from = Carbon::create($year, $quarter ? (($quarter - 1) * 3) + 1 : 1, 1)->startOfDay();

        return [$from, $quarter ? $from->copy()->addMonths(3)->subDay()->endOfDay() : $from->copy()->endOfYear()];
    }

    private function ledgerVat(string $code, Carbon $from, Carbon $to, bool $creditNature): float
    {
        $line = JournalLine::whereHas('account', fn ($q) => $q->where('code', $code))->whereHas('entry', fn ($q) => $q->whereBetween('entry_date', [$from, $to])->whereNotIn('entry_kind', ['closing', 'opening', 'tax_settlement']))->selectRaw('coalesce(sum(debit),0) debit, coalesce(sum(credit),0) credit')->first();

        return round($creditNature ? (float) $line->credit - (float) $line->debit : (float) $line->debit - (float) $line->credit, 2);
    }
}
