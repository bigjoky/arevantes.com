<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalEntity;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FiscalEntityTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $overrides = []): array
    {
        Country::firstOrCreate(['code' => 'ES'], ['name' => 'Spain']);
        Country::firstOrCreate(['code' => 'FR'], ['name' => 'France']);

        return [...[
            'country' => 'ES', 'tax_id' => 'A-12345678', 'legal_name' => 'Test fiscal entity',
            'trade_names' => ['North station', 'South station'], 'notes' => 'Master record', 'is_active' => true,
            'addresses' => [
                ['label' => 'Fiscal office', 'kind' => 'fiscal', 'address_line_1' => 'Example street 1', 'city' => 'Madrid', 'postal_code' => '28001', 'country' => 'ES'],
                ['label' => 'Establishment', 'kind' => 'operating', 'address_line_1' => 'Example street 2', 'city' => 'Madrid', 'country' => 'ES'],
            ],
        ], ...$overrides];
    }

    private function createEntity(): FiscalEntity
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('fiscal-entities.store'), $this->data())->assertSessionHasNoErrors()->assertRedirect();

        return FiscalEntity::sole();
    }

    public function test_admin_can_manage_multiple_brands_and_addresses_with_audit(): void
    {
        $entity = $this->createEntity();
        $this->assertCount(2, $entity->addresses);
        $this->assertNotEmpty($entity->addresses[0]['id']);
        $this->assertSame('A12345678', $entity->tax_id_key);
        $this->assertSame(auth()->id(), AuditLog::where('event', 'fiscal_entity_created')->sole()->user_id);
        $this->get(route('fiscal-entities.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('fiscal-entities/index')->has('entities.data', 1));
        $this->get(route('fiscal-entities.show', $entity))->assertOk()->assertInertia(fn (Assert $page) => $page->component('fiscal-entities/show')->where('revision', $entity->revision()));
        $this->patch(route('fiscal-entities.update', $entity), $this->data(['addresses' => $entity->addresses, 'legal_name' => 'Updated fiscal name', 'revision' => $entity->revision(), 'change_note' => 'Verified correct name']))->assertSessionHasNoErrors();
        $this->assertSame('Updated fiscal name', $entity->fresh()->legal_name);
        $log = AuditLog::where('event', 'fiscal_entity_updated')->sole();
        $this->assertSame('Test fiscal entity', $log->old_values['legal_name']);
        $this->assertSame('Verified correct name', $log->new_values['change_note']);
    }

    public function test_identity_is_unique_per_country_and_cannot_be_reassigned(): void
    {
        $entity = $this->createEntity();
        $this->post(route('fiscal-entities.store'), $this->data(['tax_id' => 'a12 345.678']))->assertSessionHasErrors('tax_id');
        $this->post(route('fiscal-entities.store'), $this->data(['country' => 'FR']))->assertSessionHasNoErrors();
        $this->assertSame(2, FiscalEntity::count());
        $this->patch(route('fiscal-entities.update', $entity), $this->data(['tax_id' => 'B12345678', 'revision' => $entity->revision(), 'change_note' => 'Wrong identity']))->assertSessionHasErrors('tax_id');
        $this->assertSame('A12345678', $entity->fresh()->tax_id_key);
    }

    public function test_revision_reason_and_address_validation_protect_edits(): void
    {
        $entity = $this->createEntity();
        $payload = $this->data(['revision' => $entity->revision(), 'change_note' => 'Verified update']);
        $this->patch(route('fiscal-entities.update', $entity), [...$payload, 'change_note' => ''])->assertSessionHasErrors('change_note');
        $entity->update(['notes' => 'Changed by another administrator']);
        $this->patch(route('fiscal-entities.update', $entity), $payload)->assertSessionHasErrors('revision');
        $payload['revision'] = $entity->fresh()->revision();
        $payload['addresses'][1]['kind'] = 'fiscal';
        $this->patch(route('fiscal-entities.update', $entity), $payload)->assertSessionHasErrors('addresses');
        $this->post(route('fiscal-entities.store'), $this->data(['country' => ['ES'], 'tax_id' => ['bad']]))->assertSessionHasErrors(['country', 'tax_id']);
    }

    public function test_directory_drives_lookup_and_inactive_entities_do_not_autofill(): void
    {
        $entity = $this->createEntity();
        $url = route('expenses.issuer.lookup', ['tax_id' => 'A12345678', 'country' => 'ES']);
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'candidates')->assertJsonPath('candidates.0.source', 'directory')->assertJsonPath('candidates.1.trade_name', 'South station');
        $this->getJson(route('expenses.issuer.lookup', ['tax_id' => 'A12345678', 'country' => 'FR']))->assertExactJson(['candidates' => []]);
        $entity->update(['is_active' => false]);
        $this->getJson($url)->assertExactJson(['candidates' => []]);
    }

    public function test_non_admin_cannot_read_or_modify_the_directory(): void
    {
        $entity = $this->createEntity();
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->get(route('fiscal-entities.index'))->assertForbidden();
        $this->get(route('fiscal-entities.show', $entity))->assertForbidden();
        $this->post(route('fiscal-entities.store'), $this->data())->assertForbidden();
        $this->patch(route('fiscal-entities.update', $entity), $this->data())->assertForbidden();
    }

    public function test_directory_edits_do_not_rewrite_supplier_records_or_historical_expenses(): void
    {
        $entity = $this->createEntity();
        $supplier = Supplier::create(['legal_name' => 'Original supplier name', 'country' => 'ES', 'tax_id' => 'A12345678']);
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'supplier_id' => $supplier->id,
            'number' => 'EXP-HISTORY', 'type' => 'invoice', 'issuer_legal_name' => 'Name on historical invoice',
            'issuer_tax_id' => 'A12345678', 'expense_date' => '2026-05-29', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'status' => 'paid']);
        $before = $expense->fresh()->getAttributes();
        $this->patch(route('fiscal-entities.update', $entity), $this->data(['legal_name' => 'New master name', 'revision' => $entity->revision(), 'change_note' => 'Current legal name']))->assertSessionHasNoErrors();
        $this->assertSame($before, $expense->fresh()->getAttributes());
        $this->assertSame('Original supplier name', $supplier->fresh()->legal_name);
    }

    public function test_new_expenses_keep_country_and_duplicate_checks_do_not_mix_countries(): void
    {
        $this->data();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = ['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice',
            'document_number' => 'SAME-NUMBER', 'issuer_tax_id' => 'A12345678', 'expense_date' => '2026-05-29', 'subtotal' => 100, 'tax_amount' => 21];
        foreach (['ES', 'FR'] as $country) {
            $this->post(route('expenses.store'), [...$payload, 'issuer_legal_name' => 'Issuer '.$country, 'issuer_country' => $country])->assertSessionHasNoErrors();
            $this->getJson(route('expenses.issuer.lookup', ['tax_id' => 'A12345678', 'country' => $country]))->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.legal_name', 'Issuer '.$country);
        }
        $this->post(route('expenses.store'), [...$payload, 'issuer_legal_name' => 'Issuer FR', 'issuer_country' => 'FR'])->assertSessionHasErrors('document_number');
        $this->assertSame(2, Expense::count());
    }

    public function test_audit_failure_rolls_back_directory_changes(): void
    {
        $entity = $this->createEntity();
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->patch(route('fiscal-entities.update', $entity), $this->data(['legal_name' => 'Must roll back', 'revision' => $entity->revision(), 'change_note' => 'Test rollback']))->assertStatus(500);
            $this->assertSame('Test fiscal entity', $entity->fresh()->legal_name);
        } finally {
            AuditLog::flushEventListeners();
        }
    }
}
