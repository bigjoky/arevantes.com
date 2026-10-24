<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Projects\ProjectJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDossierClosureTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): Installation
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $person = Person::create(['category' => 'customer', 'first_name' => 'Cierre ficticio']);
        $installation = Installation::create(['person_id' => $person->id, 'service_type' => 'solar', 'status' => 'completed', 'site_address_line_1' => 'Prueba local', 'project_reference' => 'PRJ-CLOSURE']);
        $quote = Quote::create(['installation_id' => $installation->id, 'person_id' => $person->id, 'created_by' => $user->id, 'number' => 'PRE-CLOSURE', 'status' => 'accepted', 'issue_date' => today(), 'amount' => 121]);
        $invoice = SalesDocument::create(['type' => 'invoice', 'installation_id' => $installation->id, 'quote_id' => $quote->id, 'person_id' => $person->id, 'created_by' => $user->id, 'number' => 'INV-CLOSURE', 'status' => 'paid', 'issue_date' => today(), 'issued_at' => now(), 'locked_at' => now(), 'customer_snapshot' => [], 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 121]);
        $invoice->payments()->create(['amount' => 121, 'paid_at' => today(), 'method' => 'cash', 'created_by' => $user->id]);
        foreach (['installation', 'commissioning'] as $kind) {
            WorkOrder::create(['installation_id' => $installation->id, 'number' => 'WO-CLOSE-'.$kind, 'title' => $kind, 'kind' => $kind, 'status' => 'completed']);
        }

        return $installation;
    }

    private function decide(Installation $installation, string $action, array $extra = [])
    {
        $fresh = $installation->fresh();

        return $this->post(route('installations.dossier.update', $installation), ['action' => $action, 'reason' => 'Decisión de prueba documentada', 'expected_updated_at' => $fresh->updated_at->toISOString(), 'expected_revision' => $fresh->dossier_revision, ...$extra]);
    }

    public function test_execution_delivery_and_administrative_closure_are_separate_and_reopening_is_audited(): void
    {
        $installation = $this->fixture();
        $this->assertNull($installation->dossier_closed_at);
        $this->decide($installation, 'close')->assertSessionHasErrors('action');
        $task = CrmActivity::create(['installation_id' => $installation->id, 'title' => 'Tarea informativa', 'type' => 'task', 'status' => 'pending', 'priority' => 'normal', 'required_for_closure' => false]);
        $this->decide($installation, 'confirm_delivery')->assertRedirect()->assertSessionHasNoErrors();
        $this->decide($installation, 'close')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($installation->fresh()->dossier_closed_at);
        $this->assertSame('pending', $task->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'project_dossier_close', 'auditable_id' => $installation->id]);
        $this->decide($installation, 'reopen')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($installation->fresh()->dossier_closed_at);
        $this->assertNull($installation->fresh()->dossier_delivery_confirmed_at);
        $this->assertSame('completed', $installation->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'project_dossier_reopen', 'auditable_id' => $installation->id]);
    }

    public function test_required_documentation_and_open_execution_orders_block_closure_without_changing_them(): void
    {
        $installation = $this->fixture();
        $task = CrmActivity::create(['installation_id' => $installation->id, 'title' => 'Legalización contratada', 'type' => 'task', 'status' => 'pending', 'priority' => 'normal', 'required_for_closure' => true]);
        $this->decide($installation, 'confirm_delivery')->assertSessionHasErrors('action');
        $task->update(['status' => 'completed']);
        $this->decide($installation, 'confirm_delivery')->assertSessionHasNoErrors();
        WorkOrder::create(['installation_id' => $installation->id, 'number' => 'WO-CLOSE-PENDING', 'title' => 'Pendiente', 'kind' => 'installation', 'status' => 'planned']);
        $this->decide($installation, 'close', ['economic_exception' => true])->assertSessionHasErrors('action');
        $this->assertNull($installation->fresh()->dossier_closed_at);
    }

    public function test_unpaid_or_unissued_milestones_block_only_administrative_closure(): void
    {
        $installation = $this->fixture();
        $invoice = SalesDocument::where('installation_id', $installation->id)->firstOrFail();
        $invoice->payments()->delete();
        $invoice->update(['status' => 'draft', 'issued_at' => null, 'locked_at' => null]);
        $this->decide($installation, 'confirm_delivery')->assertSessionHasNoErrors();
        $this->decide($installation, 'close')->assertSessionHasErrors('action');
        $this->assertSame('completed', $installation->fresh()->status);
        $this->assertNull($installation->fresh()->dossier_closed_at);
    }

    public function test_admin_can_explicitly_close_with_economic_pending_and_audited_reason_without_issuing_or_collecting(): void
    {
        $installation = $this->fixture();
        $invoice = SalesDocument::where('installation_id', $installation->id)->firstOrFail();
        $invoice->payments()->delete();
        $invoice->update(['status' => 'draft', 'issued_at' => null, 'locked_at' => null, 'paid_amount' => 0]);
        $this->decide($installation, 'confirm_delivery')->assertSessionHasNoErrors();
        $this->decide($installation, 'close', ['economic_exception' => true, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->decide($installation, 'close', ['economic_exception' => true, 'reason' => 'Cobro aplazado autorizado por administración'])->assertSessionHasNoErrors();
        $this->assertNotNull($installation->fresh()->dossier_closed_at);
        $journey = app(ProjectJourney::class)->for(User::findOrFail($installation->fresh()->dossier_closed_by), $installation->fresh());
        $closure = collect($journey['blocks'])->firstWhere('key', 'closure');
        $this->assertStringContainsString('excepción económica', $closure['detail']);
        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->issued_at);
        $this->assertSame(0, $invoice->payments()->count());
        $audit = AuditLog::where('event', 'project_dossier_close')->where('auditable_id', $installation->id)->latest('id')->firstOrFail();
        $this->assertTrue($audit->new_values['closure_basis']['economic_exception']);
        $this->assertSame('Cobro aplazado autorizado por administración', $audit->new_values['closure_basis']['economic_exception_reason']);
        $this->assertSame([$invoice->id], $audit->new_values['closure_basis']['draft_invoice_ids']);
        $this->decide($installation, 'reopen')->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->decide($installation, 'close', ['economic_exception' => true])->assertForbidden();
        $this->assertNull($installation->fresh()->dossier_closed_at);
    }

    public function test_stale_decisions_and_unauthorized_users_cannot_overwrite_requirements(): void
    {
        $installation = $this->fixture();
        $version = $installation->updated_at->toISOString();
        $this->decide($installation, 'require_technical')->assertSessionHasNoErrors();
        $this->post(route('installations.dossier.update', $installation), ['action' => 'waive_technical', 'reason' => 'Decisión desde pantalla antigua', 'expected_updated_at' => $version, 'expected_revision' => 0])->assertSessionHasErrors('expected_updated_at');
        $this->assertTrue($installation->fresh()->technical_validation_required);
        $this->actingAs(User::factory()->create(['role' => 'technician']));
        $this->decide($installation, 'waive_technical')->assertForbidden();
    }
}
