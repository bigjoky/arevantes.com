<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\PurchaseFiscalReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseFiscalReviewTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): PurchaseDocument
    {
        $supplier = Supplier::create(['legal_name' => 'Supplier SA', 'tax_id' => 'A12345678', 'country' => 'ES']);
        $invoice = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id,
            'number' => 'PI-FISCAL', 'supplier_number' => 'S-123', 'status' => 'registered', 'issue_date' => '2026-09-01',
            'locked_at' => now(), 'subtotal' => '3.46', 'tax_amount' => '0.34', 'amount' => '3.80', 'shipping_cost' => 0]);
        $invoice->lines()->create(['position' => 1, 'description' => 'Custom service', 'quantity' => 1,
            'unit_price' => '3.46', 'tax_percent' => 10, 'subtotal' => '3.46', 'tax_amount' => '0.34', 'total' => '3.80']);
        FiscalYear::updateOrCreate(['year' => 2026], ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        return $invoice->fresh();
    }

    private function data(PurchaseDocument $invoice, array $overrides = []): array
    {
        return ['revision' => app(PurchaseFiscalReview::class)->revision($invoice->fresh()),
            'operation_class' => 'domestic_current', 'reason' => 'Invoice checked', 'source_reference' => 'Invoice S-123 original',
            'confirmed' => true, ...$overrides];
    }

    private function save(PurchaseDocument $invoice, array $data): TestResponse
    {
        return $this->post(route('tax.purchases.classification.update', $invoice), $data);
    }

    public function test_audited_review_preserves_invoice_and_projects_documentary_cents(): void
    {
        $invoice = $this->invoice();
        $before = $invoice->getAttributes();
        $lines = $invoice->lines()->get()->toArray();
        $this->get(route('tax.purchases.classification.show', $invoice))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('tax/purchase-classification')->where('current', false));
        $this->save($invoice, $this->data($invoice))->assertRedirect()->assertSessionHasNoErrors();
        $review = $invoice->fresh()->fiscalClassification;
        $this->assertSame(auth()->id(), $review->reviewed_by);
        $this->assertSame('Invoice S-123 original', $review->source_reference);
        $this->assertTrue($review->evidence_snapshot['confirmation']['eur_general_regime_full_deduction']);
        $this->assertSame(1, AuditLog::where('event', 'purchase_fiscal_classified')->count());
        $this->assertSame($before, $invoice->fresh()->getAttributes());
        $this->assertSame($lines, $invoice->lines()->get()->toArray());
        $this->get('/tax/models/303?year=2026&quarter=3')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('preview.deductions.0.base', 3.46)->where('preview.deductions.0.vat', 0.34)
            ->has('preview.deductions.0.documents', 1)->has('preview.pendingReceived', 0)->where('preview.ready', false));
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(0, TaxReturn::count());
    }

    public function test_stale_forms_and_changed_evidence_are_rejected(): void
    {
        $invoice = $this->invoice();
        $stale = $this->data($invoice);
        $this->save($invoice, $stale)->assertSessionHasNoErrors();
        $this->save($invoice, $stale)->assertSessionHasErrors('operation_class');
        $next = $this->data($invoice);
        $invoice->lines()->first()->update(['description' => 'Changed fiscal line']);
        $this->assertFalse(app(PurchaseFiscalReview::class)->isCurrent($invoice->fresh()));
        $this->save($invoice, $next)->assertSessionHasErrors('operation_class');
    }

    public function test_payments_and_stock_updates_preserve_review_but_withdrawal_retains_history(): void
    {
        $invoice = $this->invoice();
        $this->save($invoice, $this->data($invoice))->assertSessionHasNoErrors();
        $invoice->update(['paid_amount' => '3.80', 'status' => 'paid']);
        $invoice->lines()->first()->update(['received_quantity' => 1]);
        $this->assertTrue(app(PurchaseFiscalReview::class)->isCurrent($invoice->fresh()));
        $this->save($invoice, $this->data($invoice, ['operation_class' => 'pending', 'confirmed' => false]))->assertSessionHasNoErrors();
        $this->assertFalse(app(PurchaseFiscalReview::class)->isCurrent($invoice->fresh()));
        $this->assertSame(2, AuditLog::where('event', 'purchase_fiscal_classified')->count());
    }

    public function test_requires_confirmation_reference_and_exact_supported_breakdown(): void
    {
        $invoice = $this->invoice();
        $this->save($invoice, $this->data($invoice, ['confirmed' => false]))->assertSessionHasErrors('operation_class');
        $this->save($invoice, $this->data($invoice, ['source_reference' => '']))->assertSessionHasErrors('source_reference');
        $invoice->update(['shipping_cost' => 1]);
        $this->save($invoice, $this->data($invoice))->assertSessionHasErrors('operation_class');
        $invoice->update(['shipping_cost' => 0, 'tax_amount' => '0.35']);
        $this->save($invoice, $this->data($invoice))->assertSessionHasErrors('operation_class');
        $this->assertNull($invoice->fresh()->fiscalClassification);
    }

    public function test_closed_year_month_and_prepared_return_block_changes(): void
    {
        $invoice = $this->invoice();
        FiscalYear::where('year', 2026)->update(['status' => 'closed']);
        $this->save($invoice, $this->data($invoice))->assertSessionHasErrors('operation_class');
        FiscalYear::where('year', 2026)->update(['status' => 'open']);
        AccountingPeriod::updateOrCreate(['year' => 2026, 'month' => 9], ['fiscal_year_id' => FiscalYear::where('year', 2026)->firstOrFail()->id, 'status' => 'closed']);
        $this->save($invoice, $this->data($invoice))->assertSessionHasErrors('accounting_period');
        AccountingPeriod::where(['year' => 2026, 'month' => 9])->update(['status' => 'open']);
        TaxReturn::create(['tax_type' => 'vat', 'year' => 2026, 'quarter' => 3,
            'period_from' => '2026-07-01', 'period_to' => '2026-09-30', 'status' => 'prepared',
            'result' => 0, 'output_vat' => 0, 'input_vat' => 0, 'snapshot' => [], 'prepared_at' => now()]);
        $this->save($invoice, $this->data($invoice))->assertSessionHasErrors('operation_class');
    }

    public function test_non_administrators_cannot_review_and_investment_has_separate_boxes(): void
    {
        $invoice = $this->invoice();
        $this->save($invoice, $this->data($invoice, ['operation_class' => 'domestic_investment']))->assertSessionHasNoErrors();
        $this->get('/tax/models/303?year=2026&quarter=3')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('preview.deductions.0.vat', null)->where('preview.deductions.1.vat', 0.34));
        $this->actingAs(User::factory()->create(['role' => 'sales']));
        $this->get(route('tax.purchases.classification.show', $invoice))->assertForbidden();
        $this->save($invoice, $this->data($invoice))->assertForbidden();
    }
}
