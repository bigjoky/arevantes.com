<?php

namespace Tests\Feature;

use App\Mail\CustomerPortalMail;
use App\Models\CustomerDocumentAcceptance;
use App\Models\CustomerPortalAccess;
use App\Models\CustomerPortalEvent;
use App\Models\CustomerPortalPreference;
use App\Models\CustomerSatisfactionSurvey;
use App\Models\Installation;
use App\Models\InstallationAsset;
use App\Models\MaintenancePlan;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\Quote;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Documents\DocumentFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_portal_is_tokenized_and_isolated_by_customer(): void
    {
        Mail::fake();
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'email' => 'ana@example.com']);
        $otherCustomer = Person::create(['category' => 'customer', 'first_name' => 'Luis', 'email' => 'luis@example.com']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'completed', 'project_reference' => 'FV-ANA', 'site_address_line_1' => 'Calle Sol 1']);
        $asset = InstallationAsset::create(['installation_id' => $installation->id, 'asset_tag' => 'FV-INV-1', 'name' => 'Main inverter', 'category' => 'inverter', 'status' => 'active']);
        $plan = MaintenancePlan::create(['installation_id' => $installation->id, 'name' => 'Priority solar care', 'version' => 3, 'service_level' => 'priority', 'coverage_type' => 'full', 'interval_months' => 12, 'starts_on' => today(), 'contract_ends_on' => today()->addYear(), 'next_due_on' => today()->addMonth(), 'is_active' => true]);
        $plan->coveredAssets()->attach($asset);
        $foreignInstallation = Installation::create(['person_id' => $otherCustomer->id, 'service_type' => 'ev_charger', 'status' => 'completed', 'project_reference' => 'VE-LUIS', 'site_address_line_1' => 'Calle Carga 2']);
        $foreignQuote = Quote::create(['person_id' => $otherCustomer->id, 'number' => 'PRE-2026-99999', 'status' => 'sent', 'issue_date' => '2026-07-13', 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121]);

        $this->actingAs($commercial)->post(route('people.portal.invite', $customer), ['recipient_email' => 'ana@example.com'])->assertRedirect();
        $access = CustomerPortalAccess::firstOrFail();
        $token = null;
        Mail::assertSent(CustomerPortalMail::class, function (CustomerPortalMail $mail) use (&$token) {
            $token = basename(parse_url($mail->url, PHP_URL_PATH));

            return $mail->hasTo('ana@example.com');
        });
        $this->assertSame(hash('sha256', $token), $access->token_hash);

        $this->get(route('customer-portal.show', $token))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertInertia(fn ($page) => $page->component('portal/index')->where('installations.0.project_reference', 'FV-ANA')->has('installations', 1)->where('maintenance.0.version', 3)->where('maintenance.0.coverage_type', 'full')->where('maintenance.0.covered_assets.0.asset_tag', 'FV-INV-1'));
        $this->post(route('customer-portal.tickets.store', $token), ['installation_id' => $installation->id, 'title' => 'Low production', 'description' => 'Production appears lower than expected.', 'priority' => 'normal'])->assertRedirect();
        $this->assertDatabaseHas('service_tickets', ['installation_id' => $installation->id, 'title' => 'Low production']);
        $this->post(route('customer-portal.tickets.store', $token), ['installation_id' => $foreignInstallation->id, 'title' => 'Foreign', 'description' => 'Must not be accepted.', 'priority' => 'normal'])->assertForbidden();
        $this->get(route('customer-portal.documents.pdf', [$token, 'quote', $foreignQuote->id]))->assertNotFound();

        $this->actingAs($commercial)->delete(route('people.portal.revoke', [$customer, $access]))->assertRedirect();
        $this->get(route('customer-portal.show', $token))->assertGone();
        $this->assertSame(1, ServiceTicket::count());
    }

    public function test_portal_acceptance_is_versioned_and_preferences_and_surveys_are_audited(): void
    {
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'email' => 'ana@example.com']);
        $otherCustomer = Person::create(['category' => 'customer', 'first_name' => 'Luis', 'email' => 'luis@example.com']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'completed', 'project_reference' => 'FV-ANA', 'site_address_line_1' => 'Calle Sol 1']);
        $quote = Quote::create(['person_id' => $customer->id, 'number' => 'PRE-2026-00001', 'version' => 3, 'status' => 'sent', 'issue_date' => '2026-08-06', 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121]);
        $foreignQuote = Quote::create(['person_id' => $otherCustomer->id, 'number' => 'PRE-2026-00002', 'status' => 'sent', 'issue_date' => '2026-08-06', 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121]);
        $ticket = ServiceTicket::create(['installation_id' => $installation->id, 'number' => 'SAT-2026-00001', 'kind' => 'incident', 'priority' => 'normal', 'status' => 'resolved', 'title' => 'Resolved ticket', 'resolved_at' => now()]);
        $token = 'secure-portal-token';
        CustomerPortalAccess::create(['person_id' => $customer->id, 'recipient_email' => 'ana@example.com', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addWeek()]);

        $this->get(route('customer-portal.show', $token))->assertOk();
        $fingerprint = app(DocumentFingerprint::class)->for($quote);
        $acceptanceRequest = ['expected_version' => $fingerprint['version'], 'expected_hash' => $fingerprint['hash']];
        $this->post(route('customer-portal.documents.accept', [$token, 'quote', $foreignQuote]), $acceptanceRequest)->assertNotFound();
        $this->post(route('customer-portal.documents.accept', [$token, 'quote', $quote]), $acceptanceRequest)->assertRedirect();
        $this->assertSame('accepted', $quote->refresh()->status);
        $acceptance = CustomerDocumentAcceptance::firstOrFail();
        $this->assertStringStartsWith('quote-v3-', $acceptance->document_version);
        $this->assertSame(64, strlen($acceptance->document_hash));

        $this->patch(route('customer-portal.preferences.update', $token), ['email_documents' => false, 'email_tickets' => true, 'email_maintenance' => false])->assertRedirect();
        $this->assertDatabaseHas(CustomerPortalPreference::class, ['person_id' => $customer->id, 'email_documents' => false, 'email_tickets' => true]);

        $survey = ['service_ticket_id' => $ticket->id, 'context' => 'intervention', 'score' => 5, 'comments' => 'Excellent'];
        $this->post(route('customer-portal.surveys.store', $token), $survey)->assertRedirect();
        $this->post(route('customer-portal.surveys.store', $token), $survey)->assertUnprocessable();
        $this->assertDatabaseHas(CustomerSatisfactionSurvey::class, ['person_id' => $customer->id, 'service_ticket_id' => $ticket->id, 'score' => 5]);
        $this->assertSame(5, $ticket->refresh()->satisfaction_rating);
        $this->assertTrue(CustomerPortalEvent::where('person_id', $customer->id)->where('event_type', 'document_accepted')->exists());
        $this->assertTrue(CustomerPortalEvent::where('person_id', $customer->id)->where('event_type', 'preferences_updated')->exists());
        $this->assertTrue(CustomerPortalEvent::where('person_id', $customer->id)->where('event_type', 'survey_submitted')->exists());
    }

    public function test_failed_invitation_keeps_the_previous_portal_access_active(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'email' => 'ana@example.com']);
        $previous = CustomerPortalAccess::create(['person_id' => $customer->id, 'recipient_email' => 'ana@example.com', 'token_hash' => hash('sha256', 'previous-token'), 'sent_at' => now(), 'expires_at' => now()->addWeek()]);
        Mail::shouldReceive('to')->once()->with('new@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));

        $this->actingAs($commercial)->post(route('people.portal.invite', $customer), ['recipient_email' => 'new@example.com'])->assertSessionHasErrors('recipient_email');

        $this->assertNull($previous->refresh()->revoked_at);
        $this->assertDatabaseHas('customer_portal_accesses', ['recipient_email' => 'new@example.com']);
        $this->assertNotNull(CustomerPortalAccess::where('recipient_email', 'new@example.com')->firstOrFail()->revoked_at);
    }

    public function test_only_published_customer_documents_are_available_as_certificates(): void
    {
        Storage::fake(config('filesystems.private'));
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'email' => 'ana@example.com']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'completed', 'project_reference' => 'FV-ANA', 'site_address_line_1' => 'Calle Sol 1']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'number' => 'OT-2026-00001', 'kind' => 'maintenance', 'status' => 'completed', 'title' => 'Maintenance']);
        $internal = $order->attachments()->create(['kind' => 'document', 'original_name' => 'internal.pdf', 'path' => 'work-orders/internal.pdf', 'mime_type' => 'application/pdf', 'size' => 10]);
        $published = $order->attachments()->create(['kind' => 'document', 'original_name' => 'certificate.pdf', 'path' => 'work-orders/certificate.pdf', 'mime_type' => 'application/pdf', 'size' => 10]);
        $published->forceFill(['publication_status' => 'published', 'customer_visible' => true, 'published_at' => now()])->save();
        Storage::disk(config('filesystems.private'))->put($internal->path, 'internal');
        Storage::disk(config('filesystems.private'))->put($published->path, 'certificate');
        $token = 'certificate-token';
        CustomerPortalAccess::create(['person_id' => $customer->id, 'recipient_email' => 'ana@example.com', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addWeek()]);
        $this->get(route('customer-portal.show', $token))->assertOk()->assertInertia(fn ($page) => $page->has('certificates', 1)->where('certificates.0.name', 'certificate.pdf'));
        $this->get(route('customer-portal.certificates.download', [$token, $internal]))->assertNotFound();
        $this->get(route('customer-portal.certificates.download', [$token, $published]))->assertOk();
    }

    public function test_handover_documents_are_reviewed_versioned_and_only_the_current_version_reaches_the_portal(): void
    {
        Storage::fake(config('filesystems.private'));
        $commercial = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => PermissionProfile::where('slug', 'commercial')->firstOrFail()->id]);
        $admin = User::factory()->create(['role' => 'admin', 'permission_profile_id' => PermissionProfile::where('slug', 'administrator')->firstOrFail()->id]);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Elena', 'email' => 'elena@example.com']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'security', 'status' => 'completed', 'project_reference' => 'SEG-ELENA', 'site_address_line_1' => 'Calle Segura 4']);
        $order = WorkOrder::create([
            'installation_id' => $installation->id,
            'number' => 'OT-2026-00002',
            'kind' => 'commissioning',
            'status' => 'completed',
            'title' => 'Security commissioning',
            'handover_documents' => [['key' => 'monitoring_certificate', 'label' => 'Certificado de conexión y monitorización']],
        ]);
        $token = 'handover-token';
        CustomerPortalAccess::create(['person_id' => $customer->id, 'recipient_email' => 'elena@example.com', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addWeek()]);

        $this->actingAs($commercial)->post(route('work-orders.attachments.store', $order), [
            'files' => [UploadedFile::fake()->create('certificate-v1.pdf', 20, 'application/pdf')],
            'evidence_key' => 'monitoring_certificate',
            'submit_for_review' => true,
        ])->assertRedirect();
        $first = $order->attachments()->firstOrFail();
        $this->assertSame('pending_review', $first->publication_status);
        $this->assertSame(1, $first->document_version);
        $this->actingAs($commercial)->patch(route('work-orders.attachments.publication', [$order, $first]), ['action' => 'publish'])->assertForbidden();
        $this->actingAs($admin)->patch(route('work-orders.attachments.publication', [$order, $first]), ['action' => 'publish'])->assertRedirect();

        $revisionOrder = WorkOrder::create([
            'installation_id' => $installation->id,
            'number' => 'OT-2026-00003',
            'kind' => 'maintenance',
            'status' => 'completed',
            'title' => 'Security documentation revision',
            'handover_documents' => $order->handover_documents,
        ]);
        $this->actingAs($commercial)->post(route('work-orders.attachments.store', $revisionOrder), [
            'files' => [UploadedFile::fake()->create('certificate-v2.pdf', 20, 'application/pdf')],
            'evidence_key' => 'monitoring_certificate',
            'submit_for_review' => true,
        ])->assertRedirect();
        $second = $revisionOrder->attachments()->firstOrFail();
        $this->assertSame(2, $second->document_version);
        $this->actingAs($admin)->patch(route('work-orders.attachments.publication', [$revisionOrder, $second]), ['action' => 'publish'])->assertRedirect();

        $this->assertSame('replaced', $first->refresh()->publication_status);
        $this->assertFalse($first->customer_visible);
        $this->assertSame('published', $second->refresh()->publication_status);
        $this->assertSame($first->id, $second->supersedes_attachment_id);
        $this->get(route('customer-portal.show', $token))->assertOk()->assertInertia(fn ($page) => $page
            ->has('certificates', 1)
            ->where('certificates.0.name', 'certificate-v2.pdf')
            ->where('certificates.0.version', 2)
            ->where('certificates.0.installation.service_type', 'security'));
        $this->get(route('customer-portal.certificates.download', [$token, $first]))->assertNotFound();
        $this->get(route('customer-portal.certificates.download', [$token, $second]))->assertOk();
        $this->actingAs($admin)->delete(route('work-orders.attachments.destroy', [$order, $first]))->assertUnprocessable();
    }
}
