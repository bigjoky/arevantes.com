<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PermissionProfile;
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
        $this->actingAs($finance)->get(route('expenses.show', $expense))->assertOk();
        $this->post(route('expenses.payments.store', $expense), ['paid_at' => '2026-07-13', 'amount' => 50, 'method' => 'card'])->assertRedirect();
        $this->assertSame('partial', $expense->refresh()->status);
        $this->assertSame('50.00', $expense->paid_amount);
        $this->assertSame('AST-2026-000002', $expense->payments()->firstOrFail()->accountingEntry()->firstOrFail()->number);
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

        $this->actingAs($user)->get(route('purchases.show', $invoice))->assertOk();
        $this->post(route('supplier-invoices.payments.store', $invoice), ['paid_at' => '2026-07-13', 'amount' => 121, 'method' => 'bank_transfer', 'reference' => 'BANK-1'])->assertRedirect();
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame('121.00', $invoice->paid_amount);
        $this->assertDatabaseHas('supplier_payments', ['purchase_document_id' => $invoice->id, 'reference' => 'BANK-1']);
        $this->assertNotNull($invoice->payments()->firstOrFail()->accountingEntry);
    }
}
