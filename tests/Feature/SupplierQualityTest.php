<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierQualityIncident;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Supply\SupplierScorecards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_accepts_stock_and_records_rejected_goods_without_stocking_them(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        [$supplier, $product, $warehouse, $order] = $this->order($user, 3);
        $line = $order->lines()->firstOrFail();

        $this->actingAs($user)->post(route('purchases.receive', $order), [
            'warehouse_id' => $warehouse->id,
            'supplier_delivery_note' => 'DN-QUALITY-1',
            'quantities' => [$line->id => 2],
            'rejected_quantities' => [$line->id => 1],
            'rejection_reasons' => [$line->id => 'damaged'],
            'rejection_severities' => [$line->id => 'major'],
            'rejection_notes' => [$line->id => 'Broken frame'],
        ])->assertRedirect();

        $receipt = GoodsReceipt::firstOrFail();
        $incident = SupplierQualityIncident::firstOrFail();
        $this->assertSame($receipt->id, $incident->goods_receipt_id);
        $this->assertSame('2.000', $line->refresh()->received_quantity);
        $this->assertSame('1.000', $incident->rejected_quantity);
        $this->assertSame('partially_received', $order->refresh()->status);
        $this->assertSame(2.0, (float) StockMovement::firstOrFail()->lines()->sum('quantity'));
        $this->assertDatabaseHas('purchase_document_events', ['purchase_document_id' => $order->id, 'event' => 'quality_incident_reported']);

        $scorecard = app(SupplierScorecards::class)->for($supplier);
        $this->assertSame(33.33, $scorecard['rejection_rate']);
        $this->assertSame(66.7, $scorecard['quality_score']);
        $this->assertSame(1, $scorecard['open_quality_incidents']);
    }

    public function test_rejected_only_delivery_creates_a_claim_without_a_receipt_and_can_be_resolved(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $commercial = User::factory()->create(['role' => 'commercial']);
        [, , $warehouse, $order] = $this->order($commercial, 2);
        $line = $order->lines()->firstOrFail();

        $this->actingAs($technician)->post(route('purchases.receive', $order), [
            'warehouse_id' => $warehouse->id,
            'rejected_quantities' => [$line->id => 2],
            'rejection_reasons' => [$line->id => 'wrong_item'],
            'rejection_severities' => [$line->id => 'critical'],
        ])->assertRedirect();

        $incident = SupplierQualityIncident::firstOrFail();
        $this->assertNull($incident->goods_receipt_id);
        $this->assertSame(0, GoodsReceipt::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame('ordered', $order->refresh()->status);
        $this->actingAs($technician)
            ->get(route('supplier-quality.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchases/quality/index')
                ->where('counts.open', 1)
                ->where('incidents.data.0.severity', 'critical'));
        $this->post(route('supplier-quality.resolve', $incident), [
            'resolution' => 'replacement',
            'resolution_notes' => 'Replacement promised for next delivery',
        ])->assertForbidden();

        $this->actingAs($commercial)->post(route('supplier-quality.resolve', $incident), [
            'resolution' => 'replacement',
            'resolution_notes' => 'Replacement promised for next delivery',
        ])->assertRedirect();
        $this->assertDatabaseHas('supplier_quality_incidents', [
            'id' => $incident->id,
            'status' => 'resolved',
            'resolution' => 'replacement',
            'resolved_by' => $commercial->id,
        ]);

        $this->post(route('supplier-quality.reopen', $incident))->assertRedirect();
        $this->assertDatabaseHas('supplier_quality_incidents', [
            'id' => $incident->id,
            'status' => 'open',
            'resolution' => null,
            'resolved_by' => null,
        ]);
    }

    private function order(User $user, float $quantity): array
    {
        $supplier = Supplier::create(['legal_name' => 'Quality Supply SL', 'country' => 'ES', 'is_active' => true]);
        $warehouse = Warehouse::create(['code' => 'QUALITY', 'name' => 'Quality warehouse', 'is_active' => true]);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'QUALITY-PANEL',
            'name' => 'Quality panel',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 150,
            'cost_price' => 80,
            'track_stock' => true,
            'track_serials' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('purchases.store'), [
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'issue_date' => today()->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Quality panel',
                'quantity' => $quantity,
                'unit_price' => 80,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        return [$supplier, $product, $warehouse, PurchaseDocument::where('type', 'purchase_order')->firstOrFail()];
    }
}
