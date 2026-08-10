<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountingRecurringRun;
use App\Models\AccountingTemplate;
use App\Models\AuditLog;
use App\Models\CorporateTaxReturn;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\Installation;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\CorporateTaxCalculator;
use App\Services\Accounting\JournalPoster;
use App\Services\Accounting\RecurringEntryProcessor;
use App\Services\Ai\BusinessContextBuilder;
use App\Services\Ai\RelevantBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_arevia_business_context_respects_financial_permissions(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $builder = app(BusinessContextBuilder::class);

        $commercialContext = $builder->build($commercial);
        $this->assertStringContainsString('Company:', $commercialContext);
        $this->assertStringContainsString('Installations:', $commercialContext);
        $this->assertStringContainsString('Bank accounts:', $commercialContext);
        $this->assertStringNotContainsString('Bank accounts:', $builder->build($technician));
    }

    public function test_arevia_finds_specific_crm_records_and_hides_finance_from_technicians(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $customer = Person::create(['category' => 'customer', 'company_name' => 'Solar Córdoba SL', 'first_name' => 'Ana', 'email' => 'ana@solar.test']);
        $installation = Installation::create(['person_id' => $customer->id, 'project_reference' => 'SOL-CORD-77', 'site_name' => 'Cubierta Córdoba', 'site_address_line_1' => 'Calle Solar', 'site_city' => 'Córdoba', 'site_country' => 'Spain', 'service_type' => 'solar_pv', 'status' => 'active', 'system_power_kw' => 12.5]);
        SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'FAC-AREVIA-77', 'status' => 'issued', 'issue_date' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'customer_snapshot' => []]);
        $retriever = app(RelevantBusinessContext::class);

        $pageContext = $retriever->search($technician, '¿Cómo va esta instalación?', '/installations/'.$installation->id);
        $this->assertStringContainsString('SOL-CORD-77', $pageContext);
        $this->assertStringNotContainsString('FAC-AREVIA-77', $pageContext);
        $this->assertStringContainsString('FAC-AREVIA-77', $retriever->search($commercial, 'Revisa FAC-AREVIA-77'));
    }

    public function test_admin_can_manage_custom_accounts_without_deleting_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('accounting.accounts.store'), ['code' => '629001', 'name' => 'Custom services', 'type' => 'expense', 'parent_code' => '629', 'is_postable' => true, 'is_active' => true])->assertSessionHasNoErrors();
        $account = LedgerAccount::where('code', '629001')->firstOrFail();
        $this->patch(route('accounting.accounts.update', $account), ['name' => 'Custom external services', 'type' => 'expense', 'parent_code' => '629', 'is_postable' => true, 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame('Custom external services', $account->fresh()->name);
        $this->delete(route('accounting.accounts.destroy', $account))->assertSessionHasNoErrors();
        $this->assertModelMissing($account);
        $this->delete(route('accounting.accounts.destroy', LedgerAccount::where('code', '629')->firstOrFail()))->assertSessionHasErrors('account');
    }

    public function test_custom_template_executes_a_balanced_journal_entry(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user)->post(route('accounting.templates.store'), ['name' => 'Split expense', 'lines' => [['account_code' => '626', 'side' => 'debit', 'percentage' => 70], ['account_code' => '629', 'side' => 'debit', 'percentage' => 30], ['account_code' => '572', 'side' => 'credit', 'percentage' => 100]]])->assertSessionHasNoErrors();
        $template = AccountingTemplate::where('name', 'Split expense')->firstOrFail();
        $this->post(route('accounting.templates.execute', $template), ['entry_date' => now()->toDateString(), 'amount' => 10.01, 'description' => 'Mixed fee'])->assertSessionHasNoErrors();
        $entry = JournalEntry::where('entry_kind', 'template')->with('lines')->firstOrFail();
        $this->assertEqualsWithDelta(10.01, $entry->lines->sum('debit'), .001);
        $this->assertEqualsWithDelta(10.01, $entry->lines->sum('credit'), .001);
    }

    public function test_recurring_templates_post_and_reverse_accruals_idempotently(): void
    {
        $this->travelTo('2026-08-01 08:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $template = AccountingTemplate::create([
            'name' => 'Monthly insurance accrual',
            'description' => 'Accrued insurance expense',
            'created_by' => $admin->id,
        ]);
        $template->lines()->createMany([
            ['account_code' => '625', 'side' => 'debit', 'percentage' => 100, 'position' => 0],
            ['account_code' => '410', 'side' => 'credit', 'percentage' => 100, 'position' => 1],
        ]);

        $this->actingAs($admin)->patch(route('accounting.templates.schedule', $template), [
            'recurrence' => 'monthly',
            'recurring_amount' => 1200,
            'next_run_on' => '2026-07-31',
            'reversal_days' => 1,
        ])->assertSessionHasNoErrors();

        $this->get(route('accounting.index', ['year' => 2026, 'month' => 7]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('periodSummary.unpostedItems', 1));

        $this->post(route('accounting.recurring.run'))->assertSessionHasNoErrors();
        $run = AccountingRecurringRun::with(['journalEntry.lines', 'reversalEntry.lines'])->firstOrFail();
        $this->assertSame('reversed', $run->status);
        $this->assertSame('2026-07-31', $run->scheduled_on->toDateString());
        $this->assertSame('2026-08-01', $run->reversal_due_on->toDateString());
        $this->assertSame('accrual', $run->journalEntry->entry_kind);
        $this->assertSame('reversal', $run->reversalEntry->entry_kind);
        $this->assertSame($run->journal_entry_id, $run->reversalEntry->reverses_entry_id);
        $this->assertEqualsWithDelta(1200, $run->journalEntry->lines->sum('debit'), .001);
        $this->assertEqualsWithDelta(1200, $run->journalEntry->lines->sum('credit'), .001);
        $this->assertEqualsWithDelta(1200, $run->reversalEntry->lines->sum('debit'), .001);
        $this->assertSame('2026-08-31', $template->fresh()->next_run_on->toDateString());

        $result = app(RecurringEntryProcessor::class)->process(userId: $admin->id);
        $this->assertSame(['posted' => 0, 'reversed' => 0, 'failed' => 0], $result);
        $this->assertSame(2, JournalEntry::count());

        $this->get(route('accounting.templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('accounting/templates')
                ->has('templates')
                ->has('recurringRuns', 1)
                ->where('recurringRuns.0.status', 'reversed')
                ->where('recurringRuns.0.journal_entry.number', $run->journalEntry->number)
                ->where('recurringRuns.0.reversal_entry.number', $run->reversalEntry->number));

        $this->delete(route('accounting.templates.destroy', $template))->assertSessionHasErrors('template');
        $this->assertModelExists($template);
    }

    public function test_recurring_processor_records_failure_without_advancing_a_closed_period(): void
    {
        $this->travelTo('2026-08-01 08:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $year = FiscalYear::where('year', 2026)->firstOrFail();
        AccountingPeriod::create([
            'fiscal_year_id' => $year->id,
            'year' => 2026,
            'month' => 7,
            'status' => 'closed',
            'closed_by' => $admin->id,
            'closed_at' => now(),
        ]);
        $template = AccountingTemplate::create([
            'name' => 'Locked period rent',
            'recurring_amount' => 500,
            'recurrence' => 'monthly',
            'next_run_on' => '2026-07-31',
            'created_by' => $admin->id,
        ]);
        $template->lines()->createMany([
            ['account_code' => '621', 'side' => 'debit', 'percentage' => 100, 'position' => 0],
            ['account_code' => '410', 'side' => 'credit', 'percentage' => 100, 'position' => 1],
        ]);

        $result = app(RecurringEntryProcessor::class)->process(userId: $admin->id);

        $this->assertSame(['posted' => 0, 'reversed' => 0, 'failed' => 1], $result);
        $this->assertDatabaseHas('accounting_recurring_runs', [
            'accounting_template_id' => $template->id,
            'status' => 'failed',
            'journal_entry_id' => null,
        ]);
        $this->assertSame('2026-07-31', $template->fresh()->next_run_on->toDateString());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_corporate_tax_uses_2026_micro_company_bands_and_can_be_prepared(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $year = FiscalYear::where('year', now()->year)->firstOrFail();
        app(JournalPoster::class)->post(now()->toDateString(), 'Trading result', [['account_code' => '572', 'debit' => 100000, 'credit' => 0], ['account_code' => '705', 'debit' => 0, 'credit' => 100000]], $admin->id);
        $payload = ['fiscal_year_id' => $year->id, 'regime' => 'micro', 'prior_year_revenue' => 500000, 'permanent_positive' => 0, 'permanent_negative' => 0, 'temporary_positive' => 0, 'temporary_negative' => 0, 'loss_carryforward' => 0, 'deductions' => 0, 'withholdings' => 1000, 'instalment_payments' => 2000];
        $this->actingAs($admin)->post(route('corporate-tax.prepare'), $payload)->assertSessionHasNoErrors();
        $return = CorporateTaxReturn::firstOrFail();
        $this->assertSame('prepared', $return->status);
        $this->assertEqualsWithDelta(20000, $return->gross_tax, .001);
        $this->assertEqualsWithDelta(17000, $return->net_tax, .001);

        $this->post(route('corporate-tax.prepare'), [...$payload, 'prior_year_revenue' => 1000000])
            ->assertSessionHasErrors('regime');
    }

    public function test_corporate_tax_applies_the_published_transition_rates_after_2026(): void
    {
        $calculator = app(CorporateTaxCalculator::class);
        foreach ([2027 => 18500, 2028 => 18500] as $yearNumber => $expected) {
            $year = FiscalYear::create(['year' => $yearNumber, 'starts_on' => "{$yearNumber}-01-01", 'ends_on' => "{$yearNumber}-12-31", 'status' => 'open']);
            $result = $calculator->calculate($year, ['regime' => 'micro', 'permanent_positive' => 100000, 'permanent_negative' => 0, 'temporary_positive' => 0, 'temporary_negative' => 0, 'loss_carryforward' => 0, 'deductions' => 0, 'withholdings' => 0, 'instalment_payments' => 0]);
            $this->assertEqualsWithDelta($expected, $result['gross_tax'], 0.001);
            $this->assertSame('aeat-transition-2025-2029', $result['tax_rule_version']);
        }

        $year2029 = FiscalYear::create(['year' => 2029, 'starts_on' => '2029-01-01', 'ends_on' => '2029-12-31', 'status' => 'open']);
        $reduced = $calculator->calculate($year2029, ['regime' => 'reduced', 'permanent_positive' => 100000, 'permanent_negative' => 0, 'temporary_positive' => 0, 'temporary_negative' => 0, 'loss_carryforward' => 0, 'deductions' => 0, 'withholdings' => 0, 'instalment_payments' => 0]);
        $this->assertEqualsWithDelta(20000, $reduced['gross_tax'], 0.001);
    }

    public function test_filed_corporate_tax_return_is_immutable_and_keeps_hashed_evidence(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $year = FiscalYear::where('year', now()->year)->firstOrFail();
        $payload = ['fiscal_year_id' => $year->id, 'regime' => 'general', 'prior_year_revenue' => 2000000, 'permanent_positive' => 0, 'permanent_negative' => 0, 'temporary_positive' => 0, 'temporary_negative' => 0, 'loss_carryforward' => 0, 'deductions' => 0, 'withholdings' => 0, 'instalment_payments' => 0];

        $this->actingAs($admin)->post(route('corporate-tax.prepare'), $payload)->assertSessionHasNoErrors();
        $return = CorporateTaxReturn::firstOrFail();
        $filedOn = $year->ends_on->copy()->addMonths(7)->toDateString();
        $this->post(route('corporate-tax.file', $return), [
            'filing_reference' => 'MODELO-200-CSV',
            'filed_on' => $filedOn,
            'receipt' => UploadedFile::fake()->create('modelo-200.pdf', 12, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $return->refresh();
        $this->assertSame('filed', $return->status);
        $this->assertSame('modelo-200.pdf', $return->receipt_original_name);
        $this->assertSame(64, strlen((string) $return->receipt_sha256));
        Storage::disk('local')->assertExists($return->receipt_path);

        $this->post(route('corporate-tax.prepare'), [...$payload, 'permanent_positive' => 5000])
            ->assertSessionHasErrors('fiscal_year_id');
        $this->assertEqualsWithDelta(0, $return->fresh()->permanent_positive, 0.001);
        $this->assertSame('MODELO-200-CSV', $return->fresh()->filing_reference);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => CorporateTaxReturn::class, 'auditable_id' => $return->id, 'event' => 'updated']);
    }

    public function test_complete_official_pgc_is_loaded_with_hierarchy_and_version(): void
    {
        $this->assertSame(898, LedgerAccount::where('source', 'pgc')->count());
        $this->assertDatabaseHas('ledger_accounts', ['code' => '10', 'is_postable' => false, 'source_version' => '2024-12-21']);
        $this->assertDatabaseHas('ledger_accounts', ['code' => '100', 'parent_code' => '10', 'name' => 'Capital social']);
        $this->assertDatabaseHas('ledger_accounts', ['code' => '705', 'type' => 'income']);
        $this->assertDatabaseHas('ledger_accounts', ['code' => '629', 'type' => 'expense']);
    }

    public function test_customer_and_supplier_subaccounts_are_created_and_used_by_documents_and_payments(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'last_name' => 'Solar']);
        $supplier = Supplier::create(['legal_name' => 'Panels SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $this->assertStringStartsWith('430', $customer->fresh()->receivable_account_code);
        $this->assertStringStartsWith('400', $supplier->fresh()->payable_account_code);

        $sale = SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'FAC-PARTY-1', 'status' => 'paid', 'issue_date' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 100, 'customer_snapshot' => []]);
        $sale->payments()->create(['created_by' => $user->id, 'paid_at' => now(), 'amount' => 100, 'method' => 'bank_transfer']);
        $purchase = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'FR-PARTY-1', 'status' => 'paid', 'issue_date' => now(), 'subtotal' => 50, 'tax_amount' => 0, 'amount' => 50, 'paid_amount' => 50]);
        SupplierPayment::create(['purchase_document_id' => $purchase->id, 'created_by' => $user->id, 'paid_at' => now(), 'amount' => 50, 'method' => 'bank_transfer']);
        app(AccountingSynchronizer::class)->sync($user->id);

        foreach ([$customer->fresh()->receivable_account_code, $supplier->fresh()->payable_account_code] as $code) {
            $account = LedgerAccount::where('code', $code)->with('lines')->firstOrFail();
            $this->assertEqualsWithDelta($account->lines->sum('debit'), $account->lines->sum('credit'), 0.001);
        }
    }

    public function test_expense_mapping_is_configurable_and_used_by_automatic_posting(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $category = ExpenseCategory::create(['code' => 'ADVISORY', 'name' => 'Advisory', 'expense_account_code' => '629', 'is_active' => true]);
        $this->actingAs($user)->patch(route('accounting.mappings.expense-categories.update', $category), ['expense_account_code' => '60'])->assertSessionHasErrors('expense_account_code');
        $this->patch(route('accounting.mappings.expense-categories.update', $category), ['expense_account_code' => '623'])->assertRedirect()->assertSessionHasNoErrors();
        $expense = Expense::create(['expense_category_id' => $category->id, 'created_by' => $user->id, 'number' => 'EXP-MAP-1', 'type' => 'invoice', 'expense_date' => now(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'status' => 'pending']);
        app(AccountingSynchronizer::class)->sync($user->id);
        $entry = JournalEntry::whereMorphedTo('source', $expense)->with('lines.account')->firstOrFail();
        $this->assertSame('100.00', $entry->lines->firstWhere('account.code', '623')->debit);
        $this->assertSame('21.00', $entry->lines->firstWhere('account.code', '472')->debit);
    }

    public function test_third_party_aging_matches_customer_subaccount_balance(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Accounts', 'company_name' => 'Aging Solar SL']);
        $invoice = SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'FAC-AGING-1', 'status' => 'partial', 'issue_date' => now()->subMonths(2), 'due_date' => now()->subDays(45), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 21, 'customer_snapshot' => []]);
        $invoice->payments()->create(['created_by' => $user->id, 'paid_at' => now()->subDays(40), 'amount' => 21, 'method' => 'bank_transfer']);
        app(AccountingSynchronizer::class)->sync($user->id);

        $this->actingAs($user)->get(route('accounting.index', ['year' => now()->year]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('thirdParties.customers', 1)
            ->where('thirdParties.customers.0.accountCode', $customer->fresh()->receivable_account_code)
            ->where('thirdParties.customers.0.ledgerBalance', 100)
            ->where('thirdParties.customers.0.outstanding', 100)
            ->where('thirdParties.customers.0.days60', 100));
    }

    public function test_financial_records_generate_balanced_idempotent_journal_entries(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $invoice = SalesDocument::create(['type' => 'invoice', 'number' => 'FAC-2026-00001', 'status' => 'issued', 'issue_date' => '2026-07-01', 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 121, 'customer_snapshot' => ['first_name' => 'Ana']]);
        $invoice->payments()->create(['created_by' => $user->id, 'paid_at' => '2026-07-02', 'amount' => 121, 'method' => 'bank_transfer']);
        $category = ExpenseCategory::create(['code' => 'SERV', 'name' => 'Services', 'is_active' => true]);
        Expense::create(['expense_category_id' => $category->id, 'created_by' => $user->id, 'number' => 'EXP-2026-00001', 'type' => 'invoice', 'expense_date' => '2026-07-03', 'subtotal' => 50, 'tax_amount' => 10.5, 'amount' => 60.5, 'paid_amount' => 0, 'status' => 'pending']);

        $this->actingAs($user)->post(route('accounting.sync'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, JournalEntry::count());
        foreach (JournalEntry::with('lines')->get() as $entry) {
            $this->assertEqualsWithDelta($entry->lines->sum('debit'), $entry->lines->sum('credit'), 0.001);
        }
        $this->actingAs($user)->post(route('accounting.sync'))->assertRedirect();
        $this->assertSame(3, JournalEntry::count());
        $this->actingAs($user)->get(route('accounting.index', ['year' => 2026, 'account' => '430']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('accounting/index')
            ->where('summary.entries', 3)
            ->where('reports.profitAndLoss.income', 100)
            ->where('reports.profitAndLoss.expenses', 50)
            ->where('reports.profitAndLoss.result', 50)
            ->where('selectedAccount.code', '430')
            ->has('ledger', 2));
    }

    public function test_manual_entries_are_balanced_and_reversed_with_a_new_entry(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user)->post(route('accounting.entries.store'), [
            'entry_date' => now()->toDateString(), 'description' => 'Capital contribution',
            'lines' => [
                ['account_code' => '572', 'debit' => 1000, 'credit' => 0],
                ['account_code' => '129', 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entry = JournalEntry::firstOrFail();
        $this->assertSame('manual', $entry->entry_kind);
        $this->actingAs($user)->post(route('accounting.entries.reverse', $entry), ['entry_date' => now()->toDateString()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, JournalEntry::count());
        $this->assertSame($entry->id, JournalEntry::where('entry_kind', 'reversal')->firstOrFail()->reverses_entry_id);
        $this->actingAs($user)->post(route('accounting.entries.reverse', $entry), ['entry_date' => now()->toDateString()])->assertSessionHasErrors('entry');
    }

    public function test_monthly_close_blocks_posting_until_an_audited_reopening(): void
    {
        $this->travelTo('2026-08-06 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $commercial = User::factory()->create(['role' => 'commercial']);
        $invoice = SalesDocument::create([
            'type' => 'invoice',
            'number' => 'FAC-PERIOD-1',
            'status' => 'issued',
            'issue_date' => '2026-07-15',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 21,
            'amount' => 121,
            'paid_amount' => 0,
            'customer_snapshot' => [],
        ]);
        $checklist = [
            'confirmation' => '2026-07',
            'bank_reconciled' => '1',
            'receivables_reviewed' => '1',
            'payables_reviewed' => '1',
            'tax_reviewed' => '1',
            'closure_notes' => 'July reviewed by finance.',
        ];

        $this->actingAs($admin)
            ->post(route('accounting.periods.close', ['year' => 2026, 'month' => 7]), $checklist)
            ->assertSessionHasErrors('period');

        $this->post(route('accounting.sync'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => $invoice->getMorphClass(),
            'source_id' => $invoice->id,
            'event' => 'document',
        ]);

        $this->post(route('accounting.periods.close', ['year' => 2026, 'month' => 7]), $checklist)
            ->assertSessionHasNoErrors();
        $period = AccountingPeriod::firstOrFail();
        $this->assertSame('closed', $period->status);
        $this->assertSame($admin->id, $period->closed_by);
        $this->assertTrue($period->checklist['bankReconciled']);

        $entry = [
            'entry_date' => '2026-07-31',
            'description' => 'Late July adjustment',
            'lines' => [
                ['account_code' => '572', 'debit' => 10, 'credit' => 0],
                ['account_code' => '129', 'debit' => 0, 'credit' => 10],
            ],
        ];
        $this->post(route('accounting.entries.store'), $entry)
            ->assertSessionHasErrors('accounting_period');
        SalesDocument::create([
            'type' => 'invoice',
            'number' => 'FAC-PERIOD-LATE',
            'status' => 'issued',
            'issue_date' => '2026-07-31',
            'subtotal' => 20,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'amount' => 20,
            'paid_amount' => 0,
            'customer_snapshot' => [],
        ]);
        $this->post(route('accounting.sync'))
            ->assertSessionHasErrors('accounting_period');
        $this->actingAs($commercial)
            ->post(route('accounting.periods.reopen', ['year' => 2026, 'month' => 7]), ['reopen_reason' => 'Late adjustment required.'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('accounting.periods.reopen', ['year' => 2026, 'month' => 7]), ['reopen_reason' => 'Late adjustment required.'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('accounting_periods', [
            'id' => $period->id,
            'status' => 'open',
            'reopened_by' => $admin->id,
            'reopen_reason' => 'Late adjustment required.',
        ]);
        $this->post(route('accounting.entries.store'), $entry)->assertSessionHasNoErrors();
        $this->post(route('accounting.sync'))->assertSessionHasNoErrors();
        $this->assertGreaterThanOrEqual(
            2,
            AuditLog::where('auditable_type', $period->getMorphClass())
                ->where('auditable_id', $period->id)
                ->count(),
        );

        $this->get(route('accounting.index', ['year' => 2026, 'month' => 7]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedMonth', 7)
                ->where('periodSummary.unpostedItems', 0)
                ->where('periodSummary.unbalancedEntries', 0)
                ->where('accountingPeriods.0.status', 'open')
                ->where('canClosePeriods', true));
    }

    public function test_closing_regularizes_income_and_generates_next_year_opening(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $year = FiscalYear::where('year', now()->year)->firstOrFail();
        $invoice = SalesDocument::create(['type' => 'invoice', 'number' => 'FAC-CLOSE-1', 'status' => 'issued', 'issue_date' => $year->ends_on, 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 0, 'customer_snapshot' => []]);
        FiscalYear::create(['year' => $year->year + 1, 'starts_on' => $year->ends_on->copy()->addDay(), 'ends_on' => $year->ends_on->copy()->addYear(), 'status' => 'open']);
        $this->travelTo($year->ends_on->copy()->addDays(10));

        $this->actingAs($admin)->post(route('accounting.years.close', $year), ['confirmation' => (string) $year->year])
            ->assertSessionHasErrors('year');
        app(AccountingSynchronizer::class)->sync($admin->id);
        foreach (range(1, 12) as $month) {
            AccountingPeriod::create(['fiscal_year_id' => $year->id, 'year' => $year->year, 'month' => $month, 'status' => 'closed', 'closed_by' => $admin->id, 'closed_at' => now()]);
        }

        $this->actingAs($admin)->post(route('accounting.years.close', $year), ['confirmation' => (string) $year->year])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('closed', $year->fresh()->status);
        $this->assertDatabaseHas('journal_entries', ['entry_kind' => 'closing', 'source_id' => $year->id]);
        $this->get(route('accounting.index', ['year' => $year->year]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reports.profitAndLoss.income', 100)
                ->where('reports.profitAndLoss.expenses', 0)
                ->where('reports.profitAndLoss.result', 100));
        $this->assertEqualsWithDelta(100, app(CorporateTaxCalculator::class)->accountingResult($year->fresh()), 0.001);
        $this->post(route('accounting.years.opening', $year))->assertRedirect()->assertSessionHasNoErrors();
        $opening = JournalEntry::where('entry_kind', 'opening')->with('lines')->firstOrFail();
        $this->assertEqualsWithDelta($opening->lines->sum('debit'), $opening->lines->sum('credit'), 0.001);
        $this->assertSame($year->year + 1, $opening->entry_date->year);
        $this->assertNotNull($invoice->fresh());
        $nextYearEntry = app(JournalPoster::class)->post(
            $opening->entry_date->copy()->addDay()->toDateString(),
            'Entry after opening',
            [
                ['account_code' => '572', 'debit' => 50, 'credit' => 0],
                ['account_code' => '129', 'debit' => 0, 'credit' => 50],
            ],
            $admin->id,
        );

        $this->post(route('fiscal-settings.years.reopen', $year), [
            'confirmation' => (string) $year->year,
            'reopen_reason' => 'Late audited adjustment required.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('open', $year->fresh()->status);
        $this->assertNull($year->fresh()->closed_at);
        $this->assertSame($admin->id, $year->fresh()->reopened_by);
        $this->assertSame('Late audited adjustment required.', $year->fresh()->reopen_reason);
        $this->assertDatabaseHas('journal_entries', ['entry_kind' => 'closing', 'source_id' => $year->id]);
        $this->assertDatabaseHas('journal_entries', ['entry_kind' => 'opening', 'source_id' => $year->id]);
        $this->assertSame(1, JournalEntry::where('entry_kind', 'closing_reversal')->count());
        $this->assertSame(1, JournalEntry::where('entry_kind', 'opening_reversal')->count());
        $this->assertNotNull($nextYearEntry->fresh());
        $followingEntry = app(JournalPoster::class)->post(
            $nextYearEntry->entry_date->copy()->addDay()->toDateString(),
            'Entry after deleted opening',
            [
                ['account_code' => '572', 'debit' => 25, 'credit' => 0],
                ['account_code' => '129', 'debit' => 0, 'credit' => 25],
            ],
            $admin->id,
        );
        $this->assertSame('AST-'.($year->year + 1).'-000004', $followingEntry->number);

        $this->post(route('fiscal-settings.years.close', $year), ['confirmation' => (string) $year->year])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('closed', $year->fresh()->status);
        $this->assertSame(2, JournalEntry::where('entry_kind', 'closing')->count());
        $this->assertSame(1, JournalEntry::where('entry_kind', 'closing')->whereDoesntHave('reversal')->count());
    }
}
