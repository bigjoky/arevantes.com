<?php

namespace App\Services\Tax;

use App\Models\PurchaseDocument;

/** Documentary diagnostics only: never classifies or changes the compatibility VAT book. */
class PurchaseFiscalEvidence
{
    public function issues(PurchaseDocument $document): array
    {
        $issues = ['purchase_review_pending'];
        if ($document->type !== 'supplier_invoice') {
            $issues[] = 'purchase_rectification_pending';
        }
        if (! $document->locked_at || in_array($document->status, ['review_required', 'cancelled'], true)) {
            $issues[] = 'purchase_registration_pending';
        }
        $snapshot = $document->supplier_snapshot ?? [];
        if (! trim((string) ($snapshot['legal_name'] ?? '')) || ! trim((string) ($snapshot['tax_id'] ?? ''))
            || ! trim((string) $document->supplier_number)) {
            $issues[] = 'purchase_identity_missing';
        }
        if (strtoupper(trim((string) ($snapshot['country'] ?? ''))) !== 'ES') {
            $issues[] = 'purchase_territory_pending';
        }
        if ($this->cents($document->shipping_cost) !== 0) {
            $issues[] = 'purchase_shipping_pending';
        }

        $document->loadMissing('lines');
        $base = $vat = $total = 0;
        $invalidLines = $document->lines->isEmpty();
        foreach ($document->lines as $line) {
            $lineBase = $this->cents($line->subtotal);
            $lineVat = $this->cents($line->tax_amount);
            $lineTotal = $this->cents($line->total);
            $base += $lineBase;
            $vat += $lineVat;
            $total += $lineTotal;
            // Free/custom lines are evidence too; a stock product is not required.
            if ($lineBase <= 0 || $lineVat <= 0 || $lineBase + $lineVat !== $lineTotal
                || ! in_array((string) $line->tax_percent, ['4.00', '10.00', '21.00'], true)) {
                $invalidLines = true;
            }
        }
        if ($invalidLines) {
            $issues[] = 'purchase_lines_pending';
        }
        // No reconstruction of residual bases, shipping VAT or supplier rounding.
        if ($base !== $this->cents($document->subtotal) || $vat !== $this->cents($document->tax_amount)
            || $total !== $this->cents($document->amount)) {
            $issues[] = 'purchase_totals_mismatch';
        }

        return $issues;
    }

    private function cents(mixed $value): int
    {
        // Persisted monetary columns are decimal(…,2), not unrounded calculations.
        $value = (string) ($value ?? '0.00');
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $cents = (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }
}
