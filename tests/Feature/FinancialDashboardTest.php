<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_uses_posted_lines_excludes_closing_and_compares_equal_periods(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->startOfDay());
        $this->actingAs(User::factory()->create());
        FiscalYear::updateOrCreate(['year' => 2026], ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
        $this->entry('2026-01-20', 'posted', 'automatic', 1000, 400);
        $this->entry('2026-09-28', 'posted', 'manual', 200, 100);
        $this->entry('2026-02-20', 'draft', 'manual', 9999, 0);
        $this->entry('2026-09-28', 'posted', 'closing', -1200, -500);
        $this->entry('2026-10-01', 'posted', 'automatic', 9000, 0);
        $this->entry('2025-01-20', 'posted', 'automatic', 600, 250);
        $this->entry('2025-10-01', 'posted', 'automatic', 9000, 0);

        $this->get('/dashboard?year=2026')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')->where('year', 2026)
            ->where('financialOverview.metrics.income', 1200)
            ->where('financialOverview.metrics.expenses', 500)
            ->where('financialOverview.metrics.result', 700)
            ->where('financialOverview.comparison.income', 100)
            ->where('financialOverview.comparison.expenses', 100)
            ->where('financialOverview.comparison.result', 100)
            ->where('financialOverview.monthly.0.income', 1000)
            ->where('financialOverview.monthly.8.result', 100)
            ->where('financialOverview.monthly.9.income', null)
            ->where('financialOverview.balances.treasury', 700)
            ->where('financialOverview.fiscal_status', 'open')
            ->where('financialOverview.posted_entries', 3)
            ->where('financialOverview.pending_entries', 1)
            ->where('financialOverview.vat_payable', null)
            ->where('financialOverview.pending_documents', null)
            ->has('financialOverview.entries', 4)
            ->has('financialOverview.activity', 3));
        $this->assertSame(7, JournalEntry::count());
    }

    public function test_financial_summary_is_not_exposed_without_permission_even_on_partial_reload(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'technician']));
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('financialOverview', null));
        $this->get('/dashboard', ['X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/dashboard')), 'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'dashboard', 'X-Inertia-Partial-Data' => 'financialOverview'])
            ->assertOk()->assertJsonPath('props.financialOverview', null);
    }

    public function test_document_summary_respects_source_permissions_and_empty_is_not_unavailable(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28));
        SalesDocument::create(['type' => 'invoice', 'number' => 'HOME-INV', 'status' => 'draft', 'issue_date' => '2026-09-28', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'customer_snapshot' => []]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get('/dashboard?year=2026')->assertInertia(fn (Assert $page) => $page
            ->has('financialOverview.documents', 1)
            ->where('financialOverview.documents.0.number', 'HOME-INV')
            ->where('financialOverview.documents.0.status', 'draft')
            ->where('financialOverview.comparison.income', null)
            ->has('financialOverview.tasks', 0));
        $permission = Permission::where('key', 'manage-installations')->firstOrFail();
        $user->permissionOverrides()->attach($permission, ['allowed' => false]);
        $this->get('/dashboard?year=2026')->assertInertia(fn (Assert $page) => $page->has('financialOverview.documents', 0));
    }

    public function test_recent_documents_include_real_expense_invoices_and_receipts(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28));
        $this->actingAs(User::factory()->create());
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => auth()->id(), 'number' => 'HOME-EXP', 'currency_code' => 'USD', 'document_number' => 'SUP-007', 'type' => 'receipt', 'expense_date' => '2026-09-28', 'merchant_name' => 'Supplier', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'status' => 'pending']);
        $this->get('/dashboard?year=2026')->assertInertia(fn (Assert $page) => $page
            ->where('financialOverview.documents_available', true)
            ->has('financialOverview.documents', 1)
            ->where('financialOverview.documents.0.number', 'SUP-007')
            ->where('financialOverview.documents.0.currency', 'USD')
            ->where('financialOverview.documents.0.type', 'receipt')
            ->where('financialOverview.documents.0.href', '/expenses/'.$expense->id));
    }

    public function test_future_year_has_no_invented_comparison_or_monthly_values_and_invalid_year_is_rejected(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28));
        $this->actingAs(User::factory()->create());
        $this->get('/dashboard?year=2027')->assertInertia(fn (Assert $page) => $page
            ->where('financialOverview.comparison.income', null)
            ->where('financialOverview.monthly.0.income', null)
            ->where('financialOverview.elapsed_months', 0)
            ->where('financialOverview.fiscal_status', null));
        $this->get('/dashboard?year=oops')->assertSessionHasErrors('year');
    }

    public function test_calendar_progress_uses_real_fiscal_dates_and_clamps_outside_period(): void
    {
        $this->actingAs(User::factory()->create());
        FiscalYear::updateOrCreate(['year' => 2026], ['starts_on' => '2026-04-01', 'ends_on' => '2026-09-30', 'status' => 'open']);
        $this->travelTo(now()->setDate(2026, 7, 1)->startOfDay());
        $this->get('/dashboard?year=2026')->assertInertia(fn (Assert $page) => $page
            ->where('financialOverview.fiscal_dates.starts_on', '2026-04-01')
            ->where('financialOverview.fiscal_dates.ends_on', '2026-09-30')
            ->where('financialOverview.fiscal_progress', 50));
        $this->travelTo(now()->setDate(2026, 3, 1)->startOfDay());
        $this->get('/dashboard?year=2026')->assertInertia(fn (Assert $page) => $page->where('financialOverview.fiscal_progress', 0));
        $this->travelTo(now()->setDate(2026, 10, 6)->startOfDay());
        $this->get('/dashboard?year=2026')->assertInertia(fn (Assert $page) => $page->where('financialOverview.fiscal_progress', 100));
        $this->get('/dashboard?year=2027')->assertInertia(fn (Assert $page) => $page
            ->where('financialOverview.fiscal_dates', null)->where('financialOverview.fiscal_progress', null));
    }

    private function entry(string $date, string $status, string $kind, float $income, float $expense): void
    {
        $entry = JournalEntry::create(['entry_date' => $date, 'number' => 'HOME-'.(JournalEntry::count() + 1), 'description' => 'Dashboard fixture', 'status' => $status, 'entry_kind' => $kind, 'posted_at' => $status === 'posted' ? $date.' 10:00:00' : null]);
        foreach ([['700', 0, $income], ['600', $expense, 0], ['572', $income - $expense, 0]] as [$code, $debit, $credit]) {
            $entry->lines()->create(['ledger_account_id' => LedgerAccount::where('code', $code)->value('id'), 'debit' => $debit, 'credit' => $credit]);
        }
    }
}
