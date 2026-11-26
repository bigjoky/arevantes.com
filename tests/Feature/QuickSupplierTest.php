<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuickSupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_creation_and_existing_normalized_tax_identity(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $data = ['tax_id' => 'B-12345678', 'country' => 'ES', 'legal_name' => 'Razón fiscal SL', 'trade_name' => 'Nombre comercial', 'city' => 'Madrid'];
        $response = $this->postJson('/catalog/suppliers/quick-create', $data)->assertCreated()->assertJsonPath('existing', false)->assertJsonPath('supplier.country', 'ES');
        $id = $response->json('supplier.id');
        $this->assertDatabaseHas('suppliers', ['id' => $id, 'tax_id' => 'B12345678', 'legal_name' => 'Razón fiscal SL', 'trade_name' => 'Nombre comercial']);
        $this->postJson('/catalog/suppliers/quick-create', [...$data, 'tax_id' => 'b 12345678', 'legal_name' => 'No sobrescribir'])->assertOk()->assertJsonPath('existing', true)->assertJsonPath('supplier.id', $id);
        $this->assertDatabaseHas('suppliers', ['id' => $id, 'legal_name' => 'Razón fiscal SL']);
    }

    public function test_catalog_and_quick_creation_share_normalized_duplicate_detection(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $payload = ['tax_id' => 'B-45678901', 'country' => 'ES', 'legal_name' => 'Sociedad original', 'payment_terms_days' => 0];
        $this->post('/catalog/suppliers', $payload)->assertSessionHasNoErrors();
        $this->post('/catalog/suppliers', [...$payload, 'tax_id' => 'B 45678901'])->assertSessionHasErrors('tax_id');
        $this->postJson('/catalog/suppliers/quick-create', [...$payload, 'tax_id' => 'B.45678901'])->assertOk()->assertJsonPath('existing', true)->assertJsonPath('supplier.legal_name', 'Sociedad original');
        $this->assertSame(1, Supplier::where('legal_name', 'Sociedad original')->count());
        $this->postJson('/catalog/suppliers/quick-create', [...$payload, 'country' => 'FR'])->assertCreated();
    }

    public function test_lookup_handles_complete_missing_and_unavailable_responses(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        config(['services.supplier_registry.key' => null]);
        $this->postJson('/catalog/suppliers/quick-lookup', ['tax_id' => 'B12345678', 'country' => 'ES'])->assertOk()->assertJsonPath('status', 'unavailable');
        config(['services.supplier_registry.provider' => 'apiempresas', 'services.supplier_registry.key' => 'synthetic-key']);
        Http::fake(['apiempresas.es/*' => Http::sequence()->push(['data' => ['cif' => 'B12345678', 'name' => 'Empresa ficticia SL', 'address' => 'Calle prueba 1', 'postal_code' => '28000', 'municipality' => 'Madrid', 'province' => 'Madrid']], 200)->push([], 404)]);
        $this->postJson('/catalog/suppliers/quick-lookup', ['tax_id' => 'B12345678', 'country' => 'ES'])->assertOk()->assertJsonPath('fields.address_line_1', 'Calle prueba 1')->assertJsonPath('address_type', 'domicilio social registral');
        $this->postJson('/catalog/suppliers/quick-lookup', ['tax_id' => 'B12345678', 'country' => 'ES'])->assertOk()->assertJsonPath('status', 'not_found');
    }

    public function test_network_failure_keeps_manual_entry_available(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        config(['services.supplier_registry.provider' => 'apiempresas', 'services.supplier_registry.key' => 'synthetic-key']);
        Http::fake(['apiempresas.es/*' => fn () => throw new ConnectionException('synthetic failure')]);
        $this->postJson('/catalog/suppliers/quick-lookup', ['tax_id' => 'B12345678', 'country' => 'ES'])->assertOk()->assertJsonPath('status', 'unavailable');
        $this->postJson('/catalog/suppliers/quick-create', ['tax_id' => 'B12345678', 'country' => 'ES', 'legal_name' => 'Alta manual SL'])->assertCreated();
    }

    public function test_non_authorized_users_cannot_create_or_consult(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $this->actingAs($user)->postJson('/catalog/suppliers/quick-create', [])->assertForbidden();
        $this->postJson('/catalog/suppliers/quick-lookup', ['tax_id' => 'B12345678'])->assertForbidden();
    }
}
