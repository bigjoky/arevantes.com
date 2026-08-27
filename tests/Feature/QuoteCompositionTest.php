<?php

namespace Tests\Feature;

use App\Jobs\GeocodeInstallation;
use App\Models\MaintenancePlan;
use App\Models\Person;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class QuoteCompositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_quote_lines_can_be_nested_up_to_five_levels(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Cliente jerarquía']);
        $lines = collect(range(1, 5))->map(fn (int $level): array => [
            'client_key' => "level-{$level}",
            'parent_client_key' => $level === 1 ? null : 'level-'.($level - 1),
            'description' => "Partida nivel {$level}",
            'quantity' => 1,
            'unit_price' => $level * 10,
            'discount_percent' => 0,
            'tax_percent' => 0,
        ])->all();

        $this->actingAs($commercial)->post(route('quotes.store'), [
            'person_id' => $customer->id,
            'issue_date' => today()->toDateString(),
            'lines' => $lines,
        ])->assertRedirect();

        $quote = Quote::with('lines')->firstOrFail();
        $this->assertSame('150.00', $quote->amount);
        $this->assertSame([1, 2, 3, 4, 5], $quote->lines->pluck('depth')->all());

        foreach ($quote->lines->skip(1) as $index => $line) {
            $this->assertSame($quote->lines[$index - 1]->id, $line->parent_line_id);
        }
    }

    public function test_custom_quote_lines_cannot_exceed_five_levels(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Cliente límite']);
        $lines = collect(range(1, 6))->map(fn (int $level): array => [
            'client_key' => "level-{$level}",
            'parent_client_key' => $level === 1 ? null : 'level-'.($level - 1),
            'description' => "Partida nivel {$level}",
            'quantity' => 1,
            'unit_price' => 10,
            'discount_percent' => 0,
            'tax_percent' => 0,
        ])->all();

        $this->actingAs($commercial)->post(route('quotes.store'), [
            'person_id' => $customer->id,
            'issue_date' => today()->toDateString(),
            'lines' => $lines,
        ])->assertSessionHasErrors('lines.5.parent_client_key');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_commercial_part_keeps_its_price_and_components_drive_project_operations(): void
    {
        Bus::fake([GeocodeInstallation::class]);
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Cliente composición']);
        $unit = Unit::where('code', 'unit')->firstOrFail();
        $tax = TaxRate::where('is_default', true)->firstOrFail();
        $panel = Product::create([
            'type' => 'product',
            'sku' => 'PANEL-COMP',
            'name' => 'Panel 500 W',
            'unit_id' => $unit->id,
            'tax_rate_id' => $tax->id,
            'sale_price' => 200,
            'cost_price' => 100,
            'track_stock' => true,
            'track_serials' => true,
            'is_active' => true,
        ]);

        $this->actingAs($commercial)->post(route('quotes.store'), [
            'person_id' => $customer->id,
            'issue_date' => today()->toDateString(),
            'valid_until' => today()->addMonth()->toDateString(),
            'lines' => [[
                'description' => 'Instalación fotovoltaica completa',
                'quantity' => 2,
                'unit_price' => 1000,
                'discount_percent' => 0,
                'tax_percent' => 21,
                'components' => [
                    [
                        'product_id' => $panel->id,
                        'unit_id' => $unit->id,
                        'kind' => 'material',
                        'fulfillment_kind' => 'stock',
                        'visibility' => 'detailed',
                        'description' => 'Panel fotovoltaico 500 W',
                        'quantity' => 4,
                        'planned_unit_cost' => 1,
                    ],
                    [
                        'kind' => 'document',
                        'fulfillment_kind' => 'activity',
                        'visibility' => 'summary',
                        'description' => 'Legalización ante Industria',
                        'quantity' => 1,
                        'planned_unit_cost' => 50,
                    ],
                    [
                        'kind' => 'service',
                        'fulfillment_kind' => 'recurring',
                        'visibility' => 'detailed',
                        'description' => 'Mantenimiento preventivo anual',
                        'quantity' => 1,
                        'planned_unit_cost' => 50,
                        'interval_months' => 12,
                    ],
                ],
            ]],
        ])->assertRedirect();

        $quote = Quote::with('lines.components')->firstOrFail();
        $this->assertSame('2420.00', $quote->amount);
        $this->assertSame('100.00', $quote->lines->first()->components->first()->planned_unit_cost);
        $this->assertCount(3, $quote->versions()->firstOrFail()->lines[0]['components']);
        $this->get(route('quotes.pdf', $quote))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->patch(route('quotes.status.update', $quote), ['status' => 'accepted'])->assertRedirect();
        $this->post(route('quotes.project.store', $quote), [
            'service_type' => 'solar',
            'scheduled_at' => today()->addDay()->setTime(9, 0)->toDateTimeString(),
            'site_name' => 'Proyecto composición',
            'site_address_line_1' => 'Calle Solar',
            'site_city' => 'Madrid',
            'site_country' => 'Spain',
        ])->assertRedirect();

        $installation = $quote->fresh()->installation;
        $this->assertDatabaseHas('installation_material_requirements', [
            'installation_id' => $installation->id,
            'product_id' => $panel->id,
            'planned_quantity' => 8,
            'planned_unit_cost' => 100,
        ]);
        $this->assertDatabaseHas('crm_activities', [
            'installation_id' => $installation->id,
            'title' => 'Legalización ante Industria',
            'status' => 'pending',
        ]);
        $this->assertSame(12, MaintenancePlan::where('installation_id', $installation->id)->firstOrFail()->interval_months);

        $this->post(route('quotes.convert', $quote), ['type' => 'invoice'])->assertRedirect();
        $invoice = SalesDocument::where('quote_id', $quote->id)->where('type', 'invoice')->where('is_acceptance_invoice', false)->firstOrFail();
        $this->assertCount(1, $invoice->lines);
        $this->assertSame('1694.00', $invoice->amount);
        $this->assertSame(2420.0, (float) SalesDocument::where('quote_id', $quote->id)->where('type', 'invoice')->sum('amount'));
        $this->assertSame('Instalación fotovoltaica completa', $invoice->lines->first()->description);
    }

    public function test_unselected_options_are_included_in_the_approval_risk_check(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Cliente opciones']);

        $this->actingAs($commercial)->post(route('quotes.store'), [
            'person_id' => $customer->id,
            'issue_date' => today()->toDateString(),
            'lines' => [
                [
                    'description' => 'Instalación base',
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'discount_percent' => 0,
                    'tax_percent' => 21,
                    'selection_mode' => 'included',
                    'is_selected' => true,
                ],
                [
                    'description' => 'Opción con descuento excepcional',
                    'quantity' => 1,
                    'unit_price' => 500,
                    'discount_percent' => 20,
                    'tax_percent' => 21,
                    'selection_mode' => 'optional',
                    'is_selected' => false,
                ],
            ],
        ])->assertRedirect();

        $quote = Quote::firstOrFail();
        $this->assertSame('1000.00', $quote->subtotal);
        $this->assertSame('pending', $quote->approval_status);
    }
}
