<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankFinancialProduct;
use App\Models\DocumentSeries;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LedgerAccount;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expense_stores_private_evidence_and_tracks_partial_payment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'commercial']);
        $category = ExpenseCategory::firstOrFail();
        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_category_id' => $category->id, 'supplier_id' => 'none', 'type' => 'receipt',
            'merchant_name' => 'Fuel Station', 'expense_date' => '2026-07-13', 'subtotal' => 100, 'tax_amount' => 21,
            'attachments' => [UploadedFile::fake()->image('ticket.jpg')],
        ])->assertRedirect();

        $expense = Expense::with('attachments')->firstOrFail();
        $this->assertSame('EXP-2026-00001', $expense->number);
        $this->assertSame('121.00', $expense->amount);
        $this->assertSame('AST-2026-000001', $expense->accountingEntry()->firstOrFail()->number);
        $attachment = $expense->attachments->first();
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($user)->get(route('expenses.attachments.download', [$expense, $attachment]))->assertOk();

        $this->post(route('expenses.payments.store', $expense), ['paid_at' => '2026-07-13', 'amount' => 50, 'method' => 'card'])->assertForbidden();

        $finance = User::factory()->create([
            'role' => 'commercial',
            'permission_profile_id' => PermissionProfile::where('slug', 'finance')->firstOrFail()->id,
        ]);
        $bank = $this->bankAccount();
        $cardLedger = LedgerAccount::create(['code' => '520000001', 'name' => 'Corporate card', 'type' => 'liability', 'parent_code' => '520', 'level' => 9, 'is_postable' => true, 'is_active' => true]);
        $card = BankFinancialProduct::create(['bank_account_id' => $bank->id, 'ledger_account_id' => $cardLedger->id, 'type' => 'credit_card', 'name' => 'Visa operations', 'currency' => 'EUR', 'identifier_last_four' => '4242', 'credit_limit' => 5000, 'used_amount' => 0, 'is_active' => true]);
        $this->actingAs($finance)->get(route('expenses.show', $expense))->assertOk();
        $this->post(route('expenses.payments.store', $expense), ['paid_at' => '2026-07-13', 'amount' => 50, 'method' => 'card', 'funding_source_type' => 'card', 'funding_source_id' => $card->id])->assertRedirect();
        $this->assertSame('partial', $expense->refresh()->status);
        $this->assertSame('50.00', $expense->paid_amount);
        $this->assertSame('AST-2026-000002', $expense->payments()->firstOrFail()->accountingEntry()->firstOrFail()->number);
        $this->assertSame('50.00', $expense->payments()->firstOrFail()->accountingEntry->lines->firstWhere('ledger_account_id', $cardLedger->id)->credit);
        $this->assertSame(2, DocumentSeries::where('document_type', 'expense')->firstOrFail()->next_number);
        $this->assertSame(3, DocumentSeries::where('document_type', 'journal_entry')->firstOrFail()->next_number);
        $this->actingAs($user)->delete(route('expenses.destroy', $expense))->assertUnprocessable();
    }

    public function test_a_supplier_invoice_can_be_paid_from_treasury(): void
    {
        $user = User::factory()->create([
            'role' => 'commercial',
            'permission_profile_id' => PermissionProfile::where('slug', 'finance')->firstOrFail()->id,
        ]);
        $supplier = Supplier::create(['legal_name' => 'Supplier SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $invoice = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'FR-2026-00001', 'supplier_number' => 'FS-99', 'status' => 'registered', 'issue_date' => '2026-07-13', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121]);
        $bank = $this->bankAccount();

        $this->actingAs($user)->get(route('purchases.show', $invoice))->assertOk();
        $this->post(route('supplier-invoices.payments.store', $invoice), ['paid_at' => '2026-07-13', 'amount' => 121, 'method' => 'bank_transfer', 'funding_source_type' => 'bank_account', 'funding_source_id' => $bank->id, 'reference' => 'BANK-1'])->assertRedirect();
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame('121.00', $invoice->paid_amount);
        $this->assertDatabaseHas('supplier_payments', ['purchase_document_id' => $invoice->id, 'reference' => 'BANK-1']);
        $this->assertNotNull($invoice->payments()->firstOrFail()->accountingEntry);
    }

    public function test_a_fiscal_expense_supports_multiple_vat_rates_deductibility_and_withholding(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = ExpenseCategory::firstOrFail();
        $supplier = Supplier::create([
            'legal_name' => 'Asesoria Fiscal SL',
            'tax_id' => 'B12345678',
            'country' => 'ES',
            'payment_terms_days' => 30,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'installation_id' => 'none',
            'type' => 'invoice',
            'document_number' => 'AF-2026-88',
            'invoice_series' => 'AF',
            'expense_date' => '2026-08-28',
            'operation_date' => '2026-08-27',
            'due_date' => '2026-09-27',
            'currency_code' => 'EUR',
            'tax_regime' => 'standard',
            'deductible_expense_percent' => 100,
            'withholding_rate' => 15,
            'withholding_amount' => 22.50,
            'tax_lines' => [
                ['kind' => 'vat', 'taxable_base' => 100, 'tax_rate' => 21, 'tax_amount' => 21, 'deductible_percent' => 100],
                ['kind' => 'vat', 'taxable_base' => 50, 'tax_rate' => 10, 'tax_amount' => 5, 'deductible_percent' => 50],
                ['kind' => 'equivalence_surcharge', 'taxable_base' => 100, 'tax_rate' => 5.2, 'tax_amount' => 5.2, 'deductible_percent' => 100],
            ],
        ])->assertRedirect();

        $expense = Expense::with(['taxLines', 'accountingEntry.lines.account'])->firstOrFail();
        $this->assertSame('Asesoria Fiscal SL', $expense->issuer_legal_name);
        $this->assertSame('B12345678', $expense->issuer_tax_id);
        $this->assertSame('150.00', $expense->subtotal);
        $this->assertSame('31.20', $expense->tax_amount);
        $this->assertSame('22.50', $expense->withholding_amount);
        $this->assertSame('158.70', $expense->amount);
        $this->assertCount(3, $expense->taxLines);
        $this->assertSame('0.00', $expense->taxLines->last()->deductible_amount);

        $journalLines = $expense->accountingEntry->lines->groupBy(fn ($line) => $line->account->code);
        $this->assertSame(23.5, (float) $journalLines['472']->sum('debit'));
        $this->assertSame('22.50', $journalLines['4751']->first()->credit);
        $creditorCode = $supplier->refresh()->payable_account_code ?: '410';
        $this->assertSame('158.70', $journalLines[$creditorCode]->first()->credit);
    }

    public function test_a_full_invoice_requires_fiscal_issuer_identification(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'supplier_id' => 'none',
            'installation_id' => 'none',
            'type' => 'invoice',
            'expense_date' => '2026-08-28',
            'subtotal' => 100,
            'tax_amount' => 21,
        ])->assertSessionHasErrors(['document_number', 'issuer_legal_name', 'issuer_tax_id']);
    }

    public function test_employee_accounts_use_identification_suffix_and_personal_payment_records_the_debt(): void
    {
        $finance = User::factory()->create(['role' => 'admin']);
        $employee = Person::create(['category' => 'staff', 'first_name' => 'Lucía', 'last_name' => 'Sanz', 'tax_id' => '12345678Z']);
        $this->assertStringEndsWith('678', $employee->refresh()->employee_advance_account_code);
        $this->assertStringEndsWith('678', $employee->employee_payable_account_code);
        $secondEmployee = Person::create(['category' => 'staff', 'first_name' => 'Mario', 'last_name' => 'León', 'tax_id' => '87654678P']);
        $this->assertStringEndsWith('678', $secondEmployee->refresh()->employee_payable_account_code);
        $this->assertNotSame($employee->employee_payable_account_code, $secondEmployee->employee_payable_account_code);

        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $finance->id, 'number' => 'EXP-2026-00999', 'type' => 'receipt', 'merchant_name' => 'Shared purchase', 'expense_date' => '2026-08-29', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 0, 'status' => 'pending']);
        $this->actingAs($finance)->post(route('expenses.payments.store', $expense), ['paid_at' => '2026-08-29', 'amount' => 100, 'method' => 'employee_funds', 'funding_source_type' => 'employee', 'funding_source_id' => $employee->id])->assertRedirect();

        $payment = $expense->payments()->firstOrFail();
        $this->assertSame($employee->employee_payable_account_code, $payment->ledgerAccount->code);
        $this->assertSame('100.00', $payment->accountingEntry->lines->firstWhere('ledger_account_id', $payment->ledger_account_id)->credit);
    }

    private function bankAccount(): BankAccount
    {
        $ledger = LedgerAccount::create(['code' => '572009999', 'name' => 'Operations bank', 'type' => 'asset', 'parent_code' => '572', 'level' => 9, 'is_postable' => true, 'is_active' => true]);

        return BankAccount::create(['name' => 'Operations account', 'bank_name' => 'Test bank', 'currency' => 'EUR', 'ledger_account_id' => $ledger->id, 'opening_balance' => 0, 'is_active' => true]);
    }
}
