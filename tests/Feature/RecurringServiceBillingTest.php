<?php

namespace Tests\Feature;

use App\Models\CustomerPortalAccess;
use App\Models\CustomerPortalPreference;
use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Person;
use App\Models\RecurringServiceBilling;
use App\Models\SalesDocument;
use App\Models\ServiceCycleCommitment;
use App\Models\ServiceCycleCommunication;
use App\Models\User;
use App\Services\Accounting\IssuedInvoiceCorrection;
use App\Services\Analytics\RecurringServiceEconomics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecurringServiceBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_cycle_prepares_one_reviewable_invoice_and_tracks_issue_collection_and_credit(): void
    {
        $this->travelTo('2026-08-27 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        [$commitment] = $this->cycle();
        $payload = ['tax_percent' => 21, 'due_days' => 30, 'generation_note' => 'Contract cycle and customer billing details reviewed.'];

        $this->actingAs($technician)->post(route('after-sales.commitments.billing.store', $commitment), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('after-sales.commitments.billing.store', $commitment), $payload)->assertRedirect();
        $invoice = SalesDocument::where('type', 'invoice')->sole();
        $this->post(route('after-sales.commitments.billing.store', $commitment), $payload)
            ->assertRedirect(route('sales-documents.show', $invoice));

        $billing = RecurringServiceBilling::with('invoice.lines')->sole();
        $this->assertSame($commitment->id, $billing->service_cycle_commitment_id);
        $this->assertSame('300.00', $billing->net_amount);
        $this->assertSame('63.00', $billing->tax_amount);
        $this->assertSame('363.00', $billing->gross_amount);
        $this->assertSame('draft', $billing->invoice->status);
        $this->assertSame(1, $billing->invoice->lines->count());
        $this->assertSame(1, SalesDocument::where('type', 'invoice')->count());
        $this->get(route('sales-documents.edit', $invoice))->assertUnprocessable();
        $this->actingAs($technician)->get(route('contracts-services.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('plans.0.cycle_commitments.0.billing')
                ->missing('plans.0.cycle_commitments.0.communications')
                ->where('serviceEconomics', null));
        $this->actingAs($admin);

        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => today()->toDateString(), 'due_date' => today()->addDays(30)->toDateString()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('issued', $billing->refresh()->status);
        $this->assertSame($admin->id, $billing->issued_by);

        $this->post(route('sales-documents.payments.store', $invoice), ['paid_at' => today()->toDateString(), 'amount' => 363, 'method' => 'bank_transfer'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('paid', $billing->refresh()->status);
        $this->assertSame(300.0, app(RecurringServiceEconomics::class)->build(true)['summary']['settled_revenue']);

        $this->post(route('sales-documents.credit-note', $invoice))->assertRedirect();
        $credit = SalesDocument::where('type', 'credit_note')->sole();
        $this->post(route('sales-documents.issue', $credit), ['issue_date' => today()->toDateString()])->assertRedirect();
        $this->assertSame('credited', $billing->refresh()->status);
        $this->assertSame(0.0, app(RecurringServiceEconomics::class)->build(true)['summary']['settled_revenue']);
    }

    public function test_admin_can_correct_an_issued_cycle_invoice_and_refresh_its_billing_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$commitment] = $this->cycle();
        $this->actingAs($admin)->post(route('after-sales.commitments.billing.store', $commitment), ['tax_percent' => 21, 'due_days' => 30, 'generation_note' => 'Revisar factura del ciclo.'])->assertSessionHasNoErrors();
        $invoice = SalesDocument::where('type', 'invoice')->sole();
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => today()->toDateString()])->assertSessionHasNoErrors();
        $invoice->refresh();
        $dueDate = today()->addDays(45)->toDateString();
        $this->put(route('sales-documents.update-issued', $invoice), [
            'type' => 'invoice', 'person_id' => $invoice->person_id, 'installation_id' => $invoice->installation_id,
            'issue_date' => $invoice->issue_date->toDateString(), 'due_date' => $dueDate,
            'revision' => IssuedInvoiceCorrection::revision($invoice), 'correction_reason' => 'Actualizar precio y tipo de IVA.',
            'lines' => [['id' => $invoice->lines()->firstOrFail()->id, 'description' => 'Servicio revisado', 'quantity' => 1, 'unit_price' => 500, 'discount_percent' => 0, 'tax_percent' => 10]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $billing = RecurringServiceBilling::sole();
        $this->assertSame('500.00', $billing->net_amount);
        $this->assertSame('50.00', $billing->tax_amount);
        $this->assertSame('550.00', $billing->gross_amount);
        $this->assertSame('10.00', $billing->tax_percent);
        $this->assertSame($dueDate, $billing->due_on->toDateString());
        $this->assertSame('550.00', $invoice->fresh()->amount);
    }

    public function test_cycle_notices_respect_customer_preferences_and_only_confirmed_dates_reach_the_portal(): void
    {
        $this->travelTo('2026-08-27 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        [$commitment, $customer] = $this->cycle();
        CustomerPortalPreference::create(['person_id' => $customer->id, 'email_documents' => true, 'email_tickets' => true, 'email_maintenance' => true]);
        $token = 'recurring-service-portal';
        CustomerPortalAccess::create(['person_id' => $customer->id, 'recipient_email' => $customer->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMonth()]);
        $payload = [
            'type' => 'maintenance_notice',
            'subject' => 'Security maintenance visit',
            'message' => 'Your reviewed maintenance date is ready for confirmation.',
            'scheduled_for' => today()->addWeek()->setTime(9, 0)->toDateTimeString(),
        ];

        $this->actingAs($technician)->post(route('after-sales.commitments.communications.store', $commitment), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('after-sales.commitments.communications.store', $commitment), $payload)->assertRedirect();
        $this->post(route('after-sales.commitments.communications.store', $commitment), $payload)->assertRedirect();
        $communication = ServiceCycleCommunication::sole();
        $this->assertSame('email', $communication->channel);
        $this->assertSame('draft', $communication->status);
        $this->get(route('customer-portal.show', $token))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('serviceCommunications', 0));

        $this->post(route('after-sales.commitments.communications.confirm', [$commitment, $communication]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $communication->refresh()->status);
        $this->get(route('customer-portal.show', $token))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('serviceCommunications', 1)
                ->where('serviceCommunications.0.subject', 'Security maintenance visit')
                ->where('serviceCommunications.0.channel', 'email'));
        $this->post(route('after-sales.commitments.communications.sent', [$commitment, $communication]), ['delivery_note' => 'Email delivery confirmed by the operator.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('after-sales.commitments.communications.sent', [$commitment, $communication]), ['delivery_note' => 'Email delivery confirmed by the operator.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('sent', $communication->refresh()->status);
        $this->assertSame($admin->id, $communication->sent_by);
        $this->get(route('customer-portal.show', $token))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('serviceCommunications.0.sent_at', fn ($value) => is_string($value) && $value !== '')
                ->has('timeline'));
        $this->assertDatabaseHas('customer_portal_events', ['person_id' => $customer->id, 'event_type' => 'service_communication_sent']);
        $this->assertSame(1, $customer->portalEvents()->where('event_type', 'service_communication_sent')->count());

        [$blockedCommitment, $blockedCustomer] = $this->cycle('BLOCKED');
        $blockedCustomer->update(['do_not_contact' => true]);
        $this->post(route('after-sales.commitments.communications.store', $blockedCommitment), [...$payload, 'type' => 'billing_notice'])->assertRedirect();
        $blocked = ServiceCycleCommunication::where('service_cycle_commitment_id', $blockedCommitment->id)->sole();
        $this->post(route('after-sales.commitments.communications.confirm', [$blockedCommitment, $blocked]))
            ->assertSessionHasErrors('communication');
        $this->assertSame('draft', $blocked->refresh()->status);
    }

    /** @return array{ServiceCycleCommitment, Person} */
    private function cycle(string $reference = 'SEG-BILLING'): array
    {
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Ana',
            'email' => strtolower($reference).'@example.com',
            'phone' => '600123123',
            'preferred_contact_channel' => 'email',
        ]);
        $installation = Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'security',
            'status' => 'completed',
            'project_reference' => $reference,
            'site_address_line_1' => 'Calle Servicio 1',
        ]);
        $plan = MaintenancePlan::create([
            'installation_id' => $installation->id,
            'name' => 'Security care',
            'version' => 2,
            'interval_months' => 3,
            'starts_on' => today(),
            'next_due_on' => today()->addWeek(),
            'billing_frequency_months' => 3,
            'billing_amount' => 300,
            'next_billing_on' => today()->addWeek(),
            'is_active' => true,
        ]);
        $commitment = ServiceCycleCommitment::create([
            'maintenance_plan_id' => $plan->id,
            'maintenance_plan_version' => 2,
            'period_starts_on' => today()->addWeek(),
            'period_ends_on' => today()->addMonths(3)->subDay(),
            'status' => 'approved',
            'planned_visits' => 1,
            'planned_minutes' => 120,
            'planned_travel_km' => 10,
            'planned_revenue' => 300,
            'planned_labor_cost' => 100,
            'planned_material_cost' => 20,
            'planned_travel_cost' => 5,
            'capacity_snapshot' => ['assigned_user_id' => 1],
            'approval_note' => 'Approved service cycle.',
            'approved_at' => now(),
        ]);

        return [$commitment, $customer];
    }
}
