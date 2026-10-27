<?php

namespace Tests\Feature;

use App\Jobs\GeocodeInstallation;
use App\Models\CrmActivity;
use App\Models\CrmSetting;
use App\Models\FieldServiceProfile;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\Person;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Geocoding\NominatimGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_user_can_manage_the_opportunity_pipeline(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'company_name' => 'Solar Pipeline SL']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'lead', 'site_address_line_1' => 'Calle Sol 1']);

        $this->actingAs($user)->post(route('opportunities.store'), [
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'owner_user_id' => $user->id,
            'title' => 'Photovoltaic system 10 kW',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 15000,
            'expected_close_on' => today()->addMonth()->toDateString(),
            'source' => 'referral',
        ])->assertRedirect();

        $opportunity = Opportunity::firstOrFail();
        $this->assertMatchesRegularExpression('/^OPP-\d{4}-\d{5}$/', $opportunity->code);
        $this->assertSame($user->id, $opportunity->created_by);
        $this->assertSame($user->id, $opportunity->owner_user_id);
        $this->assertNotNull($opportunity->expected_close_on);
        $this->assertNotNull($opportunity->nextActivity);
        $this->assertGreaterThan(0, $opportunity->commercial_score);

        $this->get(route('opportunities.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('opportunities/index')
            ->has('opportunities', 1)
            ->where('opportunities.0.stage', 'qualified')
            ->where('opportunities.0.commercial_score', 70)
            ->missing('opportunities.0.score_breakdown')
            ->missing('opportunities.0.stage_history')
            ->where('section', 'pipeline')
            ->where('filters.view', 'kanban')
            ->where('metrics.pipeline_value', 15000)
            ->where('metrics.weighted_forecast', 3750));

        $this->patch(route('opportunities.update', $opportunity), [
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'owner_user_id' => $user->id,
            'title' => 'Photovoltaic system 10 kW',
            'stage' => 'won',
            'probability' => 100,
            'estimated_value' => 15000,
            'change_note' => 'El cliente ha confirmado la adjudicación.',
        ])->assertRedirect();

        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'stage' => 'won', 'probability' => 100]);
        $this->delete(route('opportunities.destroy', $opportunity))->assertRedirect();
        $this->assertSoftDeleted('opportunities', ['id' => $opportunity->id]);
        $this->get(route('opportunities.index', ['archived' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.id', $opportunity->id));
        $this->post(route('opportunities.restore', $opportunity->id))->assertRedirect(route('opportunities.index'));
        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'deleted_at' => null]);
        $this->assertSame(
            [
                ['from_stage' => null, 'to_stage' => 'qualified'],
                ['from_stage' => 'qualified', 'to_stage' => 'won'],
            ],
            OpportunityStageHistory::query()
                ->where('opportunity_id', $opportunity->id)
                ->oldest('id')
                ->get(['from_stage', 'to_stage'])
                ->toArray(),
        );
    }

    public function test_opportunity_dossier_preserves_authorship_changes_notes_and_quote_versions(): void
    {
        $creator = User::factory()->create(['role' => 'commercial', 'name' => 'Creadora']);
        $editor = User::factory()->create(['role' => 'commercial', 'name' => 'Editor']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $creator->id,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
            'code' => 'OPP-DOSSIER-1',
            'title' => 'Original scope',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 5000,
        ]);
        $quote = Quote::create([
            'opportunity_id' => $opportunity->id,
            'person_id' => $customer->id,
            'created_by' => $creator->id,
            'number' => 'PRE-DOSSIER-1',
            'version' => 1,
            'status' => 'draft',
            'issue_date' => today(),
            'subtotal' => 5000,
            'discount_amount' => 0,
            'tax_amount' => 1050,
            'amount' => 6050,
        ]);
        $quote->versions()->create([
            'version' => 1,
            'created_by' => $creator->id,
            'change_note' => 'Primera propuesta económica.',
            'subtotal' => 5000,
            'discount_amount' => 0,
            'tax_amount' => 1050,
            'amount' => 6050,
            'lines' => [],
        ]);

        $payload = [
            'person_id' => $customer->id,
            'owner_user_id' => $editor->id,
            'title' => 'Expanded scope',
            'stage' => 'proposal',
            'probability' => 60,
            'estimated_value' => 6500,
        ];
        $this->actingAs($editor)->patch(route('opportunities.update', $opportunity), $payload)
            ->assertSessionHasErrors('change_note');
        $this->patch(route('opportunities.update', $opportunity), [
            ...$payload,
            'change_note' => 'Se amplía el alcance tras la visita técnica.',
        ])->assertRedirect();
        $this->post(route('opportunities.notes.store', $opportunity), [
            'note' => 'El cliente solicita comparar dos alternativas.',
        ])->assertRedirect();

        $this->assertSame($editor->id, $opportunity->refresh()->updated_by);
        $this->assertDatabaseHas('opportunity_stage_histories', [
            'opportunity_id' => $opportunity->id,
            'changed_by' => $editor->id,
            'from_stage' => 'qualified',
            'to_stage' => 'proposal',
            'reason' => 'Se amplía el alcance tras la visita técnica.',
        ]);
        $this->assertDatabaseHas('opportunity_events', [
            'opportunity_id' => $opportunity->id,
            'actor_id' => $editor->id,
            'event_type' => 'details_updated',
            'note' => 'Se amplía el alcance tras la visita técnica.',
        ]);
        $this->assertDatabaseHas('opportunity_events', [
            'opportunity_id' => $opportunity->id,
            'actor_id' => $editor->id,
            'event_type' => 'note_added',
            'note' => 'El cliente solicita comparar dos alternativas.',
        ]);

        $this->get(route('opportunities.show', $opportunity))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('opportunities/show')
                ->where('opportunity.creator.name', 'Creadora')
                ->where('opportunity.updater.name', 'Editor')
                ->where('opportunity.score_breakdown.owner_assigned.met', true)
                ->where('opportunity.quote.versions.0.change_note', 'Primera propuesta económica.')
                ->where('timeline', fn ($timeline) => collect($timeline)->contains(
                    fn (array $item) => $item['type'] === 'note_added' && $item['actor']['name'] === 'Editor',
                ))
                ->has('timeline', 7));

        $this->put(route('quotes.document.update', $quote), [])
            ->assertSessionHasErrors('change_note');
    }

    public function test_opportunity_creation_finds_an_existing_customer_by_normalized_mobile(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Ana',
            'phone' => '+34 600 123 123',
            'email' => 'ana@example.test',
        ]);

        $this->assertSame('34600123123', $customer->phone_normalized);
        $this->actingAs($user)
            ->getJson(route('crm-customers.lookup', ['phone' => '600-123-123']))
            ->assertOk()
            ->assertJsonPath('normalized_phone', '34600123123')
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.email', 'ana@example.test');

        $this->post(route('opportunities.store'), [
            'customer_phone' => '(600) 123 123',
            'title' => 'Battery expansion',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 4500,
        ])->assertRedirect();

        $this->assertSame(1, Person::where('category', 'customer')->count());
        $this->assertDatabaseHas('opportunities', [
            'person_id' => $customer->id,
            'title' => 'Battery expansion',
        ]);
    }

    public function test_opportunity_creation_builds_a_customer_when_the_mobile_has_no_match(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $payload = [
            'customer_phone' => '611 222 333',
            'title' => 'Industrial self-consumption',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 25000,
        ];

        $this->actingAs($user)->post(route('opportunities.store'), $payload)
            ->assertSessionHasErrors('customer_first_name');

        $this->post(route('opportunities.store'), [
            ...$payload,
            'customer_company_name' => 'Nueva Industria SL',
            'customer_email' => 'VENTAS@NUEVA-INDUSTRIA.TEST',
            'customer_preferred_contact_channel' => 'whatsapp',
            'customer_consent_status' => 'granted',
            'customer_consent_channel' => 'whatsapp',
            'customer_consent_notes' => 'Autorización verbal durante la llamada inicial.',
            'source' => 'referral',
            'next_action_type' => 'meeting',
            'next_action_title' => 'Reunión de cualificación',
            'next_action_priority' => 'high',
            'next_action_due_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $customer = Person::where('phone_normalized', '34611222333')->sole();
        $this->assertSame('Nueva Industria SL', $customer->first_name);
        $this->assertSame('Nueva Industria SL', $customer->company_name);
        $this->assertSame('ventas@nueva-industria.test', $customer->email);
        $this->assertSame('lead', $customer->lifecycle_status);
        $this->assertSame('opportunity_form', $customer->data_source);
        $this->assertSame('referral', $customer->lead_source);
        $this->assertSame('whatsapp', $customer->preferred_contact_channel);
        $this->assertFalse($customer->do_not_contact);
        $this->assertDatabaseHas('customer_consents', [
            'person_id' => $customer->id,
            'recorded_by' => $user->id,
            'channel' => 'whatsapp',
            'status' => 'granted',
            'lawful_basis' => 'consent',
            'source' => 'opportunity_form',
        ]);
        $this->assertDatabaseHas('opportunities', [
            'person_id' => $customer->id,
            'title' => 'Industrial self-consumption',
        ]);
        $opportunity = Opportunity::where('title', 'Industrial self-consumption')->firstOrFail();
        $this->assertDatabaseHas('crm_activities', [
            'opportunity_id' => $opportunity->id,
            'assigned_user_id' => $user->id,
            'type' => 'meeting',
            'title' => 'Reunión de cualificación',
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $this->post(route('opportunities.store'), [
            ...$payload,
            'customer_phone' => '+34 611 222 333',
            'title' => 'Industrial storage',
        ])->assertRedirect();

        $this->assertSame(1, Person::where('phone_normalized', '34611222333')->count());
        $this->assertSame(2, Opportunity::where('person_id', $customer->id)->count());
    }

    public function test_opportunity_creation_records_a_customer_commercial_refusal(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Ana',
            'phone' => '622 333 444',
            'preferred_contact_channel' => 'phone',
        ]);

        $this->actingAs($user)->post(route('opportunities.store'), [
            'customer_phone' => '+34 622 333 444',
            'customer_consent_status' => 'denied',
            'customer_consent_channel' => 'phone',
            'customer_consent_notes' => 'Solicita no recibir comunicaciones comerciales.',
            'title' => 'Technical assessment only',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 1200,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($customer->refresh()->do_not_contact);
        $this->assertDatabaseHas('customer_consents', [
            'person_id' => $customer->id,
            'status' => 'denied',
            'channel' => 'phone',
            'source' => 'opportunity_form',
        ]);
        $this->assertSame(1, Opportunity::where('person_id', $customer->id)->count());
    }

    public function test_opportunity_rejects_an_installation_from_another_customer(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $other = Person::create(['category' => 'customer', 'first_name' => 'Luis']);
        $installation = Installation::create(['person_id' => $other->id, 'service_type' => 'solar', 'status' => 'lead', 'site_address_line_1' => 'Calle Sol 2']);

        $this->actingAs($user)->post(route('opportunities.store'), [
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'title' => 'Invalid link',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 1000,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('opportunities', ['title' => 'Invalid link']);
    }

    public function test_lost_opportunity_requires_a_reason(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);

        $this->actingAs($user)->post(route('opportunities.store'), [
            'person_id' => $customer->id,
            'title' => 'Lost lead',
            'stage' => 'lost',
            'probability' => 0,
            'estimated_value' => 1000,
        ])->assertSessionHasErrors('lost_reason');
    }

    public function test_closing_an_opportunity_cancels_open_actions_and_terminal_stages_cannot_reopen(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'created_by' => $user->id,
            'code' => 'OPP-WORKFLOW-1',
            'title' => 'Controlled transition',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 5000,
        ]);
        $activity = CrmActivity::create([
            'opportunity_id' => $opportunity->id,
            'person_id' => $customer->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'call',
            'title' => 'Pending follow-up',
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);

        $payload = [
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'title' => $opportunity->title,
            'stage' => 'won',
            'probability' => 50,
            'estimated_value' => 5000,
            'change_note' => 'La propuesta ha sido aceptada por el cliente.',
        ];

        $this->actingAs($user)->patch(route('opportunities.update', $opportunity), $payload)->assertRedirect();

        $this->assertSame('won', $opportunity->refresh()->stage);
        $this->assertSame(100, $opportunity->probability);
        $this->assertSame('cancelled', $activity->refresh()->status);
        $this->assertNotNull($activity->blocked_reason);
        $this->assertDatabaseHas('opportunity_events', [
            'opportunity_id' => $opportunity->id,
            'actor_id' => $user->id,
            'event_type' => 'activity_updated',
        ]);

        $this->patch(route('opportunities.update', $opportunity), [...$payload, 'stage' => 'new', 'probability' => 10])
            ->assertSessionHasErrors('stage');
        $this->post(route('opportunities.activities.store', $opportunity), [
            'type' => 'call',
            'title' => 'Should not be created',
            'priority' => 'normal',
            'due_at' => now()->addDay(),
        ])->assertUnprocessable();

        $this->assertSame('won', $opportunity->fresh()->stage);
        $this->assertDatabaseMissing('crm_activities', ['title' => 'Should not be created']);
    }

    public function test_other_source_and_loss_reason_preserve_their_details(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $payload = [
            'person_id' => $customer->id,
            'title' => 'Custom source lead',
            'stage' => 'lost',
            'probability' => 0,
            'estimated_value' => 1000,
            'source' => 'other',
            'lost_reason' => 'other',
        ];

        $this->actingAs($user)->post(route('opportunities.store'), $payload)
            ->assertSessionHasErrors(['source_detail', 'lost_reason_detail']);

        $this->post(route('opportunities.store'), [
            ...$payload,
            'source_detail' => 'Feria local',
            'lost_reason_detail' => 'Proyecto aplazado por licencia',
        ])->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'source' => 'other',
            'source_detail' => 'Feria local',
            'lost_reason' => 'other',
            'lost_reason_detail' => 'Proyecto aplazado por licencia',
        ]);
    }

    public function test_technician_cannot_access_opportunities(): void
    {
        $user = User::factory()->create(['role' => 'technician']);

        $this->actingAs($user)->get(route('opportunities.index'))->assertForbidden();
    }

    public function test_admin_can_configure_explainable_scoring_and_commercial_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $commercial->id,
            'code' => 'OPP-SCORE-1',
            'title' => 'Explainable score',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 0,
        ]);
        $settings = [
            'stale_after_days' => 30,
            'lead_response_sla_minutes' => 120,
            'lead_response_escalation_minutes' => 180,
            'customer_contactable_weight' => 0,
            'source_identified_weight' => 0,
            'owner_assigned_weight' => 100,
            'expected_close_set_weight' => 0,
            'next_action_scheduled_weight' => 0,
            'estimated_value_set_weight' => 0,
            'advanced_stage_weight' => 0,
        ];

        $this->actingAs($commercial)->patch(route('crm-settings.update'), $settings)->assertForbidden();
        $this->actingAs($admin)->patch(route('crm-settings.update'), $settings)->assertRedirect();

        $this->assertSame(30, CrmSetting::current()->stale_after_days);
        $this->assertSame(120, CrmSetting::current()->lead_response_sla_minutes);
        $this->assertSame(180, CrmSetting::current()->lead_response_escalation_minutes);
        $this->assertSame(100, $opportunity->refresh()->commercial_score);
        $this->get(route('opportunities.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canConfigureCrm', true)
                ->where('crmSettings.stale_after_days', 30)
                ->where('crmSettings.lead_response_sla_minutes', 120)
                ->where('crmSettings.lead_response_escalation_minutes', 180)
                ->missing('opportunities.0.score_breakdown'));
        $this->get(route('opportunities.show', $opportunity))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('opportunity.score_breakdown.owner_assigned.met', true)
                ->where('opportunity.score_breakdown.owner_assigned.points', 100)
                ->where('opportunity.score_breakdown.estimated_value_set.met', false));
    }

    public function test_crm_scoring_weights_must_total_one_hundred(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('crm-settings.update'), [
            'stale_after_days' => 14,
            'lead_response_sla_minutes' => 240,
            'lead_response_escalation_minutes' => 120,
            'customer_contactable_weight' => 20,
            'source_identified_weight' => 10,
            'owner_assigned_weight' => 15,
            'expected_close_set_weight' => 15,
            'next_action_scheduled_weight' => 20,
            'estimated_value_set_weight' => 10,
            'advanced_stage_weight' => 9,
        ])->assertSessionHasErrors('weights');

        $this->assertSame(10, CrmSetting::current()->advanced_stage_weight);
    }

    public function test_commercial_user_can_schedule_and_filter_next_actions(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $otherUser = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'code' => 'OPP-ACTION-1',
            'title' => 'Solar follow-up',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 5000,
        ]);
        Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $otherUser->id,
            'code' => 'OPP-OTHER-1',
            'title' => 'Other owner',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 1000,
        ]);

        $this->actingAs($user)->post(route('opportunities.activities.store', $opportunity), [
            'assigned_user_id' => $user->id,
            'type' => 'call',
            'title' => 'Call customer',
            'priority' => 'high',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $activity = CrmActivity::firstWhere('opportunity_id', $opportunity->id);
        $this->assertNotNull($activity);
        $this->assertSame($customer->id, $activity->person_id);

        $this->get(route('opportunities.index', ['owner' => 'me', 'search' => 'follow-up']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.id', $opportunity->id)
                ->where('opportunities.0.next_activity.title', 'Call customer')
                ->where('filters.owner', 'me'));

        $this->get(route('opportunities.index', ['attention' => 'no_action']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.code', 'OPP-OTHER-1'));
    }

    public function test_an_action_can_be_completed_from_the_dossier_and_advances_to_the_next_planned_action(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'code' => 'OPP-COMPLETE-1',
            'title' => 'Seguimiento trazable',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 5000,
        ]);
        $current = CrmActivity::create([
            'opportunity_id' => $opportunity->id,
            'person_id' => $customer->id,
            'assigned_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'type' => 'call',
            'title' => 'Primera llamada',
            'priority' => 'high',
            'status' => 'in_progress',
            'due_at' => now()->addHour(),
        ]);
        $plannedNext = CrmActivity::create([
            'opportunity_id' => $opportunity->id,
            'person_id' => $customer->id,
            'assigned_user_id' => $commercial->id,
            'created_by' => $commercial->id,
            'type' => 'email',
            'title' => 'Enviar documentación',
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDays(2),
        ]);

        $this->actingAs($technician)->patch(route('opportunities.activities.complete', [$opportunity, $current]), [
            'completion_notes' => 'Cliente contactado.',
            'schedule_next' => false,
        ])->assertForbidden();

        $this->actingAs($commercial)->patch(route('opportunities.activities.complete', [$opportunity, $current]), [
            'completion_notes' => 'El cliente confirma interés y solicita la ficha técnica.',
            'schedule_next' => false,
        ])->assertRedirect();

        $current->refresh();
        $this->assertSame('completed', $current->status);
        $this->assertSame($commercial->id, $current->completed_by);
        $this->assertSame('El cliente confirma interés y solicita la ficha técnica.', $current->completion_notes);
        $this->assertNotNull($current->completed_at);
        $this->assertSame($plannedNext->id, $opportunity->fresh()->nextActivity->id);
        $this->assertDatabaseHas('opportunity_events', [
            'opportunity_id' => $opportunity->id,
            'actor_id' => $commercial->id,
            'event_type' => 'activity_completed',
            'note' => 'El cliente confirma interés y solicita la ficha técnica.',
        ]);

        $this->patch(route('opportunities.activities.complete', [$opportunity, $plannedNext]), [
            'completion_notes' => 'Documentación enviada y reunión acordada.',
            'schedule_next' => true,
            'next_action_type' => 'meeting',
            'next_action_title' => 'Revisar propuesta con el cliente',
            'next_action_priority' => 'high',
            'next_action_due_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'next_action_assigned_user_id' => $commercial->id,
            'next_action_notes' => 'Llevar comparativa económica.',
        ])->assertRedirect();

        $next = $opportunity->fresh()->nextActivity;
        $this->assertSame('Revisar propuesta con el cliente', $next->title);
        $this->assertSame('meeting', $next->type);
        $this->assertSame('Llevar comparativa económica.', $next->notes);
        $this->assertSame($commercial->id, $next->assigned_user_id);

        $this->patch(route('opportunities.activities.complete', [$opportunity, $current]), [
            'completion_notes' => 'Segundo intento indebido.',
            'schedule_next' => false,
        ])->assertUnprocessable();
    }

    public function test_first_response_sla_is_started_measured_and_filterable(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        CrmSetting::current()->update(['lead_response_sla_minutes' => 60]);

        $this->travelTo('2026-08-17 08:00:00');
        $this->actingAs($user)->post(route('opportunities.store'), [
            'person_id' => $customer->id,
            'title' => 'Lead answered on time',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 3000,
        ])->assertRedirect();

        $answered = Opportunity::where('title', 'Lead answered on time')->firstOrFail();
        $this->assertSame('2026-08-17 09:00:00', $answered->first_response_due_at?->format('Y-m-d H:i:s'));

        $this->travelTo('2026-08-17 08:30:00');
        $this->patch(route('activities.update', $answered->nextActivity), [
            'status' => 'in_progress',
        ])->assertRedirect();
        $this->assertSame('2026-08-17 08:30:00', $answered->refresh()->first_responded_at?->format('Y-m-d H:i:s'));

        $this->travelTo('2026-08-17 10:00:00');
        $this->post(route('opportunities.store'), [
            'person_id' => $customer->id,
            'title' => 'Lead outside SLA',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 4000,
        ])->assertRedirect();
        $overdue = Opportunity::where('title', 'Lead outside SLA')->firstOrFail();

        $this->travelTo('2026-08-17 11:01:00');
        $this->get(route('opportunities.index', ['section' => 'analysis']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics.response_sla_breached', 1)
                ->where('metrics.response_sla_pending', 0)
                ->where('metrics.response_sla_met_rate', 50)
                ->where('metrics.average_first_response_minutes', 30)
                ->where('section', 'analysis')
                ->has('responsePerformance', 1)
                ->where('responsePerformance.0.owner_id', $user->id)
                ->where('responsePerformance.0.total', 2)
                ->where('responsePerformance.0.met', 1)
                ->where('responsePerformance.0.breached', 1)
                ->where('responsePerformance.0.pending', 0)
                ->where('responsePerformance.0.met_rate', 50)
                ->where('responsePerformance.0.average_minutes', 30)
                ->where('opportunities.0.id', $overdue->id)
                ->where('opportunities.0.response_sla_status', 'breached')
                ->where('opportunities.1.id', $answered->id)
                ->where('opportunities.1.response_sla_status', 'met')
                ->where('opportunities.1.first_response_minutes', 30));

        $this->get(route('opportunities.index', ['attention' => 'response_sla']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.id', $overdue->id)
                ->where('filters.attention', 'response_sla'));

        $this->travelBack();
    }

    public function test_pipeline_metrics_use_the_selected_commercial_period(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);

        $this->travelTo(now()->subDays(40));
        $won = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'code' => 'OPP-METRIC-WON',
            'title' => 'Won project',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 12000,
        ]);
        $lost = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'code' => 'OPP-METRIC-LOST',
            'title' => 'Lost project',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 4000,
        ]);
        $this->travelBack();

        $won->update(['stage' => 'won', 'probability' => 100]);
        $lost->update(['stage' => 'lost', 'probability' => 0, 'lost_reason' => 'price']);

        $this->actingAs($user)->get(route('opportunities.index', ['period' => '30']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics.won_value', 12000)
                ->where('metrics.new_leads', 0)
                ->where('metrics.won_count', 1)
                ->where('metrics.lost_count', 1)
                ->where('metrics.conversion_rate', 50)
                ->where('metrics.average_cycle_days', 40)
                ->where('filters.period', '30'));
    }

    public function test_stale_detection_uses_stage_age_and_forecast_explains_its_composition(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $forecastDate = today()->addMonth()->startOfMonth();

        $this->travelTo(now()->subDays(20));
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'created_by' => $user->id,
            'code' => 'OPP-FORECAST-1',
            'title' => 'Forecast explicable',
            'stage' => 'qualified',
            'probability' => 40,
            'estimated_value' => 10000,
            'expected_close_on' => $forecastDate,
        ]);
        $this->travelBack();
        $opportunity->update(['notes' => 'Edited today without changing stage']);
        CrmActivity::create([
            'opportunity_id' => $opportunity->id,
            'person_id' => $customer->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'call',
            'title' => 'Seguimiento',
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->get(route('opportunities.index', ['section' => 'analysis']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics.stale', 1)
                ->where('opportunities.0.is_stale', true)
                ->where('opportunities.0.days_in_stage', fn ($days) => $days >= 20)
                ->where('forecast.0.month', $forecastDate->format('Y-m'))
                ->where('forecast.0.owner_id', $user->id)
                ->where('forecast.0.amount', 10000)
                ->where('forecast.0.weighted_amount', 4000)
                ->where('forecast.0.average_probability', 40)
                ->where('forecast.0.opportunities.0.code', 'OPP-FORECAST-1'));

        $this->get(route('opportunities.index', ['attention' => 'stale']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.id', $opportunity->id));
    }

    public function test_opportunity_can_prepare_one_quote_and_sync_its_result(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $product = $this->stockProduct('PANEL-450', '450 W panel', 120);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'lead', 'site_address_line_1' => 'Calle Sol 3']);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'owner_user_id' => $user->id,
            'code' => 'OPP-2026-00001',
            'title' => 'Solar installation 8 kW',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 12000,
        ]);

        $this->actingAs($user)->get(route('quotes.create', ['opportunity' => $opportunity->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/form')
                ->where('defaultCustomerId', $customer->id)
                ->where('opportunityDraft.id', $opportunity->id)
                ->where('opportunityDraft.installation_id', $installation->id)
                ->where('opportunityDraft.estimated_value', 12000));

        $this->post(route('quotes.store'), [
            'opportunity_id' => $opportunity->id,
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'issue_date' => now()->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'unit_id' => $product->unit_id,
                'tax_rate_id' => $product->tax_rate_id,
                'description' => $opportunity->title,
                'quantity' => 10,
                'unit_price' => 1200,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        $quote = Quote::firstOrFail();
        $this->assertSame($opportunity->id, $quote->opportunity_id);
        $this->assertSame(1, $quote->versions()->count());
        $this->assertSame('not_required', $quote->approval_status);
        $this->assertSame('10800.00', $quote->expected_margin_amount);
        $this->assertSame('proposal', $opportunity->refresh()->stage);
        $this->assertDatabaseHas('crm_activities', [
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'assigned_user_id' => $user->id,
            'type' => 'reminder',
            'status' => 'pending',
        ]);

        $this->get(route('quotes.create', ['opportunity' => $opportunity->id]))->assertStatus(422);
        $this->delete(route('opportunities.destroy', $opportunity))->assertStatus(422);
        $this->patch(route('quotes.status.update', $quote), ['status' => 'accepted'])->assertRedirect();
        $this->assertSame('won', $opportunity->refresh()->stage);
        $this->assertSame(100, $opportunity->probability);
        $this->assertSame('approved', $installation->refresh()->status);
        $this->assertDatabaseHas('installation_material_requirements', [
            'installation_id' => $installation->id,
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'planned_quantity' => 10,
            'planned_unit_cost' => 120,
        ]);
    }

    public function test_accepted_quote_can_create_the_installation_survey_and_work_order(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'skills' => ['solar', 'electrical']]);
        $product = $this->stockProduct('INV-5K', '5 kW inverter', 950);
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Ana',
            'address_line_1' => 'Calle del Sol 15',
            'city' => 'Cordoba',
            'province_state' => 'Cordoba',
            'postal_code' => '14001',
            'country' => 'Spain',
        ]);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'code' => 'OPP-2026-00002',
            'title' => 'Solar installation 5 kW',
            'stage' => 'won',
            'probability' => 100,
            'estimated_value' => 9000,
        ]);
        $quote = Quote::create([
            'opportunity_id' => $opportunity->id,
            'person_id' => $customer->id,
            'number' => 'PRE-2026-00002',
            'status' => 'accepted',
            'issue_date' => today(),
            'subtotal' => 9000,
            'discount_amount' => 0,
            'tax_amount' => 1890,
            'amount' => 10890,
            'customer_snapshot' => [],
        ]);
        $quote->lines()->create([
            'product_id' => $product->id,
            'unit_id' => $product->unit_id,
            'tax_rate_id' => $product->tax_rate_id,
            'position' => 1,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 1400,
            'discount_percent' => 0,
            'tax_percent' => 21,
            'subtotal' => 1400,
            'discount_amount' => 0,
            'tax_amount' => 294,
            'total' => 1694,
        ]);
        $scheduledAt = now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s');

        $response = $this->actingAs($user)->post(route('quotes.project.store', $quote), [
            'service_type' => 'solar',
            'technician_user_id' => $technician->id,
            'scheduled_at' => $scheduledAt,
            'site_name' => 'Ana solar project',
            'site_address_line_1' => 'Calle del Sol',
            'site_street_number' => '15',
            'site_city' => 'Cordoba',
            'site_province_state' => 'Cordoba',
            'site_postal_code' => '14001',
            'site_country' => 'Spain',
        ]);

        $installation = Installation::latest('id')->firstOrFail();
        $response->assertRedirect(route('installations.show', $installation));
        $this->assertSame('survey_booked', $installation->status);
        $this->assertSame('solar', $installation->service_type);
        $this->assertSame('15', $installation->site_street_number);
        $this->assertSame('10890.00', $installation->estimated_budget);
        $this->assertSame($installation->id, $quote->refresh()->installation_id);
        $this->assertSame($installation->id, $opportunity->refresh()->installation_id);
        $this->assertDatabaseHas('installation_material_requirements', [
            'installation_id' => $installation->id,
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'planned_quantity' => 1,
            'planned_unit_cost' => 950,
        ]);

        $order = WorkOrder::where('installation_id', $installation->id)->firstOrFail();
        $this->assertSame($technician->id, $order->assigned_user_id);
        $this->assertSame('planned', $order->status);
        $this->assertGreaterThan(0, $order->checklistItems()->count());
        $this->assertDatabaseHas('crm_activities', [
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'type' => 'visit',
            'priority' => 'high',
        ]);
        Queue::assertPushed(GeocodeInstallation::class, fn (GeocodeInstallation $job) => $job->installationId === $installation->id);

        $this->post(route('quotes.project.store', $quote), [])->assertRedirect(route('installations.show', $installation));
        $this->assertSame(1, WorkOrder::where('installation_id', $installation->id)->count());
        $this->assertSame(1, Installation::where('person_id', $customer->id)->count());
    }

    public function test_renewals_and_additional_sales_keep_their_commercial_origin(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $otherCustomer = Person::create(['category' => 'customer', 'first_name' => 'Luis']);
        $origin = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'created_by' => $user->id,
            'code' => 'OPP-ORIGIN-1',
            'title' => 'Original installation',
            'stage' => 'won',
            'probability' => 100,
            'estimated_value' => 12000,
        ]);
        $otherOrigin = Opportunity::create([
            'person_id' => $otherCustomer->id,
            'owner_user_id' => $user->id,
            'created_by' => $user->id,
            'code' => 'OPP-ORIGIN-2',
            'title' => 'Other customer installation',
            'stage' => 'won',
            'probability' => 100,
            'estimated_value' => 8000,
        ]);
        $payload = [
            'person_id' => $customer->id,
            'title' => 'Annual maintenance renewal',
            'commercial_kind' => 'renewal',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 900,
        ];

        $this->actingAs($user)->post(route('opportunities.store'), $payload)
            ->assertSessionHasErrors(['parent_opportunity_id', 'renewal_due_on']);

        $this->post(route('opportunities.store'), [
            ...$payload,
            'parent_opportunity_id' => $otherOrigin->id,
            'renewal_due_on' => today()->addDays(20)->toDateString(),
        ])->assertUnprocessable();

        $this->post(route('opportunities.store'), [
            ...$payload,
            'parent_opportunity_id' => $origin->id,
            'renewal_due_on' => today()->addDays(20)->toDateString(),
        ])->assertRedirect();

        $renewal = Opportunity::where('title', 'Annual maintenance renewal')->sole();
        $this->assertSame('renewal', $renewal->commercial_kind);
        $this->assertSame($origin->id, $renewal->parent_opportunity_id);
        $this->assertSame($origin->id, $renewal->parentOpportunity->id);
        $this->assertNotNull($renewal->nextActivity);

        $this->get(route('opportunities.index', ['commercial_kind' => 'renewal']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.id', $renewal->id)
                ->where('opportunities.0.parent_opportunity.id', $origin->id)
                ->where('metrics.renewals_due', 1));
    }

    public function test_recurring_opportunity_requires_periodicity_and_renewal_date(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $payload = [
            'person_id' => $customer->id,
            'title' => 'Monitoring subscription',
            'commercial_kind' => 'recurring',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 50,
        ];

        $this->actingAs($user)->post(route('opportunities.store'), $payload)
            ->assertSessionHasErrors(['recurrence_interval', 'renewal_due_on']);

        $this->post(route('opportunities.store'), [
            ...$payload,
            'recurrence_interval' => 'monthly',
            'renewal_due_on' => today()->addMonth()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'title' => 'Monitoring subscription',
            'commercial_kind' => 'recurring',
            'recurrence_interval' => 'monthly',
        ]);
    }

    private function stockProduct(string $sku, string $name, float $cost): Product
    {
        return Product::create([
            'type' => 'product',
            'sku' => $sku,
            'name' => $name,
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => $cost * 1.5,
            'cost_price' => $cost,
            'track_stock' => true,
            'track_serials' => false,
            'is_active' => true,
        ]);
    }

    public function test_project_geocoding_prefers_an_exact_house_number_match(): void
    {
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'survey_booked',
            'site_address_line_1' => 'Calle del Sol',
            'site_street_number' => '15',
            'site_city' => 'Cordoba',
            'site_country' => 'Spain',
        ]);
        $geocoder = $this->mock(NominatimGeocoder::class);
        $geocoder->shouldReceive('search')->once()->andReturn([
            ['provider' => 'nominatim', 'place_id' => 'wrong', 'osm_type' => 'node', 'osm_id' => '1', 'latitude' => '37.8800000', 'longitude' => '-4.7800000', 'display_name' => 'Calle del Sol', 'house_number_match' => false],
            ['provider' => 'nominatim', 'place_id' => 'exact', 'osm_type' => 'node', 'osm_id' => '2', 'latitude' => '37.8812345', 'longitude' => '-4.7712345', 'display_name' => 'Calle del Sol 15', 'house_number_match' => true],
        ]);

        (new GeocodeInstallation($installation->id))->handle($geocoder);

        $installation->refresh();
        $this->assertSame('37.8812345', $installation->site_latitude);
        $this->assertSame('-4.7712345', $installation->site_longitude);
        $this->assertSame('exact', $installation->site_geocoding_place_id);
        $this->assertNotNull($installation->site_geocoded_at);
    }
}
