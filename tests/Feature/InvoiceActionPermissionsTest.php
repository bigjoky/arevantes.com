<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceActionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_actions_are_independent_of_installation_management_and_each_other(): void
    {
        $profile = PermissionProfile::create(['name' => 'Document editor', 'slug' => 'document-editor']);
        $profile->permissions()->attach(Permission::where('key', 'manage-installations')->firstOrFail());
        $user = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $profile->id]);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Permission test']);
        $this->actingAs($user)->post(route('sales-documents.store'), [
            'type' => 'invoice', 'person_id' => $customer->id, 'issue_date' => '2026-09-13',
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21]],
        ])->assertRedirect();
        $invoice = SalesDocument::firstOrFail();
        $this->get(route('sales-documents.show', $invoice))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('document.can_issue_invoice', false)->where('document.can_prepare_credit_note', false));
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => '2026-09-13'])->assertForbidden();
        $this->assertSame('draft', $invoice->fresh()->status);

        $profile->permissions()->attach(Permission::where('key', 'issue-invoices')->firstOrFail());
        $this->actingAs($user->fresh())->get(route('sales-documents.show', $invoice))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('document.can_issue_invoice', true)->where('document.can_prepare_credit_note', false));
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => '2026-09-13'])->assertRedirect();
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->post(route('sales-documents.credit-note', $invoice))->assertForbidden();

        $profile->permissions()->attach(Permission::where('key', 'prepare-credit-notes')->firstOrFail());
        $this->actingAs($user->fresh())->post(route('sales-documents.credit-note', $invoice))->assertRedirect();
        $this->assertSame(1, SalesDocument::where('type', 'credit_note')->count());
    }

    public function test_migration_preserves_profile_membership_and_individual_denials_and_grants(): void
    {
        $migration = require database_path('migrations/2026_09_12_010000_add_invoice_action_permissions.php');
        $migration->down();
        $source = Permission::where('key', 'manage-installations')->firstOrFail();
        $profile = PermissionProfile::create(['name' => 'Existing profile', 'slug' => 'existing-invoice-profile']);
        $profile->permissions()->attach($source);
        $empty = PermissionProfile::create(['name' => 'Empty profile', 'slug' => 'empty-invoice-profile']);
        $denied = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $profile->id]);
        $granted = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $empty->id]);
        $denied->permissionOverrides()->attach($source, ['allowed' => false, 'granted_by' => $granted->id]);
        $granted->permissionOverrides()->attach($source, ['allowed' => true, 'granted_by' => $denied->id]);
        $migration->up();

        foreach (['issue-invoices', 'prepare-credit-notes'] as $key) {
            $permission = Permission::where('key', $key)->firstOrFail();
            $this->assertTrue($profile->permissions()->whereKey($permission->id)->exists());
            $this->assertFalse($empty->permissions()->whereKey($permission->id)->exists());
            $this->assertFalse($denied->fresh()->can($key));
            $this->assertTrue($granted->fresh()->can($key));
            $this->assertDatabaseHas('user_permission_overrides', ['user_id' => $denied->id, 'permission_id' => $permission->id, 'allowed' => false, 'granted_by' => $granted->id]);
        }
        $profile->permissions()->detach(Permission::where('key', 'issue-invoices')->firstOrFail());
        $migration->up();
        $this->assertFalse($profile->permissions()->where('key', 'issue-invoices')->exists());
    }
}
