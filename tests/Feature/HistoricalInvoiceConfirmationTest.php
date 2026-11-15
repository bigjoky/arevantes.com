<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\HistoricalInvoices\ConfirmInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class HistoricalInvoiceConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function prepared(): array
    {
        config(['fiscal.historical_invoice_2026' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $permission = Permission::firstOrCreate(['key' => 'manage-historical-invoices-2026'], ['name' => 'Historical invoices', 'category' => 'finance', 'is_sensitive' => true]);
        $user->permissionProfile->permissions()->syncWithoutDetaching([$permission->id]);
        $supplier = EconomicFixture::supplier(['legal_name' => 'Proveedor sintético', 'tax_id' => 'B12345678', 'country' => 'ES', 'is_active' => true]);
        $document = ['supplier_id' => $supplier->id, 'expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'supplier_tax_id' => 'B12345678', 'supplier_number' => 'HIST-1', 'issue_date' => '2026-01-15', 'operation_date' => '2026-01-14',
            'received_date' => '', 'due_date' => '', 'taxable_base' => '100', 'vat_rate' => '21', 'vat_amount' => '21',
            'withholding_rate' => '0', 'withholding_amount' => '0', 'total' => '121'];
        $batch = DB::table('historical_invoice_batches')->insertGetId(['created_by' => $user->id, 'source' => 'individual', 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('historical_invoice_rows')->insertGetId(['batch_id' => $batch, 'row_number' => 1, 'status' => 'review_required', 'document' => json_encode($document), 'errors' => '[]', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $row];
    }

    public function test_historical_date_and_posting_date_are_separate_and_retry_is_idempotent(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This verification requires isolated PostgreSQL.');
        }
        [$user, $row] = $this->prepared();
        $stockBefore = DB::table('stock_movements')->count();
        $service = app(ConfirmInvoice::class);
        $expense = $service->confirm($row, $user, '2026-03-15');
        $this->assertSame($expense->id, $service->confirm($row, $user, '2026-03-15')->id);
        $this->assertSame('2026-01-15', $expense->expense_date->toDateString());
        $this->assertSame('2026-01-14', $expense->operation_date->toDateString());
        $this->assertSame('2026-03-15', $expense->accountingEntry->entry_date->toDateString());
        $this->assertSame(1, $expense->accountingEntry()->count());
        $this->assertSame(0, $expense->payments()->count());
        $this->assertSame($stockBefore, DB::table('stock_movements')->count());
        $this->assertNull($expense->fiscalClassification);
        $this->assertLessThan(60, abs($expense->created_at->diffInSeconds(now())));
        $this->assertDatabaseHas('historical_invoice_rows', ['id' => $row, 'expense_id' => $expense->id, 'status' => 'registered']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'historical_invoice_confirmed', 'auditable_id' => $row, 'user_id' => $user->id]);
    }

    public function test_duplicate_purchase_is_rejected_without_creating_expense(): void
    {
        [$user, $row] = $this->prepared();
        $supplier = Supplier::where('tax_id', 'B12345678')->firstOrFail();
        EconomicFixture::purchase(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'PUR-SYN', 'supplier_number' => 'HIST-1', 'issue_date' => '2026-01-15', 'status' => 'registered', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121]);
        try {
            app(ConfirmInvoice::class)->confirm($row, $user, '2026-03-15');
            $this->fail('Duplicate must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('expenses', 0);
            $this->assertDatabaseHas('historical_invoice_rows', ['id' => $row, 'expense_id' => null]);
        }
    }

    public function test_closed_period_and_protected_return_leave_the_row_prepared(): void
    {
        [$user, $row] = $this->prepared();
        AccountingPeriod::updateOrCreate(['year' => 2026, 'month' => 3], ['fiscal_year_id' => FiscalYear::where('year', 2026)->firstOrFail()->id, 'status' => 'closed']);
        try {
            app(ConfirmInvoice::class)->confirm($row, $user, '2026-03-15');
            $this->fail('Closed period must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('expenses', 0);
        }
        AccountingPeriod::where(['year' => 2026, 'month' => 3])->update(['status' => 'open']);
        TaxReturn::create(['tax_type' => 'vat', 'year' => 2026, 'quarter' => 1, 'period_from' => '2026-01-01', 'period_to' => '2026-03-31', 'status' => 'prepared', 'output_vat' => 0, 'input_vat' => 0, 'result' => 0, 'snapshot' => [], 'prepared_at' => now()]);
        try {
            app(ConfirmInvoice::class)->confirm($row, $user, '2026-03-15');
            $this->fail('Protected return must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('expenses', 0);
            $this->assertDatabaseHas('historical_invoice_rows', ['id' => $row, 'expense_id' => null]);
        }
    }

    public function test_unsupported_retention_rolls_back(): void
    {
        [$user, $row] = $this->prepared();
        $document = json_decode(DB::table('historical_invoice_rows')->where('id', $row)->value('document'), true);
        $document['withholding_rate'] = '15';
        DB::table('historical_invoice_rows')->where('id', $row)->update(['document' => json_encode($document)]);
        try {
            app(ConfirmInvoice::class)->confirm($row, $user, '2026-03-15');
            $this->fail('Retención no soportada.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('historical_invoice_rows', ['id' => $row, 'expense_id' => null]);
            $this->assertDatabaseCount('expenses', 0);
        }
    }

    public function test_failure_during_posting_rolls_back_expense_and_identity(): void
    {
        [$user, $row] = $this->prepared();
        $this->mock(AccountingSynchronizer::class)->shouldReceive('syncExpense')->once()->andThrow(new \RuntimeException('Synthetic posting failure'));
        try {
            app(ConfirmInvoice::class)->confirm($row, $user, '2026-03-15');
            $this->fail('Posting failure must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic posting failure', $exception->getMessage());
            $this->assertDatabaseCount('expenses', 0);
            $this->assertDatabaseCount('received_invoice_identities', 0);
            $this->assertDatabaseHas('historical_invoice_rows', ['id' => $row, 'expense_id' => null]);
        }
    }

    public function test_unreviewed_receipt_or_special_regime_never_creates_a_document(): void
    {
        [$user, $row] = $this->prepared();
        $original = json_decode(DB::table('historical_invoice_rows')->where('id', $row)->value('document'), true);
        foreach ([['received_date' => '2026-01-20'], ['tax_regime' => 'reverse_charge']] as $unsupported) {
            DB::table('historical_invoice_rows')->where('id', $row)->update(['document' => json_encode([...$original, ...$unsupported])]);
            try {
                app(ConfirmInvoice::class)->confirm($row, $user, '2026-03-15');
                $this->fail('Unsupported fiscal evidence must remain in review.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('expenses', 0);
                $this->assertDatabaseHas('historical_invoice_rows', ['id' => $row, 'expense_id' => null, 'status' => 'review_required']);
            }
        }
    }
}
