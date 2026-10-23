<?php

namespace Tests\Unit;

use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentLine;
use App\Services\Tax\PurchaseFiscalEvidence;
use Tests\TestCase;

class PurchaseFiscalEvidenceTest extends TestCase
{
    private function invoice(array $attributes = [], array $lines = []): PurchaseDocument
    {
        $document = new PurchaseDocument([
            'type' => 'supplier_invoice', 'status' => 'registered', 'supplier_number' => 'S-123',
            'supplier_snapshot' => ['legal_name' => 'Supplier SA', 'tax_id' => 'A12345678', 'country' => 'ES'],
            'locked_at' => '2026-09-01 12:00:00', 'subtotal' => '3.46', 'tax_amount' => '0.34',
            'amount' => '3.80', 'shipping_cost' => '0.00', ...$attributes,
        ]);
        $document->setRelation('lines', collect($lines ?: [new PurchaseDocumentLine([
            'product_id' => null, 'description' => 'Custom service', 'tax_percent' => '10.00',
            'subtotal' => '3.46', 'tax_amount' => '0.34', 'total' => '3.80',
        ])]));

        return $document;
    }

    public function test_accepts_exact_documentary_rounding_and_custom_lines_without_classifying(): void
    {
        $document = $this->invoice();
        $before = $document->getAttributes();
        $this->assertSame(['purchase_review_pending'], (new PurchaseFiscalEvidence)->issues($document));
        $this->assertSame($before, $document->getAttributes());
    }

    public function test_reports_shipping_and_residual_totals_without_assuming_a_rate(): void
    {
        $issues = (new PurchaseFiscalEvidence)->issues($this->invoice(['shipping_cost' => '10.00', 'amount' => '15.90']));
        $this->assertContains('purchase_shipping_pending', $issues);
        $this->assertContains('purchase_totals_mismatch', $issues);
        $this->assertContains('purchase_totals_mismatch', (new PurchaseFiscalEvidence)->issues($this->invoice(['tax_amount' => '0.35', 'amount' => '3.81'])));
    }

    public function test_requires_retained_identity_and_registered_invoice(): void
    {
        $issues = (new PurchaseFiscalEvidence)->issues($this->invoice([
            'supplier_snapshot' => [], 'supplier_number' => null, 'locked_at' => null, 'status' => 'review_required',
        ]));
        $this->assertContains('purchase_identity_missing', $issues);
        $this->assertContains('purchase_territory_pending', $issues);
        $this->assertContains('purchase_registration_pending', $issues);
    }

    public function test_reports_rectifications_and_unsupported_lines(): void
    {
        $issues = (new PurchaseFiscalEvidence)->issues($this->invoice(['type' => 'supplier_credit_note'], [new PurchaseDocumentLine([
            'tax_percent' => 0, 'subtotal' => 100, 'tax_amount' => 0, 'total' => 100,
        ])]));
        $this->assertContains('purchase_rectification_pending', $issues);
        $this->assertContains('purchase_lines_pending', $issues);
        $empty = $this->invoice();
        $empty->setRelation('lines', collect());
        $this->assertContains('purchase_lines_pending', (new PurchaseFiscalEvidence)->issues($empty));
    }

    public function test_payments_and_stock_receipts_do_not_change_documentary_diagnostics(): void
    {
        $document = $this->invoice();
        $service = new PurchaseFiscalEvidence;
        $before = $service->issues($document);
        $document->paid_amount = '3.80';
        $document->status = 'paid';
        $document->lines->first()->received_quantity = 1;
        $this->assertSame($before, $service->issues($document));
    }
}
