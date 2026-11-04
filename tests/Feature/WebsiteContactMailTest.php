<?php

namespace Tests\Feature;

use App\Mail\WebsiteContactMailMessage;
use App\Models\User;
use App\Models\WebsiteContactMail;
use App\Models\WebsiteContactReceipt;
use App\Services\Website\WebsiteContactOutbox;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use App\Models\AuditLog;
use Tests\TestCase;

class WebsiteContactMailTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['name' => 'Prueba', 'email' => 'visitante@example.test', 'phone' => '600123456', 'service' => 'solar', 'message' => 'Necesito estudiar una instalación solar para mi vivienda.', 'privacy' => '1'];
    }

    public function test_pending_mail_can_be_cancelled_only_by_an_authorized_operator_and_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $commercial = User::factory()->create(['role' => 'commercial']);
        $this->post(route('website.contact'), $this->payload());
        $item = WebsiteContactMail::firstOrFail();
        $args = ['id' => $item->id, 'decision' => 'cancel', '--operator' => (string) $commercial->id, '--reason' => 'Prueba interna'];
        $this->artisan('website-contact:mail-resolve', $args)->assertFailed();
        $this->artisan('website-contact:mail-resolve', [...$args, '--operator' => (string) $admin->id, '--reason' => '   '])->assertFailed();
        $this->assertSame('pending', $item->fresh()->status);
        $this->artisan('website-contact:mail-resolve', [...$args, '--operator' => (string) $admin->id])->assertSuccessful();
        $item->refresh();
        $this->assertSame('cancelled', $item->status);
        $this->assertSame(0, $item->attempts);
        $this->assertNull($item->attempted_at);
        $audit = AuditLog::where('event', 'contact_mail_pending_cancelled')->where('auditable_id', $item->id)->firstOrFail();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame('Prueba interna', $audit->new_values['reason']);
        $this->assertNotNull($audit->created_at);
        config(['contact-mail.enabled' => true, 'contact-mail.transport' => 'smtp', 'mail.default' => 'smtp']);
        Mail::shouldReceive('to')->never();
        $this->assertSame('not_pending', app(WebsiteContactOutbox::class)->send($item->id));
        $this->artisan('website-contact:mail-resolve', [...$args, '--operator' => (string) $admin->id])->assertFailed();
        $this->assertSame(1, AuditLog::where('event', 'contact_mail_pending_cancelled')->where('auditable_id', $item->id)->count());
    }

    public function test_pending_mail_with_any_previous_attempt_cannot_be_cancelled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), $this->payload());
        $item = WebsiteContactMail::firstOrFail();
        foreach ([['attempts' => 1], ['attempts' => 0, 'attempted_at' => now()], ['attempts' => 0, 'attempted_at' => null, 'sent_at' => now()]] as $values) {
            $item->update($values);
            $this->artisan('website-contact:mail-resolve', ['id' => $item->id, 'decision' => 'cancel', '--operator' => (string) $admin->id, '--reason' => 'Prueba interna'])->assertFailed();
            $this->assertSame('pending', $item->fresh()->status);
        }
        $this->assertDatabaseMissing('audit_logs', ['event' => 'contact_mail_pending_cancelled', 'auditable_id' => $item->id]);
    }

    public function test_retention_only_prunes_after_all_mail_is_resolved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), $this->payload());
        $items = WebsiteContactMail::all();
        $receipt = WebsiteContactReceipt::findOrFail($items->first()->receipt_id);
        $receipt->update(['expires_at' => now()->subDay()]);
        $first = $items->first();
        $this->artisan('website-contact:mail-resolve', ['id' => $first->id, 'decision' => 'cancel', '--operator' => (string) $admin->id, '--reason' => 'Prueba interna'])->assertSuccessful();
        $this->artisan('website-contact:prune --apply')->assertSuccessful();
        $this->assertDatabaseHas('website_contact_receipts', ['id' => $receipt->id]);
        foreach ($items->skip(1) as $item) {
            $this->artisan('website-contact:mail-resolve', ['id' => $item->id, 'decision' => 'cancel', '--operator' => (string) $admin->id, '--reason' => 'Prueba interna'])->assertSuccessful();
        }
        $this->artisan('website-contact:prune --apply')->assertSuccessful();
        $this->assertDatabaseMissing('website_contact_receipts', ['id' => $receipt->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contact_mail_pending_cancelled', 'auditable_id' => $first->id]);
    }

    public function test_outbox_has_one_entry_per_authorized_recipient_and_acknowledgement(): void
    {
        User::factory()->create(['role' => 'commercial', 'email' => 'owner@example.test']);
        User::factory()->create(['role' => 'admin', 'email' => 'admin@example.test']);
        $this->post(route('website.contact'), $this->payload())->assertSessionHas('contact_submitted', true);
        $this->post(route('website.contact'), $this->payload())->assertSessionHas('contact_submitted', true);
        $this->assertDatabaseCount('opportunities', 1);
        $this->assertDatabaseCount('website_contact_mail', 3);
        $this->assertSame(['admin@example.test', 'owner@example.test'], WebsiteContactMail::where('kind', 'staff')->get()->pluck('recipient')->sort()->values()->all());
        $visitor = WebsiteContactMail::where('kind', 'visitor')->firstOrFail();
        $this->assertSame('visitante@example.test', $visitor->recipient);
        $this->assertStringNotContainsString('visitante@example.test', \DB::table('website_contact_mail')->where('id', $visitor->id)->value('recipient'));
    }

    public function test_message_templates_distinguish_customer_and_staff(): void
    {
        $visitor = new WebsiteContactMailMessage(true, null);
        $visitor->assertSeeInHtml('Hemos recibido tu consulta');
        $visitor->assertDontSeeInHtml('oportunidad');
        $staff = new WebsiteContactMailMessage(false, 'https://arevantes.example/opportunities/1');
        $staff->assertSeeInHtml('Nueva solicitud desde la web');
        $staff->assertSeeInHtml('Abrir oportunidad');
    }

    public function test_transport_acceptance_is_recorded_once(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), $this->payload());
        $item = WebsiteContactMail::firstOrFail();
        config(['contact-mail.enabled' => true, 'mail.default' => 'smtp']);
        $pending = \Mockery::mock(PendingMail::class);
        $pending->shouldReceive('send')->once()->andReturn(new \stdClass);
        Mail::shouldReceive('to')->once()->andReturn($pending);
        $this->assertSame('sent', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertSame('not_pending', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertSame('sent', $item->fresh()->status);
    }

    public function test_disabled_mail_does_not_send_or_change_crm(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), $this->payload());
        $item = WebsiteContactMail::firstOrFail();
        config(['contact-mail.enabled' => false, 'mail.default' => 'log']);
        $this->assertSame('disabled', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertSame('pending', $item->fresh()->status);
        $this->assertDatabaseCount('opportunities', 1);
    }

    public function test_retention_keeps_processed_receipts_with_unresolved_mail(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), $this->payload());
        $item = WebsiteContactMail::firstOrFail();
        $receipt = WebsiteContactReceipt::findOrFail($item->receipt_id);
        $receipt->update(['expires_at' => now()->subDay()]);
        $this->artisan('website-contact:prune --apply')->assertSuccessful();
        $this->assertDatabaseHas('website_contact_receipts', ['id' => $receipt->id]);
        WebsiteContactMail::where('receipt_id', $receipt->id)->update(['status' => 'sent']);
        $this->artisan('website-contact:prune --apply')->assertSuccessful();
        $this->assertDatabaseMissing('website_contact_receipts', ['id' => $receipt->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contact_retention_prune', 'auditable_id' => $receipt->id]);
    }

    public function test_parent_records_cannot_be_deleted_while_mail_history_exists(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), $this->payload());
        $item = WebsiteContactMail::firstOrFail();
        foreach (['pending', 'sending', 'uncertain', 'sent', 'cancelled'] as $state) {
            $item->refresh()->update(['status' => $state]);
            foreach ([['website_contact_receipts', $item->receipt_id], ['opportunities', $item->opportunity_id]] as [$table, $id]) {
                try {
                    \DB::transaction(fn () => \DB::table($table)->where('id', $id)->delete());
                    $this->fail("La eliminación de {$table} debería quedar bloqueada para {$state}.");
                } catch (QueryException $exception) {
                    $this->assertDatabaseHas($table, ['id' => $id]);
                    $this->assertDatabaseHas('website_contact_mail', ['id' => $item->id, 'status' => $state]);
                }
            }
        }
    }

    public function test_retention_preserves_uncertain_mail_and_recovery_remains_possible(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), $this->payload());
        $item = WebsiteContactMail::firstOrFail();
        $receipt = WebsiteContactReceipt::findOrFail($item->receipt_id);
        $receipt->update(['expires_at' => now()->subDay()]);
        $item->update(['status' => 'uncertain']);
        $this->artisan('website-contact:prune --apply')->assertSuccessful();
        $this->assertDatabaseHas('website_contact_mail', ['id' => $item->id, 'status' => 'uncertain']);
        $this->artisan('website-contact:mail-resolve', ['id' => $item->id, 'decision' => 'retry', '--operator' => 'test', '--reason' => 'No aceptado por el proveedor'])->assertSuccessful();
        $this->assertSame('pending', $item->fresh()->status);
        $this->artisan('website-contact:prune --apply')->assertSuccessful();
        $this->assertDatabaseHas('website_contact_receipts', ['id' => $receipt->id]);
    }

    public function test_uncertain_delivery_requires_a_recorded_manual_resolution_before_retry(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), $this->payload());
        $item = WebsiteContactMail::firstOrFail();
        $item->update(['status' => 'uncertain']);
        $this->artisan('website-contact:mail-resolve', ['id' => $item->id, 'decision' => 'retry'])->assertFailed();
        $this->assertSame('uncertain', $item->fresh()->status);
        $this->artisan('website-contact:mail-resolve', ['id' => $item->id, 'decision' => 'retry', '--operator' => 'test', '--reason' => 'Proveedor confirma ausencia de entrega'])->assertSuccessful();
        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_transport_failure_is_uncertain_and_never_retried_automatically(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->post(route('website.contact'), $this->payload());
        $item = WebsiteContactMail::firstOrFail();
        config(['contact-mail.enabled' => true, 'mail.default' => 'smtp']);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('transport failure'));
        $this->assertSame('uncertain', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertSame('not_pending', app(WebsiteContactOutbox::class)->send($item->id));
        $this->assertSame(1, $item->fresh()->attempts);
        $this->assertDatabaseCount('opportunities', 1);
    }
}
