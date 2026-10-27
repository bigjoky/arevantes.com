<?php

namespace Database\Seeders;

use App\Http\Controllers\QuotePaperAcceptanceController;
use App\Models\CrmActivity;
use App\Models\FieldServiceProfile;
use App\Models\GoodsReceipt;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Inventory\ProjectMaterialPlanner;
use App\Services\Inventory\StockLedger;
use App\Services\Quotes\QuoteStatusWorkflow;
use App\Services\Work\WorkOrderFactory;
use Dompdf\Dompdf;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProjectJourneyDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || DB::getDriverName() !== 'sqlite' || ! str_ends_with(DB::connection()->getDatabaseName(), 'installation-templates.sqlite')) {
            throw new \RuntimeException('Esta demostración solo se prepara en el SQLite local de revisión.');
        }
        if (Opportunity::where('code', 'DEMO-REC-01')->exists()) {
            $this->acceptedUnplanned();

            return;
        }
        DB::transaction(function () {
            $admin = User::where('email', 'plantillas@example.test')->firstOrFail();
            $technician = User::firstOrCreate(['email' => 'tecnico-recorrido@example.test'], ['name' => 'DEMO · Técnico de revisión', 'role' => 'technician', 'password' => Hash::make(bin2hex(random_bytes(24))), 'email_verified_at' => now()]);
            FieldServiceProfile::updateOrCreate(['user_id' => $technician->id], ['skills' => ['solar', 'electrical', 'commissioning'], 'daily_capacity_minutes' => 480, 'service_zones' => [], 'is_available' => true]);
            $warehouse = Warehouse::firstOrCreate(['code' => 'DEMO-REC'], ['name' => 'DEMO · Almacén de revisión', 'is_active' => true, 'is_usable' => true]);
            Warehouse::firstOrCreate(['code' => 'DEMO-COR'], ['name' => 'DEMO · Destino correcto', 'is_active' => true, 'is_usable' => true]);
            $product = Product::firstOrCreate(['sku' => 'DEMO-REC-PANEL'], ['type' => 'product', 'name' => 'DEMO · Panel para pruebas de recorrido', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 100, 'cost_price' => 60, 'track_stock' => true, 'track_serials' => false, 'is_active' => true]);
            app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $warehouse->id, 'reference' => 'DEMO · existencias ficticias', 'lines' => [['product_id' => $product->id, 'quantity' => 20, 'unit_cost' => 60]]], $admin->id);
            foreach ([1 => 'Contacto sin email · propuesta pendiente', 2 => 'Aceptado en papel · anticipo pendiente', 3 => 'Instalación terminada · entrega y factura pendientes'] as $index => $title) {
                $person = Person::create(['category' => 'customer', 'first_name' => 'DEMO '.$index, 'last_name' => 'Cliente ficticio sin email', 'phone' => '60099000'.$index, 'address_line_1' => 'Emplazamiento ficticio '.$index, 'country' => 'Spain']);
                $opportunity = Opportunity::create(['person_id' => $person->id, 'owner_user_id' => $admin->id, 'created_by' => $admin->id, 'code' => 'DEMO-REC-0'.$index, 'title' => 'DEMO · '.$title, 'stage' => 'proposal', 'source' => 'referral', 'expected_close_on' => today()->addDays(10)]);
                $quote = Quote::create(['opportunity_id' => $opportunity->id, 'person_id' => $person->id, 'created_by' => $admin->id, 'number' => 'DEMO-PRE-0'.$index, 'version' => 1, 'service_type' => 'solar', 'status' => 'draft', 'approval_status' => 'not_required', 'issue_date' => today(), 'valid_until' => today()->addDays(30), 'subtotal' => 400, 'tax_amount' => 84, 'amount' => 484, 'acceptance_payment_percentage' => $index === 2 ? 30 : 0, 'acceptance_payment_due_days' => 7, 'customer_snapshot' => $person->only(['first_name', 'last_name', 'phone', 'email'])]);
                $quote->lines()->create(['position' => 1, 'product_id' => $product->id, 'description' => $product->name, 'quantity' => 4, 'unit_price' => 100, 'tax_percent' => 21, 'is_selected' => true, 'subtotal' => 400, 'tax_amount' => 84, 'total' => 484]);
                if ($index === 1) {
                    continue;
                }
                if ($index === 2) {
                    $demoPdf = new Dompdf;
                    $demoPdf->loadHtml('<h1>DEMO · Justificante ficticio</h1><p>Firma ficticia de un cliente de prueba sin email. Documento exclusivo de revisión local, sin validez contractual.</p>');
                    $demoPdf->render();
                    $bytes = $demoPdf->output();
                    $request = Request::create('/quotes/'.$quote->id.'/paper-acceptance', 'POST', ['accepted_at' => today()->toDateString(), 'signed_by_name' => 'DEMO · Firmante ficticio', 'reason' => 'DEMO · aceptación ficticia, no válida para contratación real'], [], ['proof' => UploadedFile::fake()->createWithContent('DEMO-firma.pdf', $bytes)]);
                    $request->setUserResolver(fn () => $admin);
                    app(QuotePaperAcceptanceController::class)->store($request, $quote, app(QuoteStatusWorkflow::class));
                } else {
                    app(QuoteStatusWorkflow::class)->apply($quote, 'accepted', $admin, 'internal', 'DEMO · aceptación ficticia para revisión local');
                }
                $installation = Installation::create(['person_id' => $person->id, 'commercial_user_id' => $admin->id, 'technician_user_id' => $technician->id, 'service_type' => 'solar', 'project_reference' => 'DEMO-PRJ-0'.$index, 'site_name' => 'DEMO · '.$title, 'site_address_line_1' => 'Emplazamiento ficticio '.$index, 'site_country' => 'Spain', 'status' => $index === 3 ? 'completed' : 'approved', 'technical_validation_required' => true, 'financial_status' => $quote->fresh()->financial_status, 'estimated_budget' => 484]);
                $quote->update(['installation_id' => $installation->id]);
                $quote->salesDocuments()->update(['installation_id' => $installation->id]);
                $opportunity->update(['installation_id' => $installation->id]);
                app(ProjectMaterialPlanner::class)->syncFromQuote($quote, $installation);
                $survey = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'DEMO-WO-VISIT-0'.$index, 'title' => 'DEMO · Visita técnica', 'kind' => 'survey', 'status' => $index === 3 ? 'completed' : 'planned', 'completed_at' => $index === 3 ? now() : null]);
                $survey->activities()->create(['installation_id' => $installation->id, 'opportunity_id' => $opportunity->id, 'person_id' => $person->id, 'title' => $survey->title, 'type' => 'visit', 'status' => $index === 3 ? 'completed' : 'pending', 'assigned_user_id' => $technician->id, 'created_by' => $admin->id]);
                $order = app(WorkOrderFactory::class)->create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'kind' => 'installation', 'title' => 'DEMO · Instalación', 'scheduled_start' => today()->addDays(5)->setTime(9, 0), 'scheduled_end' => today()->addDays(5)->setTime(13, 0)], $admin->id);
                if ($index === 3) {
                    $order->update(['status' => 'completed', 'completed_at' => now(), 'completion_notes' => 'DEMO · ejecución ficticia terminada para revisar pendientes administrativos']);
                    $order->activities()->update(['status' => 'completed']);
                    CrmActivity::create(['installation_id' => $installation->id, 'title' => 'DEMO · Legalización contratada pendiente', 'type' => 'task', 'status' => 'pending', 'priority' => 'normal', 'required_for_closure' => true, 'created_by' => $admin->id]);
                }
            }
            $supplier = Supplier::firstOrCreate(['legal_name' => 'DEMO · Proveedor ficticio de revisión'], ['country' => 'ES']);
            $purchase = PurchaseDocument::create(['type' => 'purchase_order', 'supplier_id' => $supplier->id, 'created_by' => $admin->id, 'number' => 'DEMO-PC-01', 'status' => 'partially_received', 'issue_date' => today(), 'subtotal' => 240, 'tax_amount' => 50.40, 'amount' => 290.40]);
            $receiptProduct = Product::create(['type' => 'product', 'sku' => 'DEMO-RECEPCION', 'name' => 'DEMO · Material para corregir recepción', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 100, 'cost_price' => 60, 'track_stock' => true, 'track_serials' => false, 'is_active' => true]);
            $purchase->lines()->create(['product_id' => $receiptProduct->id, 'position' => 1, 'description' => $receiptProduct->name, 'quantity' => 4, 'received_quantity' => 2, 'unit_price' => 60, 'tax_percent' => 21, 'subtotal' => 240, 'tax_amount' => 50.40, 'total' => 290.40]);
            $movement = app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $warehouse->id, 'purchase_document_id' => $purchase->id, 'reference' => 'DEMO-PC-01', 'lines' => [['product_id' => $receiptProduct->id, 'quantity' => 2, 'unit_cost' => 60]]], $admin->id);
            GoodsReceipt::create(['purchase_document_id' => $purchase->id, 'stock_movement_id' => $movement->id, 'warehouse_id' => $warehouse->id, 'created_by' => $admin->id, 'number' => 'DEMO-EM-01', 'received_at' => now()]);
        });
        $this->acceptedUnplanned();
    }

    private function acceptedUnplanned(): void
    {
        if (Opportunity::where('code', 'DEMO-REC-04')->exists()) {
            return;
        }
        DB::transaction(function () {
            $admin = User::where('email', 'plantillas@example.test')->firstOrFail();
            $person = Person::create(['category' => 'customer', 'first_name' => 'DEMO 4', 'last_name' => 'Cliente ficticio sin email', 'phone' => '600990004', 'address_line_1' => 'Emplazamiento ficticio 4', 'country' => 'Spain']);
            $opportunity = Opportunity::create(['person_id' => $person->id, 'owner_user_id' => $admin->id, 'created_by' => $admin->id, 'code' => 'DEMO-REC-04', 'title' => 'DEMO · Aceptado sin fecha ni técnico: crear proyecto', 'stage' => 'proposal', 'source' => 'referral']);
            $quote = Quote::create(['opportunity_id' => $opportunity->id, 'person_id' => $person->id, 'created_by' => $admin->id, 'number' => 'DEMO-PRE-04', 'version' => 1, 'service_type' => 'solar', 'status' => 'draft', 'approval_status' => 'not_required', 'issue_date' => today(), 'valid_until' => today()->addDays(30), 'subtotal' => 400, 'tax_amount' => 84, 'amount' => 484, 'acceptance_payment_percentage' => 0, 'customer_snapshot' => $person->only(['first_name', 'last_name', 'phone', 'email'])]);
            $quote->lines()->create(['position' => 1, 'product_id' => Product::where('sku', 'DEMO-REC-PANEL')->value('id'), 'description' => 'DEMO · Instalación de prueba', 'quantity' => 4, 'unit_price' => 100, 'tax_percent' => 21, 'is_selected' => true, 'subtotal' => 400, 'tax_amount' => 84, 'total' => 484]);
            app(QuoteStatusWorkflow::class)->apply($quote, 'accepted', $admin, 'internal', 'DEMO · aceptación ficticia sin programación');
        });
    }
}
