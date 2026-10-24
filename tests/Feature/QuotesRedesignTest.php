<?php

namespace Tests\Feature;

use App\Mail\DocumentSharedMail;
use App\Models\DocumentDelivery;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;
use App\Services\Documents\DocumentPdf;
use App\Services\Quotes\QuoteCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuotesRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function quote(array $extra = []): Quote
    {
        $person = Person::create(['category' => 'customer', 'first_name' => 'Cliente real de prueba', 'tax_id' => 'B12345678', 'email' => 'quote@example.test']);

        return Quote::create(['person_id' => $person->id, 'created_by' => User::factory()->create(['role' => 'commercial'])->id, 'number' => 'PRE-'.uniqid(), 'version' => 1, 'status' => 'draft', 'approval_status' => 'not_required', 'issue_date' => today()->toDateString(), 'valid_until' => today()->addDays(10)->toDateString(), 'amount' => 121, 'subtotal' => 100, 'tax_amount' => 21, 'discount_amount' => 0, 'customer_snapshot' => $person->customerSnapshot(), ...$extra]);
    }

    public function test_listing_filters_kpis_sort_and_pagination_share_one_scope(): void
    {
        $a = $this->quote(['status' => 'sent', 'amount' => 200]);
        $this->quote(['status' => 'accepted', 'amount' => 300]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/quotes?search=B12345678&status=sent&sort=amount&direction=asc&per_page=15')->assertOk()->assertInertia(fn (Assert $p) => $p->component('quotes/index')->has('quotes.data', 1)->where('quotes.data.0.id', $a->id)->where('summary.total', 1)->where('summary.statuses.sent.count', 1)->where('summary.statuses.accepted.count', 0)->where('summary.statuses.sent.amount', fn ($amount) => (float) $amount === 200.0));
        $this->get('/quotes?sort=unsupported')->assertStatus(422);
    }

    public function test_expired_sent_quotes_are_derived_consistently_without_get_mutations(): void
    {
        $quote = $this->quote(['status' => 'sent', 'valid_until' => today()->subDay()->toDateString()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/quotes?status=expired')->assertOk()->assertInertia(fn (Assert $p) => $p->where('quotes.data.0.effective_status', 'expired')->where('summary.statuses.expired.count', 1));
        $this->assertSame('sent', $quote->fresh()->status);
        $this->patch('/quotes/'.$quote->id.'/status', ['status' => 'accepted'])->assertSessionHasErrors('status');
        $this->assertSame('sent', $quote->fresh()->status);
    }

    public function test_duplicate_is_a_new_draft_and_preserves_line_hierarchy_without_execution_links(): void
    {
        $quote = $this->quote(['status' => 'sent']);
        $parent = $quote->lines()->create(['description' => 'Kit', 'quantity' => 1, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 10, 'position' => 1, 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 10, 'total' => 110]);
        $quote->lines()->create(['parent_line_id' => $parent->id, 'depth' => 1, 'description' => 'Montaje', 'quantity' => 1, 'unit_price' => 30, 'discount_percent' => 0, 'tax_percent' => 0, 'position' => 2, 'subtotal' => 30, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 30]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/quotes/'.$quote->id.'/duplicate')->assertRedirect();
        $copy = Quote::latest('id')->firstOrFail();
        $this->assertNotSame($quote->number, $copy->number);
        $this->assertSame('draft', $copy->status);
        $this->assertSame(1, $copy->version);
        $this->assertNull($copy->opportunity_id);
        $this->assertNull($copy->installation_id);
        $this->assertCount(2, $copy->lines);
        $this->assertSame($copy->lines[0]->id, $copy->lines[1]->parent_line_id);
        $this->assertSame('140.00', $copy->amount);
        $this->assertCount(1, $copy->versions);
    }

    public function test_sent_pdf_is_archived_exactly_and_authorization_protects_history(): void
    {
        Storage::fake(config('filesystems.private'));
        Mail::fake();
        $quote = $this->quote();
        $original = "%PDF original immutable bytes\n";
        $pdf = $this->mock(DocumentPdf::class);
        $pdf->shouldReceive('render')->once()->andReturn($original);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/quotes/'.$quote->id.'/send', ['recipient_email' => 'quote@example.test'])->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSent(DocumentSharedMail::class);
        $delivery = $quote->deliveries()->firstOrFail();
        $this->assertSame('sent', $quote->fresh()->status);
        $quote->update(['notes' => 'Changed later', 'version' => 2]);
        $quote->customer->update(['first_name' => 'Updated customer']);
        $this->get('/quotes/'.$quote->id.'/deliveries/'.$delivery->id.'/pdf')->assertOk()->assertContent($original);
        $other = $this->quote();
        $this->get('/quotes/'.$other->id.'/deliveries/'.$delivery->id.'/pdf')->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'technician']))->get('/quotes/'.$quote->id.'/deliveries/'.$delivery->id.'/pdf')->assertForbidden();
    }

    public function test_failed_email_does_not_mark_quote_sent(): void
    {
        Storage::fake(config('filesystems.private'));
        $quote = $this->quote();
        $this->mock(DocumentPdf::class)->shouldReceive('render')->once()->andReturn('%PDF failed mail');
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Transport unavailable'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/quotes/'.$quote->id.'/send', ['recipient_email' => 'quote@example.test'])->assertRedirect()->assertSessionHasErrors('recipient_email');
        $this->assertSame('draft', $quote->fresh()->status);
        $this->assertSame('failed', DocumentDelivery::firstOrFail()->status);
    }

    public function test_optimistic_version_conflict_does_not_overwrite_draft(): void
    {
        $quote = $this->quote(['version' => 2]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put('/quotes/'.$quote->id, ['expected_version' => 1, 'person_id' => $quote->person_id, 'issue_date' => today()->toDateString(), 'change_note' => 'Edición obsoleta', 'lines' => [['description' => 'Línea obsoleta', 'quantity' => 1, 'unit_price' => 200, 'discount_percent' => 0, 'tax_percent' => 10]]])->assertSessionHasErrors('expected_version');
        $this->assertSame(2, $quote->fresh()->version);
        $this->assertSame('121.00', $quote->fresh()->amount);
    }

    public function test_project_conversion_requires_acceptance_and_cannot_duplicate_execution(): void
    {
        $quote = $this->quote(['service_type' => 'solar']);
        $data = ['service_type' => 'solar', 'scheduled_at' => today()->addDays(5)->setTime(10, 0)->toDateTimeString(), 'site_address_line_1' => 'Calle de prueba', 'site_country' => 'Spain'];
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/quotes/'.$quote->id.'/project', $data)->assertUnprocessable();
        $quote->update(['status' => 'accepted']);
        $this->post('/quotes/'.$quote->id.'/project', $data)->assertRedirect();
        $installation = $quote->fresh()->installation;
        $this->assertNotNull($installation);
        $this->assertSame($quote->person_id, $installation->person_id);
        $this->assertSame($quote->amount, $installation->estimated_budget);
        $this->get('/quotes/'.$quote->id.'?tab=installation')->assertOk()->assertInertia(fn (Assert $p) => $p->where('quote.installation.status', 'survey_booked')->where('profitability.status', 'survey_booked'));
        $this->post('/quotes/'.$quote->id.'/project', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('installations', 1);
    }

    public function test_activity_is_paginated_and_contains_real_history_and_versions(): void
    {
        $quote = $this->quote();
        foreach (range(1, 17) as $i) {
            $quote->statusHistory()->create(['to_status' => 'draft', 'channel' => 'internal', 'reason' => 'Audit '.$i, 'changed_at' => now()->subMinutes($i), 'changed_by' => $quote->created_by]);
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/quotes/'.$quote->id.'?tab=activity&activity_page=2')->assertOk()->assertInertia(fn (Assert $p) => $p->where('quoteActivity.total', 17)->has('quoteActivity.data', 2)->where('quoteActivity.current_page', 2)->where('quoteActivity.data.0.occurred_at', fn ($value) => str_ends_with($value, 'Z')));
        $this->get('/quotes/'.$quote->id.'?tab=unknown')->assertStatus(422);
        $this->get('/quotes?date_to='.today()->toDateString())->assertOk();
    }

    public function test_related_tabs_require_backend_permissions(): void
    {
        $quote = $this->quote();
        foreach (['manage-customers', 'view-installations', 'manage-installations'] as $permission) {
            Gate::define($permission, fn () => false);
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/quotes/'.$quote->id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('relationPermissions.customer', false)->where('relationPermissions.billing', false)->where('quote.installation', null)->has('billing.documents', 0));
        foreach (['customer', 'opportunity', 'installation', 'billing'] as $tab) {
            $this->get('/quotes/'.$quote->id.'?tab='.$tab)->assertForbidden();
        }
    }

    public function test_listing_queries_do_not_grow_per_quote(): void
    {
        $this->quote();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        DB::enableQueryLog();
        $this->get('/quotes')->assertOk();
        $first = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (range(1, 12) as $i) {
            $this->quote();
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/quotes')->assertOk();
        $second = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($first + 2, $second);
    }

    public function test_decimal_calculation_rounds_per_line_and_respects_actual_taxes_and_selection(): void
    {
        $totals = app(QuoteCalculator::class)->calculate([
            ['description' => 'Precision', 'quantity' => '3', 'unit_price' => '0.335', 'discount_percent' => '10', 'tax_percent' => '10'],
            ['description' => 'Exempt', 'quantity' => '1', 'unit_price' => '12.34', 'discount_percent' => '0', 'tax_percent' => '0'],
            ['description' => 'Optional', 'quantity' => 1, 'unit_price' => 999, 'tax_percent' => 21, 'is_selected' => false],
        ]);
        $this->assertSame(13.35, $totals['subtotal']);
        $this->assertSame(0.10, $totals['discount_amount']);
        $this->assertSame(0.09, $totals['tax_amount']);
        $this->assertSame(13.34, $totals['amount']);
    }
}
