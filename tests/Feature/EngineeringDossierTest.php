<?php

namespace Tests\Feature;

use App\Models\EngineeringDossier;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EngineeringDossierTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_technical_dossier_is_versioned_approved_and_handed_over_without_committing_execution(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $installation = Installation::create([
            'service_type' => 'electrical',
            'status' => 'survey_booked',
            'project_reference' => 'ELEC-ENG-1',
            'site_address_line_1' => 'Calle Técnica 1',
        ]);
        $opportunity = Opportunity::create([
            'installation_id' => $installation->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'code' => 'OPP-ENG-1',
            'title' => 'Electrical refurbishment',
            'stage' => 'survey',
        ]);
        $this->actingAs($admin);

        $this->post(route('engineering.store'), $this->payload($opportunity, $installation, [
            'proposed_solution' => 'Replace the main board and separate critical circuits.',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $dossier = EngineeringDossier::firstOrFail();
        $this->assertSame('ENG-2026-000001', $dossier->code);
        $this->assertSame(1, $dossier->current_version);
        $this->assertSame('draft', $dossier->status);
        $this->assertCount(1, $dossier->versions);
        $this->assertCount(1, $dossier->events);

        $this->patch(route('engineering.update', $dossier), $this->payload($opportunity, $installation, [
            'expected_version' => 1,
            'proposed_solution' => 'Replace the main board, separate critical circuits and add surge protection.',
            'change_note' => 'Surge protection added after the site risk review.',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $dossier->refresh();
        $this->assertSame(2, $dossier->current_version);
        $this->assertCount(2, $dossier->versions);
        $this->assertSame('Replace the main board and separate critical circuits.', $dossier->versions()->where('version', 1)->sole()->snapshot['proposed_solution']);
        $fingerprint = $dossier->versions()->where('version', 2)->sole()->fingerprint;

        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'submit', 'expected_version' => 2, 'note' => 'Technical evidence is complete and ready for independent review.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'approve', 'expected_version' => 2, 'expected_fingerprint' => $fingerprint,
            'note' => 'Calculation, scope and applicable standards have been reviewed.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $dossier->refresh()->status);
        $this->assertSame($admin->id, $dossier->approved_by);

        $this->patch(route('engineering.update', $dossier), $this->payload($opportunity, $installation, [
            'expected_version' => 2, 'change_note' => 'Attempt to alter an approved version.',
        ]))->assertSessionHasErrors('status');

        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'handover', 'expected_version' => 2, 'expected_fingerprint' => $fingerprint,
            'note' => 'Approved version handed to the project team for execution planning.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $dossier->refresh();
        $this->assertSame('handed_over', $dossier->status);
        $this->assertSame($admin->id, $dossier->handed_over_by);
        $this->assertSame('survey_booked', $installation->fresh()->status);
        $this->assertSame(0, WorkOrder::count());
        $this->assertCount(5, $dossier->events);

        $this->get(route('engineering.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('engineering/index')
            ->where('dossiers.0.code', 'ENG-2026-000001')
            ->where('dossiers.0.status', 'handed_over')
            ->where('dossiers.0.current_fingerprint', $fingerprint)
            ->has('dossiers.0.versions', 2)
            ->has('dossiers.0.events', 5));
        $this->getJson(route('global-search', ['q' => 'ENG-2026']))
            ->assertOk()
            ->assertJsonPath('results.0.group', 'Engineering')
            ->assertJsonPath('results.0.href', '/engineering#dossier-'.$dossier->id);
    }

    public function test_stale_or_unlinked_dossiers_cannot_be_approved_or_handed_over(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $opportunity = Opportunity::create([
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'code' => 'OPP-ENG-2',
            'title' => 'Security study',
            'stage' => 'survey',
        ]);
        $this->actingAs($admin)->post(route('engineering.store'), $this->payload($opportunity, null, [
            'business_line' => 'security',
            'proposed_solution' => 'Grade 2 alarm with perimeter detection.',
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $dossier = EngineeringDossier::firstOrFail();
        $fingerprint = $dossier->versions()->sole()->fingerprint;

        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'submit', 'expected_version' => 1, 'note' => 'Evidence prepared for technical review.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'approve', 'expected_version' => 1, 'expected_fingerprint' => str_repeat('0', 64),
            'note' => 'Attempt to approve a stale technical snapshot.',
        ])->assertSessionHasErrors('expected_fingerprint');
        $originalTitle = $dossier->title;
        $dossier->update(['title' => 'Content altered outside its governed version']);
        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'approve', 'expected_version' => 1, 'expected_fingerprint' => $fingerprint,
            'note' => 'Attempt to approve content that no longer matches its version.',
        ])->assertSessionHasErrors('expected_fingerprint');
        $dossier->update(['title' => $originalTitle]);
        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'approve', 'expected_version' => 1, 'expected_fingerprint' => $fingerprint,
            'note' => 'Current technical snapshot reviewed and approved.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'handover', 'expected_version' => 1, 'expected_fingerprint' => $fingerprint,
            'note' => 'Attempt to hand over without an execution project.',
        ])->assertSessionHasErrors('installation_id');
        $this->assertSame('approved', $dossier->fresh()->status);
    }

    public function test_technicians_can_consult_engineering_but_cannot_change_it(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'survey_booked',
            'site_address_line_1' => 'Calle Solar 1',
        ]);

        $this->actingAs($technician)->get(route('engineering.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false));
        $this->post(route('engineering.store'), $this->payload(null, $installation))->assertForbidden();
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(?Opportunity $opportunity, ?Installation $installation, array $overrides = []): array
    {
        return [
            'opportunity_id' => $opportunity?->id,
            'installation_id' => $installation?->id,
            'title' => 'Main technical study',
            'business_line' => 'electrical',
            'kind' => 'design',
            'scope' => 'Define the safe execution scope and technical acceptance criteria.',
            'site_conditions' => 'Existing low-voltage installation in service.',
            'calculations' => 'Demand, voltage drop and protection coordination checked.',
            'proposed_solution' => null,
            'risks' => 'Live installation and continuity constraints.',
            'standards' => 'REBT and applicable UNE standards.',
            'conclusions' => 'Execution is feasible under the recorded conditions.',
            ...$overrides,
        ];
    }
}
