<?php

namespace Tests\Feature;

use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuoteTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['name' => 'Solar 6 kW', 'service_type' => 'solar', 'person_id' => 999, 'lines' => [[
            'client_key' => 'solar', 'description' => 'Instalación solar', 'quantity' => '12', 'unit_price' => '85.125', 'total_with_tax' => '', 'discount_percent' => 0, 'tax_percent' => 21, 'selection_mode' => 'included', 'is_selected' => true,
            'components' => [
                ['client_key' => 'parent', 'parent_client_key' => null, 'kind' => 'material', 'fulfillment_kind' => 'none', 'visibility' => 'hidden', 'description' => 'Kit de montaje', 'quantity' => 1, 'planned_unit_cost' => 20],
                ['client_key' => 'child', 'parent_client_key' => 'parent', 'kind' => 'labor', 'fulfillment_kind' => 'activity', 'visibility' => 'detailed', 'description' => 'Montaje', 'quantity' => 2, 'planned_unit_cost' => 30],
            ],
        ]]];
    }

    public function test_templates_save_composition_and_precision_without_creating_quotes_or_customer_data(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->from('/quotes/create')->post(route('quote-templates.store'), $this->payload())->assertRedirect('/quotes/create')->assertSessionHasNoErrors();
        $template = QuoteTemplate::firstOrFail();
        $this->assertSame('85.125', $template->lines[0]['unit_price']);
        $this->assertSame('parent', $template->lines[0]['components'][1]['parent_client_key']);
        $this->assertSame(3, $template->lines[0]['components'][1]['depth']);
        $this->assertSame($user->id, $template->created_by);
        $this->assertArrayNotHasKey('person_id', $template->getAttributes());
        $this->assertSame(0, Quote::count());
        $this->get(route('quotes.create'))->assertInertia(fn (Assert $page) => $page->where('installationTemplates.0.name', 'Solar 6 kW'));
        $payload = $this->payload();
        $payload['lines'][0]['unit_price'] = '90.500';
        $this->put(route('quote-templates.update', $template), $payload)->assertSessionHasNoErrors();
        $this->assertSame('90.500', $template->fresh()->lines[0]['unit_price']);
        $this->delete(route('quote-templates.destroy', $template))->assertRedirect();
        $this->assertSame(0, QuoteTemplate::count());
    }

    public function test_invalid_composition_and_precision_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = $this->payload();
        $payload['lines'][0]['components'][0]['parent_client_key'] = 'child';
        $this->post(route('quote-templates.store'), $payload)->assertSessionHasErrors('lines.0.components.0.parent_client_key');
        $payload = $this->payload();
        $payload['lines'][0]['unit_price'] = '1.1234';
        $this->post(route('quote-templates.store'), $payload)->assertSessionHasErrors('lines.0.unit_price');
        $this->assertSame(0, QuoteTemplate::count());
    }

    public function test_templates_require_proposal_management_permission(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'technician']));
        $this->post(route('quote-templates.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, QuoteTemplate::count());
    }
}
