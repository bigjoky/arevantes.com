<?php

namespace Tests\Feature;

use App\Jobs\GeocodeInstallation;
use App\Models\FieldServiceProfile;
use App\Models\HrLeaveRequest;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Inventory\StockLedger;
use App\Services\Projects\ProjectJourney;
use App\Services\Quotes\QuoteMaterialReadiness;
use App\Services\Work\InstallationWorkOrderSynchronizer;
use App\Services\Work\WorkOrderFactory;
use App\Services\Work\WorkOrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectJourneyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function quote(string $status = 'accepted'): Quote
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $person = Person::create(['category' => 'customer', 'first_name' => 'Prueba recorrido', 'phone' => '600000123']);
        $opportunity = Opportunity::create(['person_id' => $person->id, 'owner_user_id' => $user->id, 'code' => 'OPP-JOURNEY', 'title' => 'Prueba', 'stage' => 'proposal']);
        $quote = Quote::create(['person_id' => $person->id, 'opportunity_id' => $opportunity->id, 'created_by' => $user->id, 'number' => 'PRE-JOURNEY', 'status' => $status, 'service_type' => 'solar', 'approval_status' => 'not_required', 'issue_date' => today(), 'valid_until' => today()->addDays(30), 'amount' => 121, 'subtotal' => 100, 'tax_amount' => 21, 'acceptance_payment_percentage' => 0]);
        $quote->lines()->create(['position' => 1, 'is_selected' => true, 'description' => 'Concepto libre', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 21, 'subtotal' => 100, 'tax_amount' => 21, 'total' => 121]);
        Queue::fake([GeocodeInstallation::class]);

        return $quote;
    }

    public function test_accepted_quote_creates_project_without_date_technician_or_survey_and_retries_reuse_it(): void
    {
        $quote = $this->quote();
        $data = ['service_type' => 'solar', 'site_address_line_1' => 'Prueba local', 'site_country' => 'Spain'];
        $this->post(route('quotes.project.store', $quote), $data)->assertRedirect()->assertSessionHasNoErrors();
        $id = $quote->fresh()->installation_id;
        $this->assertNotNull($id);
        $this->assertDatabaseCount('work_orders', 0);
        $this->assertNull(Installation::findOrFail($id)->survey_date);
        $this->post(route('quotes.project.store', $quote), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('installations', 1);
        $this->assertSame($id, $quote->fresh()->installation_id);
    }

    public function test_existing_pre_sales_visit_and_project_are_reused_without_a_new_visit(): void
    {
        $quote = $this->quote();
        $installation = Installation::create(['person_id' => $quote->person_id, 'service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Prueba local', 'project_reference' => 'PRJ-EXISTING']);
        $quote->opportunity->update(['installation_id' => $installation->id]);
        WorkOrder::create(['installation_id' => $installation->id, 'number' => 'WO-VISIT', 'title' => 'Visita previa', 'kind' => 'survey', 'status' => 'completed']);
        $this->post(route('quotes.project.store', $quote), ['service_type' => 'solar', 'site_address_line_1' => 'Prueba local', 'site_country' => 'Spain', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('installations', 1);
        $this->assertDatabaseCount('work_orders', 1);
        $this->assertSame($installation->id, $quote->fresh()->installation_id);
    }

    public function test_paper_acceptance_without_email_archives_proof_and_does_not_emit_invoice_or_duplicate_history(): void
    {
        Storage::fake(config('filesystems.private'));
        $quote = $this->quote('draft');
        $data = ['accepted_at' => today()->toDateString(), 'signed_by_name' => 'Cliente de prueba', 'reason' => 'Firma presencial', 'proof' => UploadedFile::fake()->image('firma.jpg')];
        $this->post(route('quotes.paper-acceptance', $quote), $data)->assertRedirect()->assertSessionHasNoErrors();
        $history = $quote->statusHistory()->where('channel', 'paper')->firstOrFail();
        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame('won', $quote->opportunity->fresh()->stage);
        Storage::disk(config('filesystems.private'))->assertExists($history->proof_path);
        $this->assertSame('Cliente de prueba', $history->signed_by_name);
        $this->assertDatabaseCount('sales_documents', 0);
        $this->assertDatabaseCount('installations', 0);
        $this->get(route('quotes.paper-acceptance.download', [$quote, $history]))->assertOk();
        $this->post(route('quotes.paper-acceptance', $quote), $data)->assertRedirect();
        $this->assertSame(1, $quote->statusHistory()->where('channel', 'paper')->count());
        $this->actingAs(User::factory()->create(['role' => 'technician']))->get(route('quotes.paper-acceptance.download', [$quote, $history]))->assertForbidden();
    }

    public function test_pending_acceptance_payment_allows_preparation_but_still_blocks_execution_and_reservation(): void
    {
        $quote = $this->quote();
        $installation = Installation::create(['person_id' => $quote->person_id, 'service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Prueba', 'financial_status' => 'pending']);
        $order = app(WorkOrderFactory::class)->create(['installation_id' => $installation->id, 'kind' => 'installation', 'title' => 'Preparar sin anticipo'], auth()->id());
        $this->assertSame('planned', $order->status);
        $this->assertDatabaseCount('stock_reservations', 0);
        try {
            app(WorkOrderWorkflow::class)->assertTransition($order, 'in_progress');
            $this->fail('Debe bloquear la ejecución sin anticipo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('financial_status', $e->errors());
        }
    }

    public function test_required_technical_validation_is_shared_by_execution_workflow_and_completed_visits_satisfy_it(): void
    {
        $this->quote();
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Prueba', 'technical_validation_required' => true]);
        $order = app(WorkOrderFactory::class)->create(['installation_id' => $installation->id, 'kind' => 'installation', 'title' => 'Preparación'], auth()->id());
        try {
            app(WorkOrderWorkflow::class)->assertTransition($order, 'in_progress');
            $this->fail('Debe exigir la visita o ingeniería.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        WorkOrder::create(['installation_id' => $installation->id, 'number' => 'WO-SURVEY-OK', 'title' => 'Visita previa', 'kind' => 'survey', 'status' => 'completed']);
        app(WorkOrderWorkflow::class)->assertTransition($order, 'in_progress');
        $this->assertSame('planned', $order->fresh()->status);
    }

    public function test_journey_distinguishes_missing_data_and_unavailable_permissions_and_preserves_separate_closure(): void
    {
        $quote = $this->quote();
        $installation = Installation::create(['person_id' => $quote->person_id, 'service_type' => 'solar', 'status' => 'completed', 'site_address_line_1' => 'Prueba', 'project_reference' => 'PRJ-JOURNEY']);
        $quote->update(['installation_id' => $installation->id]);
        $journey = app(ProjectJourney::class)->for(auth()->user(), $installation);
        $blocks = collect($journey['blocks'])->keyBy('key');
        $this->assertSame('unrecorded', $blocks['technical']['state']);
        $this->assertSame('pending', $blocks['materials']['state']);
        $this->assertNull($journey['closure']['closed_at']);
        $this->assertSame('pending', $blocks['closure']['state']);
        $this->get('/execution/projects/'.$installation->id)->assertOk();
        $this->get('/opportunities/'.$quote->opportunity_id)->assertOk();
        $technician = User::factory()->create(['role' => 'technician']);
        $journey = app(ProjectJourney::class)->for($technician, $installation);
        $this->assertSame('unavailable', collect($journey['blocks'])->firstWhere('key', 'finance')['state']);
        $this->assertStringNotContainsString('121.00', json_encode($journey));
    }

    public function test_web_capture_accepts_phone_without_email_and_requires_a_contact_channel(): void
    {
        $data = ['name' => 'Contacto sin email', 'phone' => '600000321', 'service' => 'solar', 'message' => 'Quiero información para una instalación solar.', 'privacy' => '1'];
        $this->post(route('website.contact'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('opportunities', 1);
        $this->post(route('website.contact'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('opportunities', 1);
        unset($data['phone']);
        $this->post(route('website.contact'), $data)->assertSessionHasErrors(['phone', 'email']);
    }

    public function test_paper_signature_before_expiry_is_valid_but_signature_before_quote_issue_is_not(): void
    {
        Storage::fake(config('filesystems.private'));
        $quote = $this->quote('sent');
        $quote->update(['issue_date' => today()->subDays(10), 'valid_until' => today()->subDays(2)]);
        $data = ['accepted_at' => today()->subDays(5)->toDateString(), 'signed_by_name' => 'Cliente de prueba', 'proof' => UploadedFile::fake()->image('signature.jpg')];
        $this->post(route('quotes.paper-acceptance', $quote), [...$data, 'accepted_at' => today()->subDays(20)->toDateString()])->assertSessionHasErrors('accepted_at');
        $this->assertSame('sent', $quote->fresh()->status);
        $this->post(route('quotes.paper-acceptance', $quote), $data)->assertSessionHasNoErrors();
        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame(today()->subDays(5)->toDateString(), $quote->fresh()->accepted_at->toDateString());
    }

    public function test_one_completed_order_does_not_complete_a_multi_order_project_and_late_survey_does_not_regress_execution(): void
    {
        $this->quote();
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'in_progress', 'site_address_line_1' => 'Prueba']);
        $first = WorkOrder::create(['installation_id' => $installation->id, 'number' => 'WO-MULTI-1', 'title' => 'Instalar', 'kind' => 'installation', 'status' => 'completed', 'completed_at' => now()]);
        $second = WorkOrder::create(['installation_id' => $installation->id, 'number' => 'WO-MULTI-2', 'title' => 'Puesta en marcha', 'kind' => 'commissioning', 'status' => 'in_progress']);
        $sync = app(InstallationWorkOrderSynchronizer::class);
        $sync->sync($first);
        $this->assertSame('in_progress', $installation->fresh()->status);
        $survey = WorkOrder::create(['installation_id' => $installation->id, 'number' => 'WO-MULTI-3', 'title' => 'Visita', 'kind' => 'survey', 'status' => 'completed']);
        $sync->sync($survey);
        $this->assertSame('in_progress', $installation->fresh()->status);
        $second->update(['status' => 'completed', 'completed_at' => now()]);
        $sync->sync($second);
        $this->assertSame('completed', $installation->fresh()->status);
        $this->assertNotNull($installation->fresh()->completed_date);
        $this->assertNull($installation->fresh()->dossier_closed_at);
    }

    public function test_material_preview_excludes_unusable_stock_and_honors_component_classification(): void
    {
        $quote = $this->quote('draft');
        $product = Product::create(['type' => 'product', 'sku' => 'JOURNEY-STOCK', 'name' => 'Material de prueba', 'tax_rate_id' => TaxRate::firstOrFail()->id, 'unit_id' => Unit::firstOrFail()->id, 'track_stock' => true, 'track_serials' => false, 'sale_price' => 100, 'cost_price' => 60]);
        $warehouse = Warehouse::create(['code' => 'JOURNEY-QUARANTINE', 'name' => 'No utilizable', 'is_active' => true, 'is_usable' => false]);
        app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $warehouse->id, 'lines' => [['product_id' => $product->id, 'quantity' => 10]]], auth()->id());
        $line = $quote->lines()->firstOrFail();
        $line->update(['product_id' => $product->id]);
        $preview = app(QuoteMaterialReadiness::class)->preview($quote->fresh());
        $this->assertSame(1, $preview['shortage_items']);
        $this->assertEquals(0, $preview['rows'][0]['available_quantity']);
        $line->components()->create(['fulfillment_kind' => 'task', 'description' => 'Solo trabajo técnico', 'quantity' => 1]);
        $this->assertSame(0, app(QuoteMaterialReadiness::class)->preview($quote->fresh())['items']);
        $line->components()->create(['fulfillment_kind' => 'stock', 'description' => 'Material sin catálogo', 'quantity' => 1]);
        $warnings = app(QuoteMaterialReadiness::class)->classificationWarnings($quote->fresh());
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Material sin catálogo', $warnings[0]);
    }

    public function test_common_assignment_validation_rejects_missing_skills_and_hr_absences(): void
    {
        $this->quote();
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Prueba']);
        $person = Person::create(['category' => 'staff', 'first_name' => 'Técnico ficticio']);
        $technician = User::factory()->create(['role' => 'technician', 'person_id' => $person->id]);
        $profile = FieldServiceProfile::create(['user_id' => $technician->id, 'skills' => ['hvac'], 'is_available' => true, 'daily_capacity_minutes' => 480]);
        $day = today()->addDays(2);
        $data = ['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'kind' => 'installation', 'title' => 'Prueba de asignación', 'scheduled_start' => $day->copy()->setTime(9, 0), 'scheduled_end' => $day->copy()->setTime(11, 0)];
        try {
            app(WorkOrderFactory::class)->create($data, auth()->id());
            $this->fail('La factoría también debe validar habilidades.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assigned_user_id', $e->errors());
        }
        $profile->update(['skills' => ['solar', 'electrical']]);
        HrLeaveRequest::create(['person_id' => $person->id, 'type' => 'vacation', 'start_date' => $day, 'end_date' => $day, 'status' => 'approved', 'requested_days' => 1]);
        try {
            app(WorkOrderFactory::class)->create($data, auth()->id());
            $this->fail('La disponibilidad laboral es común a las rutas.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assigned_user_id', $e->errors());
        }
        $this->post(route('work-orders.store'), $data)->assertSessionHasErrors('assigned_user_id');
        $this->assertDatabaseCount('work_orders', 0);
    }
}
