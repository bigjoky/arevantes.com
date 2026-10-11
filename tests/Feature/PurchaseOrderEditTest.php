<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\PurchaseDocument;
use App\Models\PurchaseSetting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\PurchaseOrderEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderEditTest extends TestCase
{
    use RefreshDatabase;

    private function order(): PurchaseDocument
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        PurchaseSetting::current()->update(['order_approval_threshold' => 10000]);
        $supplier = Supplier::create(['legal_name' => 'Bet Solar', 'is_active' => true]);
        $this->post(route('purchases.store'), ['type' => 'purchase_order', 'supplier_id' => $supplier->id, 'issue_date' => '2026-10-08', 'lines' => [['description' => '2 paneles', 'quantity' => 2, 'unit_price' => 89.70, 'discount_percent' => 0, 'tax_percent' => 21]]])->assertSessionHasNoErrors();

        return PurchaseDocument::where('type', 'purchase_order')->latest('id')->firstOrFail();
    }

    private function payload(PurchaseDocument $order): array
    {
        $supplier = Supplier::firstOrCreate(['legal_name' => 'BRICOLAJE BRICOMAN, S.L.U.'], ['trade_name' => 'Obramat', 'is_active' => true]);

        return ['type' => 'purchase_order', 'revision' => app(PurchaseOrderEditor::class)->revision($order->fresh()), 'reason' => 'Proveedor y partidas incorrectos', 'supplier_id' => $supplier->id, 'issue_date' => '2026-10-08', 'shipping_cost' => 10, 'lines' => [
            ['description' => 'Tornillos', 'received_quantity' => 999, 'source_line_id' => 999, 'quantity' => 10, 'unit_price' => 1.50, 'discount_percent' => 0, 'tax_percent' => 21],
            ['description' => 'Canaleta', 'quantity' => 3.500, 'unit_price' => 20, 'discount_percent' => 10, 'tax_percent' => 21],
        ]];
    }

    public function test_order_supplier_and_multiple_lines_can_be_corrected_without_changing_identity(): void
    {
        $order = $this->order();
        $number = $order->number;
        $this->get(route('purchases.edit', $order))->assertOk()->assertInertia(fn (Assert $page) => $page->component('purchases/edit')->has('suppliers')->where('document.id', $order->id));
        $this->put(route('purchases.update', $order), $this->payload($order))->assertRedirect(route('purchases.show', $order))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame($number, $order->number);
        $this->assertSame('ordered', $order->status);
        $this->assertSame('Obramat', $order->supplier_snapshot['trade_name']);
        $this->assertSame('106.48', $order->amount);
        $this->assertSame(['Tornillos', 'Canaleta'], $order->lines()->pluck('description')->all());
        $audit = AuditLog::where('event', 'purchase_order_edited')->firstOrFail();
        $this->assertSame('2 paneles', $audit->old_values['lines'][0]['description']);
        $this->assertSame('Canaleta', $audit->new_values['lines'][1]['description']);
        $this->assertDatabaseHas('purchase_document_events', ['purchase_document_id' => $order->id, 'event' => 'order_edited']);
        $this->assertSame(0, StockMovement::count());
        $this->assertEquals(0, (float) $order->lines()->sum('received_quantity'));
        $this->assertNull($order->lines()->first()->source_line_id);
    }

    public function test_stale_order_edits_and_orders_with_receipts_are_blocked(): void
    {
        $order = $this->order();
        $stale = $this->payload($order);
        $this->put(route('purchases.update', $order), $stale)->assertSessionHasNoErrors();
        $stale['lines'][0]['unit_price'] = 999;
        $this->put(route('purchases.update', $order), $stale)->assertSessionHasErrors('reason');
        $warehouse = Warehouse::create(['code' => 'EDIT', 'name' => 'Almacén', 'is_active' => true]);
        $movement = StockMovement::create(['created_by' => auth()->id(), 'number' => 'MOV-EDIT', 'type' => 'receipt', 'posted_at' => now()]);
        GoodsReceipt::create(['purchase_document_id' => $order->id, 'stock_movement_id' => $movement->id, 'warehouse_id' => $warehouse->id, 'created_by' => auth()->id(), 'number' => 'EM-EDIT', 'received_at' => now()]);
        $this->get(route('purchases.edit', $order))->assertStatus(422);
        $this->put(route('purchases.update', $order), $this->payload($order))->assertSessionHasErrors('reason');
        $this->assertSame('106.48', $order->fresh()->amount);
    }

    public function test_editing_rechecks_approval_rules_and_invalidates_prior_approval(): void
    {
        $order = $this->order();
        $old = $order->orderApprovals()->create(['requested_by' => auth()->id(), 'status' => 'approved', 'reason' => 'Anterior', 'requested_at' => now(), 'decided_by' => auth()->id(), 'decided_at' => now()]);
        PurchaseSetting::current()->update(['order_approval_threshold' => 100]);
        $this->put(route('purchases.update', $order), $this->payload($order))->assertSessionHasNoErrors();
        $this->assertSame('pending_approval', $order->fresh()->status);
        $this->assertNull($order->fresh()->locked_at);
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame(1, $order->orderApprovals()->where('status', 'pending')->count());
    }

    public function test_edit_requires_permission_reason_and_cannot_change_document_type(): void
    {
        $order = $this->order();
        $payload = $this->payload($order);
        $payload['reason'] = '';
        $this->put(route('purchases.update', $order), $payload)->assertSessionHasErrors('reason');
        $payload['reason'] = 'Corregir partidas';
        $payload['type'] = 'purchase_rfq';
        $this->put(route('purchases.update', $order), $payload)->assertStatus(422);
        $this->actingAs(User::factory()->create(['role' => 'technician']));
        $this->get(route('purchases.edit', $order))->assertForbidden();
        $this->put(route('purchases.update', $order), $payload)->assertForbidden();
        $this->assertSame('217.07', $order->fresh()->amount);
    }
}
