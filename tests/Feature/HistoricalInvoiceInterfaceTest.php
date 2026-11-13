<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class HistoricalInvoiceInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->permissionProfile->permissions()->syncWithoutDetaching([Permission::where('key', 'manage-historical-invoices-2026')->firstOrFail()->id]);

        return $user;
    }

    public function test_flag_and_permission_guard_entry(): void
    {
        $user = $this->actor();
        config(['fiscal.historical_invoice_2026' => false]);
        $this->actingAs($user)->get('/billing/historical-invoices')->assertForbidden();
        $this->get('/billing/historical-invoices/template')->assertForbidden();
        $this->post('/billing/historical-invoices', [])->assertForbidden();
        $this->post('/billing/historical-invoices/import', [])->assertForbidden();
        $this->post('/billing/historical-invoices/1', [])->assertForbidden();
        $this->post('/billing/historical-invoices/1/confirm', [])->assertForbidden();
        config(['fiscal.historical_invoice_2026' => true]);
        $this->actingAs($user)->get('/billing/historical-invoices')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'commercial']))->get('/billing/historical-invoices')->assertForbidden();
    }

    public function test_individual_preparation_evidence_edit_and_confirmation(): void
    {
        config(['fiscal.historical_invoice_2026' => true, 'filesystems.private' => 'local']);
        Storage::fake('local');
        $user = $this->actor();
        $supplier = EconomicFixture::supplier(['legal_name' => 'Synthetic supplier', 'tax_id' => 'B12345678', 'country' => 'ES', 'is_active' => true]);
        $data = ['supplier_id' => $supplier->id, 'supplier_tax_id' => 'B12345678', 'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'supplier_number' => 'INDIVIDUAL-1', 'issue_date' => '2026-01-15', 'operation_date' => '2026-01-14', 'due_date' => '2026-02-15', 'taxable_base' => '100', 'vat_rate' => '21', 'vat_amount' => '21', 'withholding_rate' => '0', 'withholding_amount' => '0', 'total' => '121', 'notes' => 'Referencia sintética'];
        $data['evidence'] = UploadedFile::fake()->createWithContent('original.pdf', "%PDF-1.4\n%%EOF\n");
        $this->actingAs($user)->post('/billing/historical-invoices', $data)->assertSessionHasNoErrors();
        $row = DB::table('historical_invoice_rows')->first();
        $this->assertNotNull($row);
        $this->assertDatabaseCount('expenses', 0);
        $evidence = json_decode($row->evidence, true);
        Storage::disk('local')->assertExists($evidence['path']);
        unset($data['evidence']);
        $data['notes'] = 'Referencia corregida';
        $this->post('/billing/historical-invoices/'.$row->id, $data)->assertSessionHasNoErrors();
        $response = $this->post('/billing/historical-invoices/'.$row->id.'/confirm', ['accounting_date' => '2026-03-15']);
        $response->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();
        $response->assertRedirect('/billing/suppliers/expense/'.$expense->id);
        $this->assertSame('2026-01-15', $expense->expense_date->toDateString());
        $this->assertSame('2026-03-15', $expense->accountingEntry->entry_date->toDateString());
        $attachment = $expense->attachments()->firstOrFail();
        $this->assertSame($evidence['path'], $attachment->path);
        $this->get(route('expenses.attachments.download', [$expense, $attachment]))->assertOk();
        $this->assertSame(0, $expense->payments()->count());
        auth()->logout();
        $this->get(route('expenses.attachments.download', [$expense, $attachment]))->assertRedirect();
    }

    public function test_csv_import_persists_rows_for_review_without_posting(): void
    {
        config(['fiscal.historical_invoice_2026' => true]);
        $user = $this->actor();
        $csv = implode(',', \App\Services\HistoricalInvoices\CsvReader::COLUMNS)."\nB12345678,A-1,2026-01-02,,,,100,21,21,0,0,121\nB12345678,A-2,2025-01-02,,,,100,21,21,0,0,121\n";
        $this->actingAs($user)->post('/billing/historical-invoices/import', ['csv' => UploadedFile::fake()->createWithContent('invoices.csv', $csv)])->assertSessionHasNoErrors();
        $rows = DB::table('historical_invoice_rows')->orderBy('row_number')->get();
        $this->assertCount(2, $rows);
        $this->assertNotEmpty(json_decode($rows[1]->errors, true));
        $this->assertDatabaseCount('expenses', 0);
        $supplier = EconomicFixture::supplier(['legal_name' => 'Synthetic CSV supplier', 'tax_id' => 'B12345678', 'country' => 'ES', 'is_active' => true]);
        $corrected = ['supplier_id' => $supplier->id, 'supplier_tax_id' => 'B12345678', 'expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'supplier_number' => 'A-2', 'issue_date' => '2026-01-02', 'operation_date' => '', 'received_date' => '', 'due_date' => '',
            'taxable_base' => '100', 'vat_rate' => '21', 'vat_amount' => '21', 'withholding_rate' => '0', 'withholding_amount' => '0', 'total' => '121'];
        $this->post('/billing/historical-invoices/'.$rows[1]->id, $corrected)->assertSessionHasNoErrors();
        $this->assertSame([], json_decode(DB::table('historical_invoice_rows')->where('id', $rows[1]->id)->value('errors'), true));
        $this->assertDatabaseCount('expenses', 0);
    }
}
