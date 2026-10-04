<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Tax\VatSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatSettlementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function draft(User $user): TaxReturn
    {
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'created_by' => $user->id, 'number' => 'EXP-WORKFLOW', 'type' => 'invoice',
            'expense_date' => '2026-09-15', 'subtotal' => 100, 'tax_amount' => 21,
            'amount' => 121, 'status' => 'pending']);
        app(AccountingSynchronizer::class)->syncExpense($expense, $user->id);

        return app(VatSettlement::class)->prepare(2026, 3, $user->id);
    }

    public function test_review_is_non_posting_and_changed_books_require_a_new_approval(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $return = $this->draft($user);
        $entries = JournalEntry::count();
        $this->actingAs($user)->post(route('tax.returns.post', $return))->assertStatus(422);
        $this->post(route('tax.returns.review', $return), [])->assertSessionHasErrors('reason');
        $this->post(route('tax.returns.review', $return), ['reason' => 'Checked against invoices'])->assertSessionHasNoErrors();
        $this->assertSame($entries, JournalEntry::count());
        $this->assertSame('approved', $return->fresh()->status);
        $this->assertSame($user->id, $return->fresh()->snapshot['review_history'][0]['user_id']);

        // Even an identity-only change must invalidate the evidence being approved.
        Expense::firstOrFail()->update(['merchant_name' => 'Corrected issuer']);
        $this->post(route('tax.returns.post', $return))->assertSessionHasErrors('review');
        $this->assertSame($entries, JournalEntry::count());
        $this->assertNull($return->fresh()->journal_entry_id);
        $this->post(route('tax.returns.review', $return), ['reason' => 'Updated evidence', 'refresh' => true])->assertSessionHasNoErrors();
        $this->assertSame('draft', $return->fresh()->status);
        $this->post(route('tax.returns.post', $return))->assertStatus(422);
        $this->post(route('tax.returns.review', $return), ['reason' => 'Reviewed corrected issuer'])->assertSessionHasNoErrors();
        $this->post(route('tax.returns.post', $return))->assertSessionHasNoErrors();
        $this->assertSame($entries + 1, JournalEntry::count());
        $this->assertSame('prepared', $return->fresh()->status);
        $this->assertSame($user->id, $return->fresh()->snapshot['posted_by']);
        $this->post(route('tax.returns.post', $return))->assertStatus(422);
        $this->post(route('tax.returns.review', $return), ['reason' => 'Cannot rewrite', 'refresh' => true])->assertStatus(422);
        $this->assertSame($entries + 1, JournalEntry::count());
    }

    public function test_finance_read_access_cannot_approve_or_post_a_draft(): void
    {
        $return = $this->draft(User::factory()->create(['role' => 'admin']));
        $entries = JournalEntry::count();
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->post(route('tax.returns.review', $return), ['reason' => 'Unauthorized'])->assertForbidden();
        $this->post(route('tax.returns.post', $return))->assertForbidden();
        $this->assertSame('draft', $return->fresh()->status);
        $this->assertSame($entries, JournalEntry::count());
    }

    public function test_posting_failure_preserves_approval_and_creates_no_partial_entry(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $return = $this->draft($user);
        $this->actingAs($user)->post(route('tax.returns.review', $return), ['reason' => 'Reviewed'])->assertSessionHasNoErrors();
        FiscalYear::where('year', 2026)->update(['status' => 'closed']);
        $entries = JournalEntry::count();
        $this->post(route('tax.returns.post', $return))->assertSessionHasErrors('entry_date');
        $this->assertSame('approved', $return->fresh()->status);
        $this->assertNull($return->fresh()->journal_entry_id);
        $this->assertSame($entries, JournalEntry::count());
    }
}
