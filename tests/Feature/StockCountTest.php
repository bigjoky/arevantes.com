<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_physical_count_posts_one_adjustment_and_reconciles_stock(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $warehouse = Warehouse::create(['code' => 'COUNT', 'name' => 'Count warehouse', 'is_active' => true]);
        $product = $this->product('COUNTED', false);
        $unchecked = $this->product('UNCHECKED', false);
        $this->product('SERIALIZED', true);
        $this->actingAs($commercial)->post(route('inventory.movements.store'), [
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 5]],
        ])->assertRedirect();

        $this->actingAs($technician)->post(route('stock-counts.store'), [
            'warehouse_id' => $warehouse->id,
            'mode' => 'quantity',
        ])->assertForbidden();
        $this->actingAs($commercial)->post(route('stock-counts.store'), [
            'warehouse_id' => $warehouse->id,
            'mode' => 'quantity',
            'notes' => 'Aisle A',
        ])->assertRedirect();

        $count = StockCount::firstOrFail();
        $this->assertSame(2, $count->lines()->count());
        $line = $count->lines()->where('product_id', $product->id)->firstOrFail();
        $uncheckedLine = $count->lines()->where('product_id', $unchecked->id)->firstOrFail();
        $this->assertSame($product->id, $line->product_id);
        $this->assertSame('10.000', $line->system_quantity);
        $this->get(route('stock-counts.show', $count))->assertInertia(fn (Assert $page) => $page
            ->component('inventory/counts/show')
            ->where('count.lines.0.system_quantity', '10.000'));

        $this->patch(route('stock-counts.update', $count), [
            'quantities' => [$line->id => 7, $uncheckedLine->id => ''],
        ])->assertRedirect();
        $this->assertNull($uncheckedLine->refresh()->counted_quantity);
        $this->post(route('stock-counts.post', $count))->assertSessionHasErrors('quantities');
        $this->patch(route('stock-counts.update', $count), [
            'quantities' => [$uncheckedLine->id => 0],
        ])->assertRedirect();
        $this->post(route('stock-counts.post', $count))->assertRedirect();

        $count->refresh();
        $this->assertSame('posted', $count->status);
        $this->assertNotNull($count->stock_movement_id);
        $this->assertSame('-3.000', $line->refresh()->difference_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'id' => $count->stock_movement_id,
            'type' => 'adjustment',
            'reference' => $count->number,
        ]);
        $this->assertSame(7.0, (float) StockMovementLine::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->sum('quantity'));
        $this->post(route('stock-counts.post', $count))->assertStatus(422);
    }

    public function test_count_is_not_posted_when_stock_changed_after_snapshot(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $warehouse = Warehouse::create(['code' => 'STALE', 'name' => 'Stale warehouse', 'is_active' => true]);
        $product = $this->product('STALE-PRODUCT', false);
        $this->actingAs($user)->post(route('inventory.movements.store'), [
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 5]],
        ])->assertRedirect();
        $this->post(route('stock-counts.store'), ['warehouse_id' => $warehouse->id, 'mode' => 'quantity'])->assertRedirect();
        $count = StockCount::firstOrFail();
        $line = $count->lines()->firstOrFail();
        $this->patch(route('stock-counts.update', $count), ['quantities' => [$line->id => 8]])->assertRedirect();

        $this->post(route('inventory.movements.store'), [
            'type' => 'adjustment',
            'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 5]],
        ])->assertRedirect();
        $this->post(route('stock-counts.post', $count))->assertSessionHasErrors('quantities');

        $this->assertSame('draft', $count->refresh()->status);
        $this->assertNull($count->stock_movement_id);
        $this->assertSame(2, StockMovement::count());
        $this->assertSame(11.0, (float) StockMovementLine::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->sum('quantity'));
        $this->delete(route('stock-counts.destroy', $count))->assertRedirect(route('stock-counts.index'));
        $this->assertDatabaseMissing('stock_counts', ['id' => $count->id]);
    }

    private function product(string $sku, bool $serialized): Product
    {
        return Product::create([
            'type' => 'product',
            'sku' => $sku,
            'name' => $sku,
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 10,
            'cost_price' => 5,
            'track_stock' => true,
            'track_serials' => $serialized,
            'is_active' => true,
        ]);
    }
}
