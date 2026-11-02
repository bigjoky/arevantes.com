<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Person;
use App\Models\User;
use App\Models\WebsiteContactReceipt;
use App\Notifications\WebsiteLeadNotification;
use App\Services\Website\WebsiteContactReceipts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebsiteContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_website_contact_creates_a_customer_and_crm_opportunity(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);

        $this->post(route('website.contact'), $this->payload())
            ->assertRedirect(route('home'))
            ->assertSessionHas('contact_submitted', true);

        $customer = Person::firstOrFail();
        $opportunity = Opportunity::firstOrFail();

        $this->assertSame('customer', $customer->category);
        $this->assertSame('María', $customer->first_name);
        $this->assertSame('López Ruiz', $customer->last_name);
        $this->assertSame('maria@example.com', $customer->email);
        $this->assertSame(['Instalación fotovoltaica'], $customer->service_interests);
        $this->assertSame($customer->id, $opportunity->person_id);
        $this->assertSame('new', $opportunity->stage);
        $this->assertSame('website', $opportunity->source);
        $this->assertSame($commercial->id, $opportunity->owner_user_id);
        $this->assertNotNull($opportunity->first_response_due_at);
        $this->assertStringStartsWith('OPP-'.now()->format('Y').'-', $opportunity->code);
        $this->assertStringContainsString('vivienda unifamiliar', $opportunity->notes);

        Notification::assertSentTo([$admin, $commercial], WebsiteLeadNotification::class);
        Notification::assertNotSentTo($technician, WebsiteLeadNotification::class);
    }

    public function test_an_existing_customer_is_reused_by_email(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'commercial']);
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'María',
            'email' => 'MARIA@example.com',
            'service_interests' => ['Mantenimiento'],
        ]);

        $this->post(route('website.contact'), $this->payload())->assertRedirect();

        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('opportunities', 1);
        $this->assertSame('600123123', $customer->refresh()->phone);
        $this->assertSame(
            ['Mantenimiento', 'Instalación fotovoltaica'],
            $customer->service_interests,
        );
    }

    public function test_a_website_lead_is_assigned_to_the_commercial_with_the_smallest_open_pipeline(): void
    {
        Notification::fake();
        $busy = User::factory()->create(['role' => 'commercial']);
        $available = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Existing']);
        Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $busy->id,
            'code' => 'OPP-BUSY-1',
            'title' => 'Existing open opportunity',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 5000,
        ]);

        $this->post(route('website.contact'), $this->payload())->assertRedirect();

        $lead = Opportunity::where('source', 'website')->firstOrFail();
        $this->assertSame($available->id, $lead->owner_user_id);
    }

    public function test_contact_validation_and_honeypot_block_invalid_submissions(): void
    {
        $this->post(route('website.contact'), [
            ...$this->payload(),
            'email' => 'not-an-email',
            'privacy' => null,
            'company_url' => 'https://spam.example',
        ])->assertSessionHasErrors(['email', 'privacy', 'company_url']);

        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_an_immediate_retry_does_not_create_a_duplicate_opportunity(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'commercial']);

        $this->post(route('website.contact'), $this->payload())->assertRedirect();
        $this->post(route('website.contact'), $this->payload())->assertRedirect();

        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('opportunities', 1);
    }

    public function test_website_lead_notifications_do_not_require_a_worker(): void
    {
        $this->assertFalse(
            is_subclass_of(WebsiteLeadNotification::class, ShouldQueue::class),
        );
    }

    public function test_email_or_phone_alone_is_sufficient(): void
    {
        User::factory()->create(['role' => 'commercial']);
        $this->post(route('website.contact'), [...$this->payload(), 'phone' => null])->assertSessionHasNoErrors();
        $this->post(route('website.contact'), [...$this->payload(), 'email' => null, 'phone' => '611222333'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('opportunities', 2);
    }

    public function test_server_rejects_invalid_fields(): void
    {
        $this->post(route('website.contact'), [...$this->payload(), 'name' => '', 'email' => null, 'phone' => null, 'service' => 'invalid', 'message' => 'corto', 'privacy' => null])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'service', 'message', 'privacy']);
        $this->post(route('website.contact'), [...$this->payload(), 'phone' => '123'])->assertSessionHasErrors('phone');
        $this->post(route('website.contact'), [...$this->payload(), 'message' => str_repeat('a', 2001)])->assertSessionHasErrors('message');
        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_notifications_are_persisted_without_a_worker(): void
    {
        config(['queue.default' => 'database']);
        $owner = User::factory()->create(['role' => 'commercial']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->post(route('website.contact'), $this->payload())->assertSessionHas('contact_submitted', true);

        $this->assertCount(1, $owner->notifications);
        $this->assertCount(1, $admin->notifications);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_failure_to_notify_rolls_back_the_entire_submission(): void
    {
        User::factory()->create(['role' => 'commercial']);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Notification storage failed'));

        $this->post(route('website.contact'), $this->payload())->assertSessionHas('contact_submitted', true);
        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('opportunities', 0);
        $this->assertDatabaseCount('website_contact_receipts', 1);
        $this->assertNotNull(WebsiteContactReceipt::firstOrFail()->payload);
    }

    public function test_missing_owner_returns_an_error_without_saving(): void
    {
        $this->post(route('website.contact'), $this->payload())->assertSessionHas('contact_submitted', true);
        $this->assertDatabaseCount('opportunities', 0);
        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('website_contact_receipts', 1);
    }

    public function test_received_data_is_encrypted_and_admin_can_retry_idempotently(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = app(WebsiteContactReceipts::class)->receive($this->payload());
        $raw = \DB::table('website_contact_receipts')->where('id', $receipt->id)->value('payload');
        $this->assertStringNotContainsString('maria@example.com', strtolower($raw));
        $this->assertStringNotContainsString('vivienda unifamiliar', $raw);
        $this->actingAs($admin)->post(route('contact-incidents.retry', $receipt))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['event' => 'website_contact_retry', 'user_id' => $admin->id, 'auditable_id' => $receipt->id]);
        $this->actingAs($admin)->post(route('contact-incidents.retry', $receipt))->assertRedirect();
        $this->assertDatabaseCount('opportunities', 1);
        $this->assertNull($receipt->fresh()->payload);
        $this->assertNotNull($receipt->fresh()->processed_at);
    }

    public function test_failed_processing_can_be_retried_without_duplicates(): void
    {
        $service = app(WebsiteContactReceipts::class);
        $receipt = $service->receive($this->payload());
        $this->assertFalse($service->process($receipt));
        $this->assertNotNull($receipt->fresh()->payload);
        User::factory()->create(['role' => 'commercial']);
        $this->assertTrue($service->process($receipt));
        $this->assertTrue($service->process($receipt));
        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('opportunities', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertNull($receipt->fresh()->payload);
    }

    public function test_identical_reception_is_idempotent(): void
    {
        $service = app(WebsiteContactReceipts::class);
        $one = $service->receive($this->payload());
        $two = $service->receive($this->payload());
        $this->assertSame($one->id, $two->id);
        $this->assertDatabaseCount('website_contact_receipts', 1);
    }

    public function test_retention_command_requires_explicit_apply(): void
    {
        $receipt = app(WebsiteContactReceipts::class)->receive($this->payload());
        $receipt->update(['expires_at' => now()->subDay()]);
        $this->artisan('website-contact:prune')->assertSuccessful();
        $this->assertDatabaseHas('website_contact_receipts', ['id' => $receipt->id]);
        $this->artisan('website-contact:prune --apply')->assertSuccessful();
        $this->assertDatabaseHas('website_contact_receipts', ['id' => $receipt->id]);
    }

    public function test_incident_access_requires_administrative_permission(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $receipt = app(WebsiteContactReceipts::class)->receive($this->payload());
        $this->actingAs($user)->get(route('contact-incidents.index'))->assertForbidden();
        $this->actingAs($user)->post(route('contact-incidents.retry', $receipt))->assertForbidden();
    }

    public function test_failed_durable_storage_does_not_confirm_receipt(): void
    {
        \Schema::drop('website_contact_mail');
        \Schema::drop('website_contact_receipts');
        $this->post(route('website.contact'), $this->payload())->assertSessionHasErrors('submission')->assertSessionMissing('contact_submitted');
    }

    public function test_opportunity_detail_exposes_the_existing_response_sla(): void
    {
        $owner = User::factory()->create(['role' => 'commercial']);
        $this->post(route('website.contact'), $this->payload())->assertSessionHas('contact_submitted', true);
        $opportunity = Opportunity::firstOrFail();

        $this->actingAs($owner)->get(route('opportunities.show', $opportunity))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('opportunities/show')
                ->where('opportunity.response_sla_status', 'pending')
                ->where('opportunity.owner.id', $owner->id)
                ->where('opportunity.first_responded_at', null)
                ->has('opportunity.first_response_due_at'));
    }

    private function payload(): array
    {
        return [
            'name' => 'María López Ruiz',
            'email' => 'Maria@Example.com',
            'phone' => '600123123',
            'postal_code' => '14850',
            'service' => 'solar',
            'message' => 'Quiero estudiar una instalación solar para una vivienda unifamiliar.',
            'privacy' => '1',
            'company_url' => '',
        ];
    }
}
