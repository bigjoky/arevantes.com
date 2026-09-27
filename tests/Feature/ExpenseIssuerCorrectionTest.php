<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseIssuerCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function expense(): Expense
    {
        $this->withoutExceptionHandling();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $supplier = Supplier::create(['legal_name' => 'Master supplier name', 'country' => 'ES']);
        $this->post(route('expenses.store'), [
            'expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'supplier_id' => $supplier->id, 'type' => 'invoice', 'document_number' => 'TEST-1',
            'issuer_legal_name' => 'Incorrect name', 'issuer_tax_id' => 'A12345678',
            'merchant_name' => 'Station', 'expense_date' => '2026-07-13', 'subtotal' => 100, 'tax_amount' => 21,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->withExceptionHandling();

        return Expense::firstOrFail();
    }

    private function payload(Expense $expense): array
    {
        return [
            'issuer_legal_name' => 'E.S. LA PEÑITA, S.A.', 'merchant_name' => 'Station',
            'correction_reason' => 'Name transcribed incorrectly',
            'issuer_revision' => hash('sha256', json_encode($expense->only(['issuer_legal_name', 'merchant_name']), JSON_THROW_ON_ERROR)),
        ];
    }

    public function test_correction_preserves_accounting_and_supplier_and_records_history(): void
    {
        $expense = $this->expense();
        $before = $expense->getAttributes();
        $journals = JournalEntry::with('lines')->get()->toArray();
        $taxes = $expense->taxLines->toArray();
        $payload = $this->payload($expense);
        $this->patch(route('expenses.issuer.update', $expense), [...$payload, 'amount' => 999, 'issuer_tax_id' => 'INVALID', 'supplier_id' => null])->assertSessionHasNoErrors();
        $expense->refresh();
        $this->assertSame('E.S. LA PEÑITA, S.A.', $expense->issuer_legal_name);
        foreach (['amount', 'supplier_id', 'issuer_tax_id', 'expense_date', 'created_at', 'status', 'paid_amount', 'document_number'] as $field) {
            $this->assertSame($before[$field], $expense->getAttributes()[$field]);
        }
        $this->assertSame($journals, JournalEntry::with('lines')->get()->toArray());
        $this->assertSame($taxes, $expense->taxLines->toArray());
        $this->assertSame('Master supplier name', $expense->supplier->legal_name);
        $log = AuditLog::where('event', 'expense_issuer_corrected')->sole();
        $this->assertSame(auth()->id(), $log->user_id);
        $this->assertSame('Incorrect name', $log->old_values['issuer_legal_name']);
        $this->assertSame($payload['correction_reason'], $log->new_values['correction_reason']);
        $this->patch(route('expenses.issuer.update', $expense), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, AuditLog::where('event', 'expense_issuer_corrected')->count());
        $this->get(route('expenses.show', $expense))->assertInertia(fn (Assert $page) => $page->where('canCorrectIssuer', true)->has('issuerCorrections', 1));
    }

    public function test_non_admin_cannot_correct_or_read_correction_history(): void
    {
        $expense = $this->expense();
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->patch(route('expenses.issuer.update', $expense), $this->payload($expense))->assertForbidden();
        $this->get(route('expenses.show', $expense))->assertInertia(fn (Assert $page) => $page->where('canCorrectIssuer', false)->where('issuerRevision', null)->has('issuerCorrections', 0));
    }

    public function test_stale_form_and_missing_reason_cannot_overwrite_names(): void
    {
        $expense = $this->expense();
        $payload = $this->payload($expense);
        $this->patch(route('expenses.issuer.update', $expense), [...$payload, 'correction_reason' => ''])->assertSessionHasErrors('correction_reason');
        $expense->update(['issuer_legal_name' => 'Another administrator corrected it']);
        $this->patch(route('expenses.issuer.update', $expense), $payload)->assertSessionHasErrors('issuer_legal_name');
        $this->assertSame('Another administrator corrected it', $expense->fresh()->issuer_legal_name);
        $this->assertSame(0, AuditLog::where('event', 'expense_issuer_corrected')->count());
    }

    public function test_paid_expense_can_correct_names_without_changing_payment_or_postings(): void
    {
        $expense = $this->expense();
        $payment = SupplierPayment::create(['expense_id' => $expense->id, 'created_by' => auth()->id(), 'amount' => 121, 'method' => 'cash', 'paid_at' => '2026-07-13']);
        app(AccountingSynchronizer::class)->syncSupplierPayment($payment, auth()->id());
        $expense->update(['paid_amount' => 121, 'status' => 'paid']);
        $journals = JournalEntry::with('lines')->get()->toArray();
        $before = $payment->fresh()->getAttributes();
        $this->patch(route('expenses.issuer.update', $expense), $this->payload($expense))->assertSessionHasNoErrors();
        $this->assertSame($before, $payment->fresh()->getAttributes());
        $this->assertSame($journals, JournalEntry::with('lines')->get()->toArray());
        $this->assertSame('paid', $expense->fresh()->status);
        $this->assertSame('121.00', $expense->fresh()->paid_amount);
    }

    public function test_audit_failure_rolls_back_the_name_change(): void
    {
        $expense = $this->expense();
        AuditLog::creating(function (AuditLog $log): void {
            if ($log->event === 'expense_issuer_corrected') {
                throw new \RuntimeException('Simulated audit failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->patch(route('expenses.issuer.update', $expense), $this->payload($expense));
            $this->fail('Correction must not succeed without its audit.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        }
        $this->assertSame('Incorrect name', $expense->fresh()->issuer_legal_name);
    }
}
