<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerializedStockCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_serial_audit_marks_missing_adds_unexpected_and_can_recover_a_missing_serial(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $warehouse = Warehouse::create(['code' => 'SER-AUDIT', 'name' => 'Serial audit warehouse', 'is_active' => true]);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'SER-AUDIT-PRODUCT',
            'name' => 'Serialized inverter',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 1000,
            'cost_price' => 500,
            'track_stock' => true,
            'track_serials' => true,
            'is_active' => true,
        ]);
        $this->actingAs($user)->post(route('inventory.movements.store'), [
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_cost' => 500,
                'serials' => "SER-001\nSER-002",
            ]],
        ])->assertRedirect();

        $this->post(route('stock-counts.store'), [
            'warehouse_id' => $warehouse->id,
            'mode' => 'serial',
            'notes' => 'Full serial scan',
        ])->assertRedirect();
        $count = StockCount::firstOrFail();
        $line = $count->lines()->firstOrFail();
        $this->assertSame('serial', $count->mode);
        $this->assertSame(['SER-001', 'SER-002'], $line->snapshot_serials);

        $this->patch(route('stock-counts.update', $count), [
            'serials' => [$line->id => "SER-001\nSER-001"],
        ])->assertSessionHasErrors('serials');
        $this->patch(route('stock-counts.update', $count), [
            'serials' => [$line->id => "SER-001\nSER-003"],
        ])->assertRedirect();
        $this->post(route('stock-counts.post', $count))->assertRedirect();

        $line->refresh();
        $this->assertSame('0.000', $line->difference_quantity);
        $this->assertSame(2, $line->discrepancy_count);
        $this->assertSame(['SER-002'], $line->missing_serials);
        $this->assertSame(['SER-003'], $line->unexpected_serials);
        $this->assertDatabaseHas('serial_numbers', ['serial_number' => 'SER-002', 'status' => 'missing', 'warehouse_id' => null]);
        $this->assertDatabaseHas('serial_numbers', ['serial_number' => 'SER-003', 'status' => 'available', 'warehouse_id' => $warehouse->id]);
        $movement = StockMovement::findOrFail($count->refresh()->stock_movement_id);
        $this->assertSame(2, $movement->lines()->count());
        $this->assertSame(0.0, (float) $movement->lines()->sum('quantity'));
        $this->assertSame(2.0, (float) StockMovementLine::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->sum('quantity'));

        $this->post(route('stock-counts.store'), [
            'warehouse_id' => $warehouse->id,
            'mode' => 'serial',
        ])->assertRedirect();
        $recovery = StockCount::whereKeyNot($count->id)->firstOrFail();
        $recoveryLine = $recovery->lines()->firstOrFail();
        $this->patch(route('stock-counts.update', $recovery), [
            'serials' => [$recoveryLine->id => "SER-001\nSER-002\nSER-003"],
        ])->assertRedirect();
        $this->post(route('stock-counts.post', $recovery))->assertRedirect();

        $recovered = SerialNumber::where('serial_number', 'SER-002')->firstOrFail();
        $this->assertSame('available', $recovered->status);
        $this->assertSame($warehouse->id, $recovered->warehouse_id);
        $this->assertSame(3.0, (float) StockMovementLine::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->sum('quantity'));
    }
}
