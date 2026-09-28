<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseIssuerCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_history_remains_readable_but_separate_correction_is_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->post(route('expenses.store'), ['expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'type' => 'invoice', 'document_number' => 'OLD-1', 'issuer_legal_name' => 'Supplier', 'issuer_tax_id' => 'A12345678',
            'expense_date' => '2026-09-15', 'subtotal' => 100, 'tax_amount' => 21])->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();
        AuditLog::create(['user_id' => $admin->id, 'event' => 'expense_issuer_corrected',
            'auditable_type' => $expense->getMorphClass(), 'auditable_id' => $expense->id,
            'old_values' => ['issuer_legal_name' => 'Old name', 'merchant_name' => null],
            'new_values' => ['issuer_legal_name' => 'Supplier', 'merchant_name' => null, 'correction_reason' => 'Historical correction']]);
        $this->get(route('expenses.show', $expense))->assertInertia(fn (Assert $page) => $page
            ->missing('canCorrectIssuer')->missing('issuerRevision')->where('canEditExpense', true)
            ->has('issuerCorrections', 1)->where('issuerCorrections.0.before.issuer_legal_name', 'Old name'));
        $this->patch('/expenses/'.$expense->id.'/issuer', ['issuer_legal_name' => 'Unexpected'])->assertNotFound();
        $this->assertSame('Supplier', $expense->fresh()->issuer_legal_name);
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->get(route('expenses.show', $expense))->assertInertia(fn (Assert $page) => $page->where('canEditExpense', false)->has('issuerCorrections', 0));
    }
}
