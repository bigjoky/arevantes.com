<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Quote;
use App\Models\Unit;
use App\Services\Documents\DocumentPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposal_pdf_separates_scope_options_components_totals_and_acceptance_terms(): void
    {
        $quote = $this->representativeQuote();
        $company = CompanyProfile::firstOrFail()->toArray();
        $company['logo_data'] = null;
        $html = view('pdf.quote', [
            'document' => $quote->load(['lines.components.unit', 'opportunity:id,code,title', 'installation:id,project_reference,site_name']),
            'company' => $company,
            'isDraft' => true,
        ])->render();

        $this->assertStringContainsString('Propuesta comercial', $html);
        $this->assertStringContainsString('Alcance e inversión', $html);
        $this->assertStringContainsString('Opciones disponibles no incluidas', $html);
        $this->assertStringContainsString('Anticipo del 30%', $html);
        $this->assertStringContainsString('Aceptación de la propuesta', $html);
        $this->assertStringContainsString('Gateway de comunicaciones', $html);
        $this->assertStringNotContainsString('Coste interno de aprovisionamiento', $html);
        $this->assertLessThan(
            strpos($html, 'Opciones disponibles no incluidas'),
            strpos($html, 'Sistema fotovoltaico llave en mano'),
        );

        $pdf = app(DocumentPdf::class)->render($quote, 'quote');
        $this->assertStringStartsWith('%PDF', $pdf);

        if ($output = getenv('GENERATE_QUOTE_PDF_ARTIFACT')) {
            file_put_contents($output, $pdf);
        }
    }

    private function representativeQuote(): Quote
    {
        $customer = Person::create([
            'category' => 'customer', 'company_name' => 'Hotel Mirador del Sur, S.L.',
            'first_name' => 'Elena', 'last_name' => 'Martín', 'tax_id' => 'B12345678',
            'billing_email' => 'administracion@hotelmirador.example', 'address_line_1' => 'Avenida del Mar 42',
            'postal_code' => '29016', 'city' => 'Málaga', 'province_state' => 'Málaga', 'country' => 'España',
        ]);
        $installation = Installation::create([
            'person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'quoted',
            'project_reference' => 'FV-HMS-2026', 'site_name' => 'Hotel Mirador del Sur',
            'site_address_line_1' => 'Avenida del Mar 42',
        ]);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id, 'installation_id' => $installation->id,
            'code' => 'OPP-2026-00142', 'title' => 'Autoconsumo fotovoltaico para Hotel Mirador del Sur',
            'stage' => 'proposal', 'probability' => 60, 'estimated_value' => 12300.75,
        ]);
        $quote = Quote::create([
            'person_id' => $customer->id, 'installation_id' => $installation->id, 'opportunity_id' => $opportunity->id,
            'service_type' => 'solar', 'number' => 'PRE-2026-00142', 'version' => 3, 'status' => 'draft',
            'approval_status' => 'not_required', 'issue_date' => '2026-08-29', 'valid_until' => '2026-09-28',
            'acceptance_payment_percentage' => 30, 'acceptance_payment_due_days' => 5,
            'subtotal' => 10650, 'discount_amount' => 425, 'tax_amount' => 2075.75, 'amount' => 12300.75,
            'notes' => "La planificación definitiva se confirmará tras la visita técnica.\nLa oferta incluye legalización y puesta en marcha.",
            'customer_snapshot' => $customer->only([
                'company_name', 'first_name', 'last_name', 'tax_id', 'billing_email', 'address_line_1',
                'postal_code', 'city', 'province_state', 'country',
            ]),
        ]);
        $system = $quote->lines()->create([
            'position' => 1, 'selection_mode' => 'included', 'is_selected' => true,
            'description' => 'Sistema fotovoltaico llave en mano de 15 kWp', 'quantity' => 1, 'unit_price' => 8500,
            'discount_percent' => 5, 'tax_percent' => 21, 'subtotal' => 8500, 'discount_amount' => 425,
            'tax_amount' => 1695.75, 'total' => 9770.75,
        ]);
        $unit = Unit::firstOrFail();
        $panel = $system->components()->create([
            'position' => 1, 'depth' => 1, 'unit_id' => $unit->id, 'kind' => 'material', 'fulfillment_kind' => 'stock',
            'visibility' => 'detailed', 'description' => 'Módulos fotovoltaicos de alta eficiencia', 'quantity' => 30, 'planned_unit_cost' => 100,
        ]);
        $structure = $system->components()->create([
            'parent_component_id' => $panel->id, 'position' => 2, 'depth' => 2, 'unit_id' => $unit->id,
            'kind' => 'material', 'fulfillment_kind' => 'stock', 'visibility' => 'summary',
            'description' => 'Estructura y fijaciones para cubierta', 'quantity' => 1, 'planned_unit_cost' => 500,
        ]);
        $wiring = $system->components()->create([
            'parent_component_id' => $structure->id, 'position' => 3, 'depth' => 3, 'unit_id' => $unit->id,
            'kind' => 'material', 'fulfillment_kind' => 'stock', 'visibility' => 'detailed',
            'description' => 'Cableado solar y protecciones', 'quantity' => 1, 'planned_unit_cost' => 400,
        ]);
        $system->components()->create([
            'parent_component_id' => $wiring->id, 'position' => 4, 'depth' => 4, 'unit_id' => $unit->id,
            'kind' => 'material', 'fulfillment_kind' => 'stock', 'visibility' => 'detailed',
            'description' => 'Gateway de comunicaciones', 'quantity' => 1, 'planned_unit_cost' => 120,
        ]);
        $system->components()->create([
            'position' => 5, 'depth' => 1, 'unit_id' => $unit->id, 'kind' => 'other', 'fulfillment_kind' => 'none',
            'visibility' => 'hidden', 'description' => 'Coste interno de aprovisionamiento', 'quantity' => 1, 'planned_unit_cost' => 9999,
        ]);
        $quote->lines()->create([
            'position' => 2, 'selection_mode' => 'included', 'is_selected' => true,
            'description' => 'Ingeniería, legalización y puesta en marcha', 'quantity' => 1, 'unit_price' => 650,
            'discount_percent' => 0, 'tax_percent' => 10, 'subtotal' => 650, 'discount_amount' => 0,
            'tax_amount' => 65, 'total' => 715,
        ]);
        $quote->lines()->create([
            'position' => 3, 'selection_mode' => 'optional', 'is_selected' => true,
            'description' => 'Monitorización energética avanzada', 'quantity' => 1, 'unit_price' => 1500,
            'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 1500, 'discount_amount' => 0,
            'tax_amount' => 315, 'total' => 1815,
        ]);
        $quote->lines()->create([
            'position' => 4, 'selection_mode' => 'optional', 'is_selected' => false,
            'description' => 'Mantenimiento preventivo durante dos años', 'quantity' => 1, 'unit_price' => 390,
            'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 390, 'discount_amount' => 0,
            'tax_amount' => 81.90, 'total' => 471.90,
        ]);
        $quote->lines()->create([
            'position' => 5, 'selection_mode' => 'alternative', 'option_group' => 'Almacenamiento', 'is_selected' => false,
            'description' => 'Batería de respaldo de 10 kWh', 'quantity' => 1, 'unit_price' => 2100,
            'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 2100, 'discount_amount' => 0,
            'tax_amount' => 441, 'total' => 2541,
        ]);

        return $quote;
    }
}
