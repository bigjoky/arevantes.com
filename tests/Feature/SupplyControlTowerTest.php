<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentEvent;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplyControlTowerTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_orders_are_prioritized_and_supplier_eta_changes_are_audited(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $order = $this->order($commercial);
        $order->update(['expected_delivery_date' => today()->subDays(2)]);

        $this->actingAs($technician)
            ->get(route('supply-control.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchases/control-tower/index')
                ->where('counts.open', 1)
                ->where('counts.late', 1)
                ->where('counts.unconfirmed', 1)
                ->where('counts.unassigned', 0)
                ->where('orders.0.priority', 'late')
                ->where('orders.0.days_late', 2)
                ->where('orders.0.receipt_progress', 0)
                ->where('orders.0.assignee.id', $commercial->id));

        $this->actingAs($commercial)->patch(route('supply-control.assign', $order), [
            'assigned_to' => $technician->id,
            'next_follow_up_date' => today()->addDay()->toDateString(),
            'note' => 'Coordinate the revised delivery with the site team.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame($technician->id, $order->assigned_to);
        $this->assertSame($commercial->id, $order->assigned_by);
        $this->assertDatabaseHas('purchase_document_events', [
            'purchase_document_id' => $order->id,
            'event' => 'supply_action_assigned',
        ]);

        $this->actingAs($technician)->post(route('supply-control.confirm', $order), [
            'confirmed_delivery_date' => today()->addDays(3)->toDateString(),
            'confirmation_reference' => 'SUP-ACK-42',
            'note' => 'Supplier confirmed by email',
            'next_follow_up_date' => today()->addDays(2)->toDateString(),
        ])->assertForbidden();

        $this->actingAs($commercial)->post(route('supply-control.confirm', $order), [
            'confirmed_delivery_date' => today()->addDays(3)->toDateString(),
            'confirmation_reference' => 'SUP-ACK-42',
            'note' => 'Supplier confirmed by email',
            'next_follow_up_date' => today()->addDays(2)->toDateString(),
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(today()->addDays(3)->toDateString(), $order->supplier_confirmed_delivery_date->toDateString());
        $this->assertSame($commercial->id, $order->supplier_confirmed_by);
        $this->assertSame('SUP-ACK-42', $order->supplier_confirmation_reference);
        $this->assertSame(today()->addDays(2)->toDateString(), $order->next_follow_up_date->toDateString());
        $this->assertDatabaseHas('purchase_document_events', [
            'purchase_document_id' => $order->id,
            'event' => 'supplier_delivery_confirmed',
        ]);
        $this->get(route('supply-control.index'))->assertInertia(fn (Assert $page) => $page
            ->where('counts.late', 0)
            ->where('counts.unconfirmed', 0)
            ->where('counts.due_soon', 1)
            ->where('orders.0.priority', 'due_soon'));
        $this->get(route('purchases.index'))->assertInertia(fn (Assert $page) => $page
            ->where('attention.late', 0));

        $this->post(route('supply-control.follow-up', $order), [
            'confirmed_delivery_date' => today()->addDays(10)->toDateString(),
            'confirmation_reference' => 'SUP-ACK-43',
            'note' => 'Supplier revised the dispatch plan',
            'next_follow_up_date' => today()->toDateString(),
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(today()->addDays(10)->toDateString(), $order->supplier_confirmed_delivery_date->toDateString());
        $this->assertSame('Supplier revised the dispatch plan', $order->last_follow_up_note);
        $this->assertSame(today()->toDateString(), $order->next_follow_up_date->toDateString());
        $event = PurchaseDocumentEvent::where('event', 'supplier_followed_up')->firstOrFail();
        $this->assertSame(today()->addDays(3)->toDateString(), $event->metadata['from']);
        $this->assertSame(today()->addDays(10)->toDateString(), $event->metadata['to']);

        $this->get(route('supply-control.index'))->assertInertia(fn (Assert $page) => $page
            ->where('counts.follow_up_due', 1)
            ->where('orders.0.priority', 'follow_up_due'));
        $this->getJson(route('action-center'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('groups.0.label', 'Supply')
            ->assertJsonPath('groups.0.items.0.id', 'supply-'.$order->id)
            ->assertJsonPath('groups.0.items.0.href', '/purchases/'.$order->id)
            ->assertJsonPath('groups.0.items.0.subtitle', fn ($subtitle) => str_contains($subtitle, 'Owner: '.$technician->name));
    }

    private function order(User $user): PurchaseDocument
    {
        $supplier = Supplier::create([
            'legal_name' => 'Tracked Supply SL',
            'email' => 'orders@example.com',
            'country' => 'ES',
            'is_active' => true,
        ]);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'TRACKED-PANEL',
            'name' => 'Tracked panel',
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
            'expected_delivery_date' => today()->addDays(5)->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Tracked panel',
                'quantity' => 5,
                'unit_price' => 80,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        return PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
    }
}
