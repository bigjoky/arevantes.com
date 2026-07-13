<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Person;
use App\Models\User;
use App\Notifications\WebsiteLeadNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        $this->assertSame('Website', $opportunity->source);
        $this->assertStringStartsWith('OPP-'.now()->format('Y').'-', $opportunity->code);
        $this->assertStringContainsString('vivienda unifamiliar', $opportunity->notes);

        Notification::assertSentTo([$admin, $commercial], WebsiteLeadNotification::class);
        Notification::assertNotSentTo($technician, WebsiteLeadNotification::class);
    }

    public function test_an_existing_customer_is_reused_by_email(): void
    {
        Notification::fake();
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
