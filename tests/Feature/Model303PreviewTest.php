<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\VatReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Model303PreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_read_only_model_without_creating_returns_or_entries(): void
    {
        $this->mock(VatReport::class)->shouldReceive('make')->once()->with(2026, 3)->andReturn([
            'period' => ['year' => 2026, 'quarter' => 3], 'issued' => [], 'received' => [],
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/tax/models/303?year=2026&quarter=3')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('tax/model303')
            ->where('preview.ready', false)->where('preview.supported', true)
            ->has('preview.groups', 4)->where('preview.groups.0.base', null)
            ->where('reconciliation.ready', false)->where('reconciliation.matches', false));
        $this->assertSame(0, TaxReturn::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_rejects_invalid_periods_and_unauthorised_users(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/tax/models/303?quarter=5')->assertSessionHasErrors('quarter');
        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->get('/tax/models/303')->assertForbidden();
    }

    public function test_purchase_evidence_is_loaded_and_explained_without_changing_document(): void
    {
        $supplier = Supplier::create(['legal_name' => 'Supplier SA', 'tax_id' => 'A12345678', 'country' => 'ES']);
        $purchase = PurchaseDocument::create([
            'type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'PI-TEST',
            'supplier_number' => 'S-123', 'status' => 'registered', 'issue_date' => '2026-09-01',
            'locked_at' => now(), 'subtotal' => '3.46', 'tax_amount' => '0.34', 'amount' => '3.80', 'shipping_cost' => 0,
        ]);
        $purchase->lines()->create(['position' => 1, 'description' => 'Custom service', 'quantity' => 1,
            'unit_price' => '3.46', 'tax_percent' => 10, 'subtotal' => '3.46', 'tax_amount' => '0.34', 'total' => '3.80']);
        $before = $purchase->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/tax/models/303?year=2026&quarter=3')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.pendingReceived.0.source_id', $purchase->id)
                ->where('preview.pendingReceived.0.fiscal_issues', ['purchase_review_pending'])
                ->where('preview.pendingReceived.0.vat', 0.34)
                ->where('preview.deductions.0.vat', null));
        $this->assertSame($before, $purchase->fresh()->getAttributes());
        $this->assertSame(0, TaxReturn::count());
        $this->assertSame(0, JournalEntry::count());
    }
}
