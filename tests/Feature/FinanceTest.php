<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankFinancialProduct;
use App\Models\BankReconciliation;
use App\Models\BankStatementImport;
use App\Models\CollectionAction;
use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAlertAction;
use App\Models\FinancialBudget;
use App\Models\FinancialBudgetPeriod;
use App\Models\FinancialBudgetVersion;
use App\Models\FinancialCategory;
use App\Models\FinancialForecastItem;
use App\Models\LedgerAccount;
use App\Models\PaymentBatch;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Finance\FinancialTaxonomyComparison;
use App\Services\Finance\TreasuryForecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_exposes_daily_bank_position_and_statement_integrity(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Operating EUR', 'bank_name' => 'Arevantes Bank', 'currency' => 'EUR', 'opening_balance' => 1000, 'is_active' => true]);
        $statementImport = BankStatementImport::create([
            'bank_account_id' => $account->id,
            'imported_by' => $user->id,
            'filename' => 'august.csv',
            'file_hash' => hash('sha256', 'august-statement'),
            'format' => 'csv',
            'created_transactions' => 2,
            'duplicate_transactions' => 0,
            'control_valid' => false,
        ]);
        $account->transactions()->create(['bank_statement_import_id' => $statementImport->id, 'booked_on' => '2026-08-04', 'amount' => -100, 'currency' => 'EUR', 'description' => 'Pending supplier payment', 'import_hash' => hash('sha256', 'position-pending'), 'status' => 'pending']);
        $account->transactions()->create(['bank_statement_import_id' => $statementImport->id, 'booked_on' => '2026-08-05', 'amount' => 50, 'currency' => 'EUR', 'description' => 'Reconciled receipt', 'import_hash' => hash('sha256', 'position-reconciled'), 'status' => 'reconciled']);

        $this->actingAs($user)->get(route('finance.index', ['year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bankPosition.0.name', 'Operating EUR')
                ->where('bankPosition.0.bankName', 'Arevantes Bank')
                ->where('bankPosition.0.balance', 950)
                ->where('bankPosition.0.pendingTransactions', 1)
                ->where('bankPosition.0.pendingAmount', -100)
                ->where('bankPosition.0.lastTransactionOn', '2026-08-05')
                ->where('bankPosition.0.statementControl', 'mismatch'));
    }

    public function test_financial_dashboard_aggregates_cash_vat_and_outstanding_balances(): void
    {
        $this->travelTo('2026-07-26');
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Supplier SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $sale = SalesDocument::create(['type' => 'invoice', 'number' => 'FAC-2026-00001', 'status' => 'partial', 'issue_date' => '2026-07-01', 'due_date' => '2026-07-31', 'issued_at' => now(), 'locked_at' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 60, 'customer_snapshot' => ['first_name' => 'Ana']]);
        $sale->payments()->create(['created_by' => $user->id, 'paid_at' => '2026-07-10', 'amount' => 60, 'method' => 'bank_transfer']);
        CollectionAction::create(['sales_document_id' => $sale->id, 'status' => 'promised', 'promised_on' => today()->subDay(), 'promised_amount' => 61, 'next_action_on' => today()]);
        $purchase = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'FR-2026-00001', 'status' => 'partial', 'issue_date' => '2026-07-02', 'due_date' => '2026-08-01', 'subtotal' => 50, 'tax_amount' => 10, 'amount' => 60, 'paid_amount' => 20]);
        SupplierPayment::create(['purchase_document_id' => $purchase->id, 'created_by' => $user->id, 'paid_at' => '2026-07-11', 'amount' => 20, 'method' => 'card']);
        Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $user->id, 'number' => 'EXP-2026-00001', 'type' => 'receipt', 'expense_date' => '2026-07-03', 'subtotal' => 25, 'tax_amount' => 5, 'amount' => 30, 'status' => 'pending']);
        FinancialBudget::create(['year' => 2026, 'month' => 7, 'category' => 'revenue', 'amount' => 120, 'created_by' => $user->id]);
        FinancialBudget::create(['year' => 2026, 'month' => 7, 'category' => 'costs', 'amount' => 80, 'created_by' => $user->id]);
        FinancialBudget::create(['year' => 2026, 'month' => 7, 'category' => 'cash_in', 'amount' => 90, 'created_by' => $user->id]);
        FinancialBudget::create(['year' => 2026, 'month' => 7, 'category' => 'cash_out', 'amount' => 30, 'created_by' => $user->id]);

        $this->actingAs($user)->get(route('finance.index', ['year' => 2026]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('finance/index')
            ->where('summary.cashIn', 60)
            ->where('summary.cashOut', 20)
            ->where('summary.netCash', 40)
            ->where('summary.receivable', 61)
            ->where('summary.payable', 70)
            ->where('summary.overdueReceivable', 0)
            ->where('summary.overduePayable', 0)
            ->where('summary.revenue', 100)
            ->where('summary.costs', 75)
            ->where('summary.operatingResult', 25)
            ->where('summary.operatingMargin', 25)
            ->where('vat.output', 21)
            ->where('vat.input', 15)
            ->where('aging.receivable.current', 61)
            ->where('aging.payable.current', 70)
            ->where('cashFlow.6.in', 60)
            ->where('cashFlow.6.out', 20)
            ->where('cashFlow.6.net', 40)
            ->where('cashFlow.6.forecastIn', 61)
            ->where('cashFlow.6.budgetIn', 90)
            ->where('cashFlow.6.budgetOut', 30)
            ->where('cashFlow.6.budgetNet', 60)
            ->where('cashFlow.7.forecastOut', 40)
            ->where('performance.6.revenue', 100)
            ->where('performance.6.costs', 75)
            ->where('performance.6.result', 25)
            ->where('performance.6.budgetRevenue', 120)
            ->where('performance.6.budgetCosts', 80)
            ->where('performance.6.budgetResult', 40)
            ->where('budgetSummary.cashNet', 60)
            ->where('budgetSummary.actualCashNet', 40)
            ->where('budgetSummary.cashVariance', -20)
            ->where('budgetAlerts.0.type', 'cash')
            ->where('budgetAlerts.0.month', 7)
            ->where('budgetAlerts.0.severity', 'critical')
            ->where('budgetAlerts.1.type', 'operating')
            ->where('budgetAlerts.1.month', 7)
            ->where('alertSettings.operatingVariancePercent', 10)
            ->where('alertSettings.cashVariancePercent', 10)
            ->where('alertSettings.liquidityBufferAmount', 0)
            ->where('budgetPeriod.status', 'draft')
            ->where('budgetPeriod.configuredValues', 4)
            ->where('budgetPeriod.requiredValues', 48)
            ->where('receivables.0.promise_breached', true)
            ->where('kpis.brokenPromises', 1)
            ->has('cashFlow', 12)
            ->has('performance', 12)
            ->has('budgetAlerts', 2));

        $this->actingAs($user)->get(route('finance.export', ['type' => 'cash', 'year' => 2026]))->assertOk()->assertDownload('finance-cash-2026.csv');
        $this->actingAs($user)->get(route('finance.export', ['type' => 'vat', 'year' => 2026]))->assertOk()->assertDownload('finance-vat-2026.csv');
        $this->actingAs($user)->get(route('finance.export', ['type' => 'receivables', 'year' => 2026]))->assertOk()->assertDownload('finance-receivables-2026.csv');
        $this->actingAs($user)->get(route('finance.export', ['type' => 'payables', 'year' => 2026]))->assertOk()->assertDownload('finance-payables-2026.csv');
    }

    public function test_financial_dashboard_defaults_to_the_current_year(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);

        $this->actingAs($user)->get(route('finance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('finance/index')
            ->where('year', now()->year));
    }

    public function test_budget_forecast_and_ledger_use_the_common_financial_taxonomy(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $sales = FinancialCategory::where('code', 'sales_revenue')->firstOrFail();
        $otherRevenue = FinancialCategory::where('code', 'other_revenue')->firstOrFail();
        $payroll = FinancialCategory::where('code', 'payroll_payments')->firstOrFail();

        $this->actingAs($user)->post(route('finance.forecast-items.store'), [
            'direction' => 'in',
            'financial_category_id' => $payroll->id,
            'description' => 'Invalid incoming payroll',
            'amount' => 100,
            'due_on' => today()->addDay()->toDateString(),
            'probability' => 100,
        ])->assertSessionHasErrors('financial_category_id');
        $this->assertDatabaseCount('financial_forecast_items', 0);

        $this->post(route('finance.forecast-items.store'), [
            'direction' => 'out',
            'financial_category_id' => $payroll->id,
            'description' => 'Monthly payroll',
            'amount' => 100,
            'due_on' => today()->addDay()->toDateString(),
            'probability' => 100,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('financial_forecast_items', [
            'category' => 'payroll_payments',
            'financial_category_id' => $payroll->id,
        ]);

        foreach ([[$sales, 100], [$otherRevenue, 50]] as [$category, $amount]) {
            $this->post(route('finance.budgets.store'), [
                'year' => now()->year,
                'month' => 1,
                'category' => 'revenue',
                'financial_category_id' => $category->id,
                'amount' => $amount,
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('financial_budgets', 2);
        $this->get(route('finance.index', ['year' => now()->year]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('performance.0.budgetRevenue', 150)
                ->where('budgetPeriod.configuredValues', 1)
                ->has('financialCategories', 12));
        $this->assertSame('sales_revenue', LedgerAccount::where('code', '700')->firstOrFail()->financialCategory?->code);
        $this->assertSame('payroll_costs', LedgerAccount::where('code', '640')->firstOrFail()->financialCategory?->code);
    }

    public function test_accounting_managers_can_extend_taxonomy_without_mutating_system_categories(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($technician)->post(route('finance.categories.store'), [
            'code' => 'vehicle_fleet_costs',
            'name' => 'Vehicle fleet costs',
            'reporting_group' => 'costs',
        ])->assertForbidden();

        $this->actingAs($user)->post(route('finance.categories.store'), [
            'code' => 'vehicle_fleet_costs',
            'name' => 'Vehicle fleet costs',
            'reporting_group' => 'costs',
        ])->assertSessionHasNoErrors();
        $custom = FinancialCategory::where('code', 'vehicle_fleet_costs')->firstOrFail();
        $this->assertFalse($custom->is_system);
        $this->assertSame($user->id, $custom->created_by);

        $system = FinancialCategory::where('code', 'operating_costs')->firstOrFail();
        $this->patch(route('finance.categories.update', $system), [
            'name' => 'Changed system category',
            'is_active' => false,
        ])->assertSessionHasErrors('financial_category');
        $this->assertTrue($system->fresh()->is_active);

        $this->patch(route('finance.categories.update', $custom), [
            'name' => 'Fleet and vehicles',
            'is_active' => false,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('financial_categories', [
            'id' => $custom->id,
            'name' => 'Fleet and vehicles',
            'is_active' => false,
        ]);
        $this->post(route('finance.budgets.store'), [
            'year' => now()->year,
            'month' => 1,
            'category' => 'costs',
            'financial_category_id' => $custom->id,
            'amount' => 100,
        ])->assertSessionHasErrors('financial_category_id');
    }

    public function test_taxonomy_comparison_reconciles_planning_with_actuals_and_source_documents(): void
    {
        $this->travelTo('2026-08-11');
        $user = User::factory()->create(['role' => 'commercial']);
        $salesCategory = FinancialCategory::where('code', 'sales_revenue')->firstOrFail();
        $collectionsCategory = FinancialCategory::where('code', 'customer_collections')->firstOrFail();
        FinancialBudget::create([
            'year' => 2026,
            'month' => 8,
            'category' => 'revenue',
            'financial_category_id' => $salesCategory->id,
            'amount' => 1000,
            'created_by' => $user->id,
        ]);
        FinancialForecastItem::create([
            'direction' => 'in',
            'category' => 'customer_collections',
            'financial_category_id' => $collectionsCategory->id,
            'description' => 'Expected August collection',
            'amount' => 500,
            'baseline_amount' => 500,
            'due_on' => '2026-08-15',
            'baseline_due_on' => '2026-08-15',
            'probability' => 100,
            'status' => 'planned',
            'created_by' => $user->id,
        ]);
        $sale = SalesDocument::create([
            'type' => 'invoice',
            'number' => 'FAC-TAXONOMY-1',
            'status' => 'partial',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-31',
            'issued_at' => now(),
            'locked_at' => now(),
            'subtotal' => 800,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'amount' => 800,
            'paid_amount' => 300,
            'customer_snapshot' => [],
        ]);
        app(AccountingSynchronizer::class)->syncSalesDocument($sale, $user->id);
        $sale->payments()->create([
            'created_by' => $user->id,
            'paid_at' => '2026-08-10',
            'amount' => 300,
            'method' => 'bank_transfer',
        ]);

        $comparison = app(FinancialTaxonomyComparison::class)->build(2026, $collectionsCategory->id);
        $sales = $comparison['rows']->firstWhere('code', 'sales_revenue');
        $collections = $comparison['rows']->firstWhere('code', 'customer_collections');

        $this->assertSame(1000.0, $sales['budget']);
        $this->assertSame(800.0, $sales['actual']);
        $this->assertSame(-200.0, $sales['budgetVariance']);
        $this->assertSame(500.0, $collections['forecast']);
        $this->assertSame(300.0, $collections['actual']);
        $this->assertSame('/sales/'.$sale->id, $comparison['evidence']->sole()['href']);

        $this->actingAs($user)->get(route('accounting.index', [
            'year' => 2026,
            'financial_category' => $salesCategory->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('selectedFinancialCategory.code', 'sales_revenue')
            ->has('entries', 1)
            ->where('entries.0.source_href', '/sales/'.$sale->id));
    }

    public function test_annual_budget_can_be_copied_adjusted_overwritten_and_deleted(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $sourceYear = now()->year - 1;
        $targetYear = now()->year;
        FinancialBudget::create(['year' => $sourceYear, 'month' => 1, 'category' => 'revenue', 'amount' => 1000, 'created_by' => $user->id]);
        FinancialBudget::create(['year' => $sourceYear, 'month' => 1, 'category' => 'cash_in', 'amount' => 800, 'created_by' => $user->id]);
        $existing = FinancialBudget::create(['year' => $targetYear, 'month' => 1, 'category' => 'revenue', 'amount' => 750, 'created_by' => $user->id]);

        $this->actingAs($user)->post(route('finance.budgets.copy'), [
            'source_year' => $sourceYear,
            'target_year' => $targetYear,
            'adjustment_percent' => 10,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('financial_budgets', [
            'id' => $existing->id,
            'amount' => 750,
        ]);
        $this->assertDatabaseHas('financial_budgets', [
            'year' => $targetYear,
            'month' => 1,
            'category' => 'cash_in',
            'amount' => 880,
        ]);

        $this->post(route('finance.budgets.copy'), [
            'source_year' => $sourceYear,
            'target_year' => $targetYear,
            'adjustment_percent' => -5,
            'overwrite' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('financial_budgets', [
            'id' => $existing->id,
            'amount' => 950,
        ]);

        $this->delete(route('finance.budgets.destroy', $existing))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('financial_budgets', ['id' => $existing->id]);
    }

    public function test_budget_csv_can_be_exported_with_a_complete_template(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        FinancialBudget::create([
            'year' => 2026,
            'month' => 3,
            'category' => 'revenue',
            'amount' => 1250.50,
            'notes' => 'Sales plan',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('finance.export', ['type' => 'budget', 'year' => 2026]));
        $response->assertOk()->assertDownload('finance-budget-2026.csv');
        $this->assertStringContainsString('2026,3,revenue,1250.50,"Sales plan"', $response->streamedContent());

        $template = $this->get(route('finance.export', ['type' => 'budget_template', 'year' => 2026]));
        $template->assertOk()->assertDownload('finance-budget_template-2026.csv');
        $lines = array_values(array_filter(explode("\n", trim($template->streamedContent()))));
        $this->assertCount(49, $lines);
        $this->assertStringContainsString('2026,3,revenue,1250.50,"Sales plan"', $template->streamedContent());
    }

    public function test_budget_csv_import_is_validated_before_transactional_upsert(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $existing = FinancialBudget::create([
            'year' => 2026,
            'month' => 1,
            'category' => 'revenue',
            'amount' => 750,
            'created_by' => $user->id,
        ]);
        $csv = "\xEF\xBB\xBFyear;month;category;amount;notes\n2026;1;revenue;900;Keep existing\n2026;2;cash_in;1.234,56;February collections\n";

        $this->actingAs($user)->post(route('finance.budgets.import'), [
            'year' => 2026,
            'budget_file' => UploadedFile::fake()->createWithContent('budget.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('750.00', $existing->fresh()->amount);
        $this->assertDatabaseHas('financial_budgets', [
            'year' => 2026,
            'month' => 2,
            'category' => 'cash_in',
            'amount' => 1234.56,
            'notes' => 'February collections',
        ]);

        $invalid = "year,month,category,amount,notes\n2026,3,costs,100,Valid row\n2026,13,cash_out,20,Invalid month\n";
        $this->post(route('finance.budgets.import'), [
            'year' => 2026,
            'budget_file' => UploadedFile::fake()->createWithContent('invalid.csv', $invalid),
            'overwrite' => true,
        ])->assertRedirect()->assertSessionHasErrors('budget_file');
        $this->assertDatabaseMissing('financial_budgets', [
            'year' => 2026,
            'month' => 3,
            'category' => 'costs',
        ]);

        $overwrite = "year,month,category,amount,notes\n2026,1,revenue,950,Updated plan\n";
        $this->post(route('finance.budgets.import'), [
            'year' => 2026,
            'budget_file' => UploadedFile::fake()->createWithContent('overwrite.csv', $overwrite),
            'overwrite' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('financial_budgets', [
            'id' => $existing->id,
            'amount' => 950,
            'notes' => 'Updated plan',
        ]);
    }

    public function test_financial_alert_thresholds_are_configurable_and_ignore_future_months(): void
    {
        $this->travelTo('2026-07-26');
        $user = User::factory()->create(['role' => 'commercial']);
        FinancialBudget::create(['year' => 2026, 'month' => 6, 'category' => 'revenue', 'amount' => 100, 'created_by' => $user->id]);
        FinancialBudget::create(['year' => 2026, 'month' => 6, 'category' => 'cash_in', 'amount' => 200, 'created_by' => $user->id]);
        FinancialBudget::create(['year' => 2026, 'month' => 8, 'category' => 'revenue', 'amount' => 500, 'created_by' => $user->id]);

        $this->actingAs($user)->patch(route('finance.alert-settings.update'), [
            'operating_variance_alert_percent' => 20,
            'cash_variance_alert_percent' => 25,
            'liquidity_buffer_amount' => 4000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('company_profiles', [
            'operating_variance_alert_percent' => 20,
            'cash_variance_alert_percent' => 25,
            'liquidity_buffer_amount' => 4000,
        ]);
        $this->get(route('finance.index', ['year' => 2026]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('alertSettings.operatingVariancePercent', 20)
            ->where('alertSettings.cashVariancePercent', 25)
            ->where('alertSettings.liquidityBufferAmount', 4000)
            ->where('budgetAlerts.0.type', 'cash')
            ->where('budgetAlerts.0.month', 6)
            ->where('budgetAlerts.1.type', 'operating')
            ->where('budgetAlerts.1.month', 6)
            ->where('budgetAlerts.2.type', 'liquidity')
            ->where('budgetAlerts.2.severity', 'warning')
            ->has('budgetAlerts', 3));
    }

    public function test_financial_alerts_can_be_assigned_reviewed_and_resolved(): void
    {
        $this->travelTo('2026-07-26');
        $user = User::factory()->create(['role' => 'commercial']);
        $assignee = User::factory()->create(['role' => 'commercial']);
        FinancialBudget::create([
            'year' => 2026,
            'month' => 6,
            'category' => 'revenue',
            'amount' => 1000,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->patch(route('finance.alerts.update'), [
            'year' => 2026,
            'alert_key' => 'operating:6',
            'status' => 'in_review',
            'assigned_to' => $assignee->id,
            'notes' => 'Review delayed June invoicing with the commercial team.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $action = FinancialAlertAction::firstOrFail();
        $this->assertSame('in_review', $action->status);
        $this->assertSame($assignee->id, $action->assigned_to);
        $this->assertNull($action->resolved_at);
        $this->get(route('finance.index', ['year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budgetAlerts.0.key', 'operating:6')
                ->where('budgetAlerts.0.status', 'in_review')
                ->where('budgetAlerts.0.assignee', $assignee->name)
                ->where('budgetAlerts.0.notes', 'Review delayed June invoicing with the commercial team.'));

        $this->patch(route('finance.alerts.update'), [
            'year' => 2026,
            'alert_key' => 'operating:6',
            'status' => 'resolved',
            'assigned_to' => $assignee->id,
            'notes' => 'June invoices were posted and the deviation was accepted.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('resolved', $action->fresh()->status);
        $this->assertSame($user->id, $action->fresh()->resolved_by);
        $this->assertNotNull($action->fresh()->resolved_at);
        $groups = collect($this->getJson(route('action-center'))->assertOk()->json('groups'));
        $this->assertNull($groups->firstWhere('label', 'Financial alerts'));
        $this->assertGreaterThanOrEqual(2, AuditLog::where('auditable_type', FinancialAlertAction::class)->count());

        $this->patch(route('finance.alerts.update'), [
            'year' => 2026,
            'alert_key' => 'operating:13',
            'status' => 'open',
            'notes' => 'Invalid alert.',
        ])->assertRedirect()->assertSessionHasErrors('alert_key');
    }

    public function test_forecast_settlement_preserves_baseline_and_reports_accuracy(): void
    {
        $this->travelTo('2026-08-06');
        $user = User::factory()->create(['role' => 'commercial']);

        $this->actingAs($user)->post(route('finance.forecast-items.store'), [
            'direction' => 'out',
            'category' => 'insurance',
            'description' => 'Annual insurance premium',
            'amount' => 1000,
            'due_on' => '2026-08-01',
            'probability' => 100,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item = FinancialForecastItem::firstOrFail();
        $this->assertSame('1000.00', $item->baseline_amount);
        $this->assertSame('2026-08-01', $item->baseline_due_on->toDateString());

        $this->patch(route('finance.forecast-items.update', $item), [
            'status' => 'planned',
            'amount' => 1200,
            'due_on' => '2026-08-05',
            'probability' => 100,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('1200.00', $item->fresh()->amount);
        $this->assertSame('1000.00', $item->fresh()->baseline_amount);
        $this->assertSame('2026-08-01', $item->fresh()->baseline_due_on->toDateString());

        $this->patch(route('finance.forecast-items.update', $item), [
            'status' => 'settled',
        ])->assertRedirect()->assertSessionHasErrors(['actual_amount', 'settled_on']);

        $this->patch(route('finance.forecast-items.update', $item), [
            'status' => 'settled',
            'actual_amount' => 1100,
            'settled_on' => '2026-08-06',
            'settlement_notes' => 'Paid by direct debit.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('settled', $item->status);
        $this->assertSame('1100.00', $item->actual_amount);
        $this->assertSame('2026-08-06', $item->settled_on->toDateString());
        $this->assertSame($user->id, $item->settled_by);
        $this->get(route('finance.index', ['year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('forecastItems.0.baseline_amount', '1000.00')
                ->where('forecastItems.0.actual_amount', '1100.00')
                ->where('forecastItems.0.settler.name', $user->name)
                ->where('forecastAccuracy.settledCount', 1)
                ->where('forecastAccuracy.plannedTotal', 1000)
                ->where('forecastAccuracy.actualTotal', 1100)
                ->where('forecastAccuracy.amountVariance', 100)
                ->where('forecastAccuracy.meanAbsolutePercentageError', 10)
                ->where('forecastAccuracy.averageDaysVariance', 5));

        $this->delete(route('finance.forecast-items.destroy', $item))
            ->assertRedirect()
            ->assertSessionHasErrors('forecast_item');
        $this->assertDatabaseHas('financial_forecast_items', ['id' => $item->id, 'status' => 'settled']);
    }

    public function test_annual_budget_approval_locks_every_mutation_until_reopened(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $admin = User::factory()->create(['role' => 'admin']);
        FinancialBudget::create(['year' => 2025, 'month' => 1, 'category' => 'revenue', 'amount' => 100, 'created_by' => $commercial->id]);

        $this->actingAs($admin)->post(route('finance.budgets.approve', 2026), [
            'confirmation' => '2026',
        ])->assertRedirect()->assertSessionHasErrors('budget');

        foreach (range(1, 12) as $month) {
            foreach (['revenue', 'costs', 'cash_in', 'cash_out'] as $category) {
                FinancialBudget::create([
                    'year' => 2026,
                    'month' => $month,
                    'category' => $category,
                    'amount' => $month * 100,
                    'created_by' => $commercial->id,
                ]);
            }
        }

        $this->actingAs($commercial)->post(route('finance.budgets.approve', 2026), [
            'confirmation' => '2026',
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('finance.budgets.approve', 2026), [
            'confirmation' => '2026',
            'approval_notes' => 'Board-approved annual plan.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $period = FinancialBudgetPeriod::firstOrFail();
        $this->assertSame('approved', $period->status);
        $this->assertSame(1, $period->current_revision);
        $this->assertSame($admin->id, $period->approved_by);
        $this->assertNotNull($period->approved_at);
        $firstVersion = FinancialBudgetVersion::firstOrFail();
        $this->assertSame(1, $firstVersion->revision);
        $this->assertSame($admin->id, $firstVersion->approved_by);
        $this->assertSame('Board-approved annual plan.', $firstVersion->approval_notes);
        $this->assertSame('100.00', collect($firstVersion->values)->firstWhere(fn (array $value) => $value['month'] === 1 && $value['category'] === 'revenue')['amount']);
        $lockedBudget = FinancialBudget::where(['year' => 2026, 'month' => 1, 'category' => 'revenue'])->firstOrFail();

        $this->actingAs($commercial)->post(route('finance.budgets.store'), [
            'year' => 2026,
            'month' => 1,
            'category' => 'revenue',
            'amount' => 999,
        ])->assertRedirect()->assertSessionHasErrors('budget');
        $this->post(route('finance.budgets.copy'), [
            'source_year' => 2025,
            'target_year' => 2026,
            'adjustment_percent' => 0,
            'overwrite' => true,
        ])->assertRedirect()->assertSessionHasErrors('budget');
        $this->post(route('finance.budgets.import'), [
            'year' => 2026,
            'budget_file' => UploadedFile::fake()->createWithContent(
                'locked.csv',
                "year,month,category,amount\n2026,1,revenue,999\n",
            ),
            'overwrite' => true,
        ])->assertRedirect()->assertSessionHasErrors('budget');
        $this->delete(route('finance.budgets.destroy', $lockedBudget))
            ->assertRedirect()
            ->assertSessionHasErrors('budget');
        $this->assertSame('100.00', $lockedBudget->fresh()->amount);

        $this->actingAs($admin)->post(route('finance.budgets.reopen', 2026), [
            'reopen_reason' => 'Revenue assumptions changed after approval.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft', $period->fresh()->status);
        $this->assertSame($admin->id, $period->fresh()->reopened_by);
        $this->assertSame('Revenue assumptions changed after approval.', $period->fresh()->reopen_reason);

        $this->actingAs($commercial)->post(route('finance.budgets.store'), [
            'year' => 2026,
            'month' => 1,
            'category' => 'revenue',
            'amount' => 999,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('999.00', $lockedBudget->fresh()->amount);

        $this->actingAs($admin)->post(route('finance.budgets.approve', 2026), [
            'confirmation' => '2026',
            'approval_notes' => 'Reforecast after commercial review.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $period->fresh()->current_revision);
        $this->assertCount(2, $period->versions);
        $this->assertSame('100.00', collect($firstVersion->fresh()->values)->firstWhere(fn (array $value) => $value['month'] === 1 && $value['category'] === 'revenue')['amount']);
        $secondVersion = FinancialBudgetVersion::where('revision', 2)->firstOrFail();
        $this->assertSame('999.00', collect($secondVersion->values)->firstWhere(fn (array $value) => $value['month'] === 1 && $value['category'] === 'revenue')['amount']);

        $this->actingAs($commercial)->get(route('finance.index', ['year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budgetPeriod.currentRevision', 2)
                ->where('budgetPeriod.versions.0.revision', 2)
                ->where('budgetPeriod.versions.0.operatingResult', 899)
                ->where('budgetPeriod.versions.1.revision', 1)
                ->where('budgetPeriod.versions.1.operatingResult', 0)
                ->has('budgetPeriod.versions', 2));

        $export = $this->get(route('finance.budgets.versions.export', [2026, $firstVersion]));
        $export->assertOk()->assertDownload('finance-budget-2026-r1.csv');
        $this->assertStringContainsString('2026,1,revenue,100.00', $export->streamedContent());
        $this->get(route('finance.budgets.versions.export', [2025, $firstVersion]))->assertNotFound();

        $this->assertGreaterThanOrEqual(2, AuditLog::where('auditable_type', FinancialBudgetPeriod::class)->count());
    }

    public function test_treasury_planning_collection_workflow_and_payment_batches_are_operational(): void
    {
        Storage::fake('local');
        $commercial = User::factory()->create(['role' => 'commercial']);
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create(['legal_name' => 'SEPA Supplier SL', 'country' => 'ES', 'iban' => 'ES9121000418450200051332', 'bic' => 'CAIXESBBXXX', 'payment_terms_days' => 30]);
        $account = BankAccount::create(['name' => 'Main payments', 'iban' => 'ES7921000813610123456789', 'currency' => 'EUR', 'opening_balance' => 5000, 'is_active' => true]);
        CompanyProfile::firstOrFail()->update(['iban' => $account->iban]);
        $sale = SalesDocument::create(['type' => 'invoice', 'number' => 'FAC-PLAN-1', 'status' => 'issued', 'issue_date' => now(), 'due_date' => now()->addDays(10), 'issued_at' => now(), 'locked_at' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'customer_snapshot' => []]);
        $purchase = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'FR-PLAN-1', 'supplier_number' => 'SUP-100', 'status' => 'registered', 'issue_date' => now(), 'due_date' => now()->addDays(12), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0]);

        $this->actingAs($commercial)
            ->post(route('finance.forecast-items.store'), ['direction' => 'out', 'category' => 'payroll', 'description' => 'Monthly payroll', 'amount' => 2500, 'due_on' => now()->addWeek()->toDateString(), 'probability' => 100])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('finance.budgets.store'), ['year' => now()->year, 'month' => now()->month, 'category' => 'revenue', 'amount' => 10000])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('finance.collections.store', $sale), ['status' => 'promised', 'promised_on' => now()->addDays(5)->toDateString(), 'promised_amount' => 121, 'next_action_on' => now()->addDays(2)->toDateString(), 'notes' => 'Customer confirmed transfer.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('finance.payment-batches.store'), ['bank_account_id' => $account->id, 'planned_on' => now()->addDays(12)->toDateString(), 'payables' => ['invoice:'.$purchase->id]])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('financial_forecast_items', ['description' => 'Monthly payroll', 'status' => 'planned']);
        $this->assertDatabaseHas('financial_budgets', ['year' => now()->year, 'category' => 'revenue', 'amount' => 10000]);
        $this->assertDatabaseHas('collection_actions', ['sales_document_id' => $sale->id, 'status' => 'promised']);
        $this->assertDatabaseHas('collection_action_events', ['collection_action_id' => $sale->collectionAction->id, 'event_type' => 'promise', 'status_to' => 'promised', 'created_by' => $commercial->id]);
        $batch = PaymentBatch::firstOrFail();
        $this->assertSame('121.00', $batch->items()->firstOrFail()->amount);
        $this->get(route('finance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('payables.0.payment_batch.number', $batch->number)
            ->where('paymentBatches.0.item_count', 1)
            ->where('paymentBatches.0.items.0.number', 'SUP-100')
            ->where('paymentBatches.0.items.0.amount', 121));
        $this->post(route('finance.payment-batches.store'), ['bank_account_id' => $account->id, 'planned_on' => now()->addDays(12)->toDateString(), 'payables' => ['invoice:'.$purchase->id]])
            ->assertSessionHasErrors('payables');
        $this->post(route('finance.payment-batches.approve', $batch))->assertForbidden();

        SupplierPayment::create(['purchase_document_id' => $purchase->id, 'created_by' => $commercial->id, 'paid_at' => today(), 'amount' => 21, 'method' => 'bank_transfer']);
        $purchase->update(['paid_amount' => 21, 'status' => 'partial']);
        $this->actingAs($admin)->post(route('finance.payment-batches.approve', $batch))->assertRedirect()->assertSessionHasNoErrors();
        $approvedItem = $batch->items()->firstOrFail()->fresh();
        $this->assertSame('100.00', $approvedItem->amount);
        $this->assertSame('SEPA Supplier SL', $approvedItem->recipient_name);
        $this->assertSame('ES9121000418450200051332', $approvedItem->recipient_iban);
        $supplier->update(['legal_name' => 'Changed after approval SL', 'iban' => 'ES6621000418401234567891']);
        $this->get(route('finance.payment-batches.sepa', $batch))->assertStatus(422);
        $this->assertSame('approved', $batch->fresh()->status);
        $this->post(route('finance.payment-batches.export', $batch))->assertRedirect()->assertSessionHasNoErrors();
        $batch->refresh();
        $this->assertSame('exported', $batch->status);
        $this->assertSame($admin->id, $batch->exported_by);
        $this->assertSame(64, strlen((string) $batch->export_sha256));
        Storage::disk('local')->assertExists($batch->export_path);
        $xml = $this->get(route('finance.payment-batches.sepa', $batch))->assertOk()->assertDownload($batch->number.'.xml')->streamedContent();
        $this->assertStringContainsString('SEPA Supplier SL', $xml);
        $this->assertStringContainsString('ES9121000418450200051332', $xml);
        $this->assertStringNotContainsString('Changed after approval SL', $xml);
        $this->get(route('finance.payment-batches.sepa', $batch))->assertOk()->assertDownload($batch->number.'.xml');
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Supplier::class, 'auditable_id' => $supplier->id, 'event' => 'updated']);
        Storage::disk('local')->put($batch->export_path, '<tampered/>');
        $this->post(route('finance.payment-batches.execute', $batch), ['paid_on' => today()->toDateString()])
            ->assertSessionHasErrors('payment_batch');
        $this->assertSame('exported', $batch->fresh()->status);
        Storage::disk('local')->put($batch->export_path, $xml);
        $this->post(route('finance.payment-batches.execute', $batch), ['paid_on' => today()->toDateString()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('paid', $batch->fresh()->status);
        $this->assertSame($admin->id, $batch->fresh()->executed_by);
        $this->assertNotNull($batch->items()->firstOrFail()->supplier_payment_id);
        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertSame('121.00', $purchase->fresh()->paid_amount);
        $this->assertDatabaseHas('supplier_payments', ['purchase_document_id' => $purchase->id, 'amount' => 100, 'reference' => $batch->number]);
        $batchPayment = SupplierPayment::findOrFail($batch->items()->firstOrFail()->supplier_payment_id);
        $transaction = $account->transactions()->create(['booked_on' => today(), 'amount' => -100, 'currency' => 'EUR', 'description' => 'Executed batch transfer', 'import_hash' => hash('sha256', 'payment-batch-reconciliation'), 'status' => 'reconciled']);
        $reconciliation = BankReconciliation::create(['bank_transaction_id' => $transaction->id, 'supplier_payment_id' => $batchPayment->id, 'reconciled_by' => $admin->id, 'method' => 'manual', 'reconciled_at' => now()]);
        $this->assertSame('reconciled', $batch->fresh()->status);
        $this->assertNotNull($batch->fresh()->reconciled_at);
        $reconciliation->delete();
        $this->assertSame('paid', $batch->fresh()->status);
        $this->assertNull($batch->fresh()->reconciled_at);
        $this->post(route('finance.payment-batches.execute', $batch), ['paid_on' => today()->toDateString()])->assertStatus(422);
        $this->assertGreaterThanOrEqual(4, AuditLog::whereIn('auditable_type', [FinancialForecastItem::class, FinancialBudget::class, CollectionAction::class, PaymentBatch::class])->count());
    }

    public function test_payment_batch_creator_cannot_approve_their_own_batch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create(['legal_name' => 'Independent approval SL', 'country' => 'ES', 'iban' => 'ES9121000418450200051332']);
        $account = BankAccount::create(['name' => 'Payments', 'iban' => 'ES7921000813610123456789', 'currency' => 'EUR', 'opening_balance' => 5000, 'is_active' => true]);
        $purchase = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'FR-SOD-1', 'status' => 'registered', 'issue_date' => now(), 'due_date' => now(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0]);

        $this->actingAs($admin)->post(route('finance.payment-batches.store'), [
            'bank_account_id' => $account->id,
            'planned_on' => today()->toDateString(),
            'payables' => ['invoice:'.$purchase->id],
        ])->assertSessionHasNoErrors();
        $batch = PaymentBatch::firstOrFail();

        $this->post(route('finance.payment-batches.approve', $batch))
            ->assertSessionHasErrors('payment_batch');
        $this->assertSame('draft', $batch->fresh()->status);
        $this->assertNull($batch->approved_by);
    }

    public function test_cancelling_a_payment_batch_releases_its_documents(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Supplier SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $purchase = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'FR-CANCEL-1', 'status' => 'registered', 'issue_date' => now(), 'due_date' => now()->addWeek(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0]);

        $this->actingAs($commercial)->post(route('finance.payment-batches.store'), [
            'planned_on' => now()->addWeek()->toDateString(),
            'payables' => ['invoice:'.$purchase->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $batch = PaymentBatch::firstOrFail();

        $this->post(route('finance.payment-batches.cancel', $batch))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $batch->fresh()->status);
        $this->post(route('finance.payment-batches.store'), [
            'planned_on' => now()->addDays(8)->toDateString(),
            'payables' => ['invoice:'.$purchase->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_batches', 2);
    }

    public function test_executing_a_batch_stops_if_a_balance_changed_after_export(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create(['legal_name' => 'Supplier SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $purchase = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'FR-CHANGED-1', 'status' => 'partial', 'issue_date' => now(), 'due_date' => now(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 21]);
        $batch = PaymentBatch::create(['number' => 'PAY-CHANGED-1', 'planned_on' => today(), 'status' => 'exported', 'created_by' => $admin->id, 'exported_at' => now()]);
        $batch->items()->create(['payable_type' => 'invoice', 'payable_id' => $purchase->id, 'amount' => 121]);

        $this->actingAs($admin)->post(route('finance.payment-batches.execute', $batch), ['paid_on' => today()->toDateString()])
            ->assertRedirect()
            ->assertSessionHasErrors('payment_batch');

        $this->assertSame('exported', $batch->fresh()->status);
        $this->assertNull($batch->items()->firstOrFail()->supplier_payment_id);
        $this->assertDatabaseMissing('supplier_payments', ['purchase_document_id' => $purchase->id]);
    }

    public function test_collection_actions_follow_invoice_payment_and_reversal(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $sale = SalesDocument::create(['type' => 'invoice', 'number' => 'FAC-COLLECTION-1', 'status' => 'overdue', 'issue_date' => now(), 'due_date' => today()->subWeek(), 'issued_at' => now(), 'locked_at' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'customer_snapshot' => []]);
        $action = CollectionAction::create(['sales_document_id' => $sale->id, 'assigned_to' => $commercial->id, 'status' => 'promised', 'promised_on' => today(), 'promised_amount' => 121, 'next_action_on' => today()]);

        $this->actingAs($commercial);
        $sale->update(['status' => 'paid', 'paid_amount' => 121]);

        $this->assertSame('resolved', $action->fresh()->status);
        $this->assertSame('payment_received', $action->fresh()->resolution_reason);
        $this->assertNotNull($action->fresh()->resolved_at);
        $this->assertNull($action->fresh()->next_action_on);

        $sale->update(['status' => 'partial', 'paid_amount' => 100]);

        $this->assertSame('open', $action->fresh()->status);
        $this->assertNull($action->fresh()->resolution_reason);
        $this->assertNull($action->fresh()->resolved_at);
        $this->assertSame(today()->toDateString(), $action->fresh()->next_action_on->toDateString());
        $this->assertDatabaseHas('collection_action_events', ['collection_action_id' => $action->id, 'event_type' => 'resolved', 'status_from' => 'promised', 'status_to' => 'resolved']);
        $this->assertDatabaseHas('collection_action_events', ['collection_action_id' => $action->id, 'event_type' => 'reopened', 'status_from' => 'resolved', 'status_to' => 'open']);
    }

    public function test_finance_permissions_keep_technicians_out_and_split_approval_from_treasury(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($technician)->get(route('finance.index'))->assertForbidden();
        $this->post(route('finance.forecast-items.store'), ['direction' => 'in'])->assertForbidden();
        $this->patch(route('finance.alert-settings.update'), [])->assertForbidden();
        $this->patch(route('finance.alerts.update'), [])->assertForbidden();
        $this->post(route('finance.budgets.import'), [])->assertForbidden();
        $this->post(route('finance.budgets.approve', now()->year), [])->assertForbidden();
    }

    public function test_thirteen_week_forecast_uses_current_bank_balance_and_expands_recurring_items(): void
    {
        $account = BankAccount::create(['name' => 'Forecast bank', 'currency' => 'EUR', 'opening_balance' => 1000, 'is_active' => true]);
        $account->transactions()->create(['booked_on' => today(), 'amount' => -100, 'currency' => 'EUR', 'description' => 'Today movement', 'import_hash' => hash('sha256', 'forecast-current-balance'), 'status' => 'pending']);
        FinancialForecastItem::create(['direction' => 'out', 'category' => 'rent', 'description' => 'Monthly rent', 'amount' => 100, 'due_on' => today(), 'probability' => 100, 'recurrence' => 'monthly', 'status' => 'planned']);

        $forecast = app(TreasuryForecast::class)->build(now()->year, 'optimistic');

        $this->assertSame(900.0, $forecast['openingBalance']);
        $this->assertSame('Forecast bank', $forecast['openingSources']->first()['name']);
        $this->assertSame('/banking?account='.$account->id, $forecast['openingSources']->first()['href']);
        $this->assertGreaterThanOrEqual(300, $forecast['weeks']->sum('out'));
        $this->assertSame($forecast['openingBalance'] - $forecast['weeks']->sum('out'), $forecast['closingBalance']);
        $rent = $forecast['weeks']->flatMap(fn (array $week) => $week['events'])->firstWhere('sourceType', 'forecast_item');
        $this->assertSame('Monthly rent', $rent['label']);
        $this->assertSame(100.0, $rent['originalAmount']);
        $this->assertSame('#forecast-items', $rent['href']);
    }

    public function test_treasury_forecast_explains_scenario_adjustments_and_does_not_mix_currencies(): void
    {
        $this->travelTo('2026-08-10');
        $euro = BankAccount::create(['name' => 'EUR operating', 'currency' => 'EUR', 'opening_balance' => 1000, 'is_active' => true]);
        BankAccount::create(['name' => 'USD reserve', 'currency' => 'USD', 'opening_balance' => 500, 'is_active' => true]);
        BankAccount::create(['name' => 'Closed EUR', 'currency' => 'EUR', 'opening_balance' => 900, 'is_active' => false]);
        $invoice = SalesDocument::create([
            'type' => 'invoice',
            'number' => 'FAC-TRACE-1',
            'status' => 'issued',
            'issue_date' => today(),
            'due_date' => today()->addDay(),
            'issued_at' => now(),
            'locked_at' => now(),
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 21,
            'amount' => 121,
            'paid_amount' => 0,
            'customer_snapshot' => [],
        ]);

        $forecast = app(TreasuryForecast::class)->build(2026, 'probable');
        $event = $forecast['weeks']->flatMap(fn (array $week) => $week['events'])->firstWhere('sourceType', 'receivable');

        $this->assertSame('EUR', $forecast['baseCurrency']);
        $this->assertSame(1000.0, $forecast['openingBalance']);
        $this->assertSame($euro->id, $forecast['openingSources']->sole()['id']);
        $this->assertSame('USD', $forecast['excludedCurrencyBalances']->sole()['currency']);
        $this->assertSame(500.0, $forecast['excludedCurrencyBalances']->sole()['balance']);
        $this->assertSame($invoice->id, $event['sourceId']);
        $this->assertSame(121.0, $event['originalAmount']);
        $this->assertSame(108.9, $event['amount']);
        $this->assertSame('/sales/'.$invoice->id, $event['href']);
        $this->assertSame(108.9, $forecast['weeks']->sum('in'));
    }

    public function test_treasury_forecast_detects_an_intraday_range_shortfall_hidden_by_the_weekly_close(): void
    {
        $this->travelTo('2026-08-10');
        BankAccount::create(['name' => 'Operating account', 'currency' => 'EUR', 'opening_balance' => 50, 'is_active' => true]);
        FinancialForecastItem::create([
            'direction' => 'out',
            'category' => 'supplier',
            'description' => 'Supplier maturity',
            'amount' => 100,
            'due_on' => '2026-08-11',
            'probability' => 100,
            'status' => 'planned',
        ]);
        FinancialForecastItem::create([
            'direction' => 'in',
            'category' => 'collection',
            'description' => 'Customer collection',
            'amount' => 100,
            'due_on' => '2026-08-12',
            'probability' => 100,
            'status' => 'planned',
        ]);

        $forecast = app(TreasuryForecast::class)->build(2026, 'probable');
        $shortfallDay = $forecast['days']->firstWhere('date', '2026-08-11');
        $recoveryDay = $forecast['days']->firstWhere('date', '2026-08-12');

        $this->assertCount(91, $forecast['days']);
        $this->assertSame(-50.0, $shortfallDay['closing']);
        $this->assertSame(1, $shortfallDay['maturities']);
        $this->assertSame(50.0, $recoveryDay['closing']);
        $this->assertSame(-50.0, $forecast['lowestBalance']);
        $this->assertSame('2026-08-11', $forecast['lowestBalanceOn']);
        $this->assertSame(50.0, $forecast['weeks']->first()['closing']);
        $this->assertSame(50.0, $forecast['closingBalance']);
    }

    public function test_shared_card_receipt_is_forecast_once_on_its_payment_day(): void
    {
        $this->travelTo('2026-08-06');
        $account = BankAccount::create(['name' => 'Card debit account', 'currency' => 'EUR', 'opening_balance' => 1000, 'is_active' => true]);
        $facility = BankFinancialProduct::create([
            'bank_account_id' => $account->id,
            'type' => 'credit_facility',
            'name' => 'Shared card credit',
            'currency' => 'EUR',
            'credit_limit' => 4800,
            'used_amount' => 500,
            'billing_day' => 31,
            'payment_day' => 5,
            'is_active' => true,
        ]);
        foreach ([300, 200] as $index => $used) {
            BankFinancialProduct::create([
                'bank_account_id' => $account->id,
                'shared_credit_facility_id' => $facility->id,
                'type' => 'credit_card',
                'name' => 'Card '.($index + 1),
                'currency' => 'EUR',
                'credit_limit' => 4800,
                'used_amount' => $used,
                'is_active' => true,
            ]);
        }

        $forecast = app(TreasuryForecast::class)->build(2026, 'optimistic');
        $paymentWeek = $forecast['weeks']->first(fn (array $week) => $week['from'] <= '2026-09-05' && $week['to'] >= '2026-09-05');

        $this->assertNotNull($paymentWeek);
        $this->assertSame(500.0, $paymentWeek['out']);
        $this->assertSame(500.0, $forecast['weeks']->sum('out'));
        $this->assertSame(500.0, $forecast['closingBalance']);
    }

    public function test_independent_credit_card_is_included_in_the_treasury_forecast(): void
    {
        $this->travelTo('2026-08-06');
        $account = BankAccount::create(['name' => 'Independent card debit', 'currency' => 'EUR', 'opening_balance' => 1000, 'is_active' => true]);
        $card = BankFinancialProduct::create([
            'bank_account_id' => $account->id,
            'type' => 'credit_card',
            'name' => 'Independent purchasing card',
            'currency' => 'EUR',
            'credit_limit' => 2000,
            'used_amount' => 120,
            'billing_day' => 25,
            'payment_day' => 5,
            'is_active' => true,
        ]);

        $forecast = app(TreasuryForecast::class)->build(2026, 'probable');
        $event = $forecast['weeks']->flatMap(fn (array $week) => $week['events'])->sole('sourceType', 'credit_card');

        $this->assertSame($card->id, $event['sourceId']);
        $this->assertSame(120.0, $event['amount']);
        $this->assertSame(120.0, $forecast['weeks']->sum('out'));
        $this->assertSame(880.0, $forecast['closingBalance']);
    }
}
