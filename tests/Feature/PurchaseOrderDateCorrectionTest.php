<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\FiscalYear;
use App\Models\PurchaseDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class PurchaseOrderDateCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function order(): PurchaseDocument
    {
        $supplier = EconomicFixture::supplier(['legal_name' => 'Historical supplier', 'country' => 'ES']);

        return EconomicFixture::purchase(['type' => 'purchase_order', 'supplier_id' => $supplier->id, 'number' => 'PO-2026-HISTORY', 'status' => 'ordered', 'issue_date' => '2026-09-20', 'expected_delivery_date' => '2026-09-30', 'locked_at' => now(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121]);
    }

    private function payload(array $overrides = []): array
    {
        return [...['issue_date' => '2026-05-10', 'expected_delivery_date' => '2026-05-15', 'reason' => 'Historical purchase entered with today by mistake'], ...$overrides];
    }

    public function test_admin_can_correct_the_reported_september_order_to_may_without_changing_delivery(): void
    {
        $order = $this->order();
        $order->update(['number' => 'PC-2026-00001', 'issue_date' => '2026-09-15', 'expected_delivery_date' => '2026-06-03']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('purchases.dates.update', $order), $this->payload(['issue_date' => '2026-05-29', 'expected_delivery_date' => '2026-06-03']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('2026-05-29', $order->fresh()->issue_date->toDateString());
        $this->assertSame('2026-06-03', $order->fresh()->expected_delivery_date->toDateString());
        $this->assertSame('PC-2026-00001', $order->fresh()->number);
    }

    public function test_admin_can_correct_a_saved_locked_order_with_an_audited_reason(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = $this->order();
        $before = $order->attributesToArray();
        $this->assertSame('121.00', $before['amount']);
        $this->actingAs($user)->patch(route('purchases.dates.update', $order), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $order->refresh();
        $this->assertSame('2026-05-10', $order->issue_date->toDateString());
        $this->assertSame('2026-05-15', $order->expected_delivery_date->toDateString());
        foreach (['number', 'amount', 'status', 'locked_at', 'created_at'] as $field) {
            $this->assertSame($before[$field], $order->attributesToArray()[$field]);
        }
        $event = $order->events()->where('event', 'dates_corrected')->sole();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('2026-09-20', $event->metadata['previous_issue_date']);
        $this->assertSame('2026-05-10', $event->metadata['issue_date']);
        $this->assertSame($this->payload()['reason'], $event->metadata['reason']);
        $this->patch(route('purchases.dates.update', $order), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(1, $order->events()->count());
    }

    public function test_role_reason_and_date_validation_are_enforced_in_server(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create(['role' => 'commercial']))->patch(route('purchases.dates.update', $order), $this->payload())->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->patch(route('purchases.dates.update', $order), $this->payload(['reason' => '']))->assertSessionHasErrors('reason');
        $this->patch(route('purchases.dates.update', $order), $this->payload(['expected_delivery_date' => '2026-01-01']))->assertSessionHasErrors('expected_delivery_date');
        $this->patch(route('purchases.dates.update', $order), $this->payload(['issue_date' => 'bad']))->assertSessionHasErrors('issue_date');
        $this->assertSame('2026-09-20', $order->fresh()->issue_date->toDateString());
        $this->assertSame(0, $order->events()->count());
    }

    public function test_cross_year_correction_renumbers_and_keeps_the_old_reference(): void
    {
        $year = FiscalYear::create(['year' => 2025, 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'status' => 'open']);
        $series = DocumentSeries::create(['document_type' => 'purchase_order', 'name' => 'Historical purchase orders', 'prefix' => 'PO', 'next_number' => 7, 'fiscal_year_id' => $year->id, 'fiscal_year' => 2025, 'is_active' => true]);
        $order = $this->order();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('purchases.dates.update', $order), $this->payload(['issue_date' => '2025-05-10', 'expected_delivery_date' => null]))->assertSessionHasNoErrors();
        $this->assertSame('PO-2025-00007', $order->fresh()->number);
        $this->assertSame($series->id, $order->fresh()->document_series_id);
        $this->assertSame('PO-2026-HISTORY', $order->events()->sole()->metadata['previous_number']);
        $this->assertSame(8, $series->fresh()->next_number);
    }

    public function test_closed_year_missing_series_and_sent_order_are_not_silently_bypassed(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $target = $this->payload(['issue_date' => '2025-05-10', 'expected_delivery_date' => null]);
        $this->patch(route('purchases.dates.update', $order), $target)->assertSessionHasErrors('issue_date');
        $year = FiscalYear::create(['year' => 2025, 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'status' => 'closed']);
        $this->patch(route('purchases.dates.update', $order), $target)->assertSessionHasErrors('issue_date');
        $year->update(['status' => 'open']);
        $this->patch(route('purchases.dates.update', $order), $target)->assertSessionHasErrors('issue_date');
        $order->update(['sent_at' => now()]);
        $this->patch(route('purchases.dates.update', $order), $target)->assertSessionHasErrors('issue_date');
        FiscalYear::where('year', 2026)->update(['status' => 'closed']);
        $this->patch(route('purchases.dates.update', $order), $this->payload())->assertSessionHasErrors('issue_date');
        $this->assertSame('2026-09-20', $order->fresh()->issue_date->toDateString());
        $this->assertSame(0, $order->events()->count());
    }

    public function test_closed_or_invoiced_orders_and_invoices_are_protected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order();
        $order->update(['status' => 'closed']);
        $this->patch(route('purchases.dates.update', $order), $this->payload())->assertSessionHasErrors('issue_date');
        $order->update(['status' => 'ordered']);
        $invoice = EconomicFixture::purchase(['type' => 'supplier_invoice', 'supplier_id' => $order->supplier_id, 'source_document_id' => $order->id, 'number' => 'INV-HISTORY', 'status' => 'registered', 'issue_date' => '2026-09-20', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121]);
        $this->patch(route('purchases.dates.update', $order), $this->payload())->assertSessionHasErrors('issue_date');
        $this->patch(route('purchases.dates.update', $invoice), $this->payload())->assertUnprocessable();
        $this->assertSame('2026-09-20', $order->fresh()->issue_date->toDateString());
    }
}
