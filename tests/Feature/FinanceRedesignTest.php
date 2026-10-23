<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialForecastItem;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Finance\TreasuryForecast;
use App\Support\WorkspaceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function account(array $data = []): BankAccount
    {
        return BankAccount::create([...['name' => 'Cuenta real', 'currency' => 'EUR', 'opening_balance' => '1000.00', 'is_active' => true, 'iban' => 'ES1234567890123456789012'], ...$data]);
    }

    private function transaction(BankAccount $account, string $amount, array $data = []): BankTransaction
    {
        return $account->transactions()->create([...['amount' => $amount, 'currency' => $account->currency, 'booked_on' => today()->toDateString(), 'import_hash' => Str::random(64), 'description' => 'Movimiento real', 'status' => 'pending'], ...$data]);
    }

    private function invoice(array $data = []): SalesDocument
    {
        return SalesDocument::create([...['number' => 'INV-'.Str::random(8), 'type' => 'invoice', 'status' => 'issued', 'issue_date' => today()->toDateString(), 'due_date' => today()->addDays(5)->toDateString(), 'amount' => '121.00', 'subtotal' => '100.00', 'tax_amount' => '21.00', 'paid_amount' => '0.00', 'customer_snapshot' => ['company_name' => 'Cliente real'], 'locked_at' => now()], ...$data]);
    }

    public function test_dashboard_separates_recorded_balance_available_and_invoice_credit_balances(): void
    {
        $account = $this->account();
        $this->transaction($account, '200.01');
        $this->transaction($account, '-100.01');
        $this->transaction($account, '999.00', ['booked_on' => today()->addDay()->toDateString()]);
        $this->account(['name' => 'Dólares', 'iban' => null, 'currency' => 'USD', 'opening_balance' => '9999.00']);
        $invoice = $this->invoice(['paid_amount' => '20.00']);
        $this->invoice(['type' => 'credit_note', 'source_document_id' => $invoice->id, 'amount' => '-11.00']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/finance')->assertOk()->assertInertia(fn (Assert $p) => $p->component('finance/workspace')->where('metrics.registered', '1100.00')->where('metrics.available', null)->where('metrics.receivable', '90.00')->where('metrics.flow', '100.00')->where('accounts.0.iban_masked', '•••• 9012')->where('capabilities.multiCompany', false));
    }

    public function test_matched_payment_is_not_counted_twice_in_treasury(): void
    {
        $account = $this->account();
        $invoice = $this->invoice();
        $payment = $invoice->payments()->create(['paid_at' => today(), 'amount' => '121.00', 'method' => 'bank_transfer']);
        $tx = $this->transaction($account, '121.00', ['status' => 'reconciled']);
        BankReconciliation::create(['bank_transaction_id' => $tx->id, 'sales_document_payment_id' => $payment->id, 'method' => 'manual', 'reconciled_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/finance?area=treasury')->assertOk()->assertInertia(fn (Assert $p) => $p->where('movements.total', 1)->where('totals.in', '121.00'));
        $this->get('/finance?area=treasury&source=unmatched')->assertInertia(fn (Assert $p) => $p->where('movements.total', 0));
    }

    public function test_own_transfer_does_not_inflate_external_cash_flow(): void
    {
        $a = $this->account();
        $b = $this->account(['name' => 'Otra cuenta', 'iban' => null]);
        $out = $this->transaction($a, '-50');
        $in = $this->transaction($b, '50');
        BankTransfer::create(['outgoing_transaction_id' => $out->id, 'incoming_transaction_id' => $in->id, 'amount' => '50.00', 'transferred_on' => today(), 'matched_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/finance')->assertInertia(fn (Assert $p) => $p->where('totals.in', '0.00')->where('totals.out', '0.00')->where('metrics.registered', '2000.00'));
    }

    public function test_movement_search_sort_pagination_and_account_filter_are_backend(): void
    {
        $a = $this->account();
        for ($i = 0; $i < 12; $i++) {
            $this->transaction($a, (string) ($i + 1), ['description' => 'Registro '.$i]);
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/finance?area=treasury')->assertInertia(fn (Assert $p) => $p->has('movements.data', 10)->where('movements.total', 12));
        $this->get('/finance?area=treasury&search=Registro%2011')->assertInertia(fn (Assert $p) => $p->where('movements.total', 1));
        $this->get('/finance?area=treasury&sort=amount&direction=asc')->assertInertia(fn (Assert $p) => $p->where('movements.data.0.amount', '1.00'));
    }

    public function test_forecast_uses_existing_obligations_exact_money_and_horizons(): void
    {
        $this->account();
        $invoice = $this->invoice();
        $this->invoice(['type' => 'credit_note', 'source_document_id' => $invoice->id, 'amount' => '-21.00']);
        FinancialForecastItem::create(['direction' => 'out', 'category' => 'other', 'description' => 'Previsión manual real', 'amount' => '0.10', 'due_on' => today()->addDays(7), 'probability' => 100, 'status' => 'planned']);
        Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice', 'number' => 'FX-1', 'amount' => '999', 'subtotal' => '999', 'tax_amount' => '0', 'paid_amount' => '0', 'currency_code' => 'USD', 'status' => 'pending', 'expense_date' => today(), 'due_date' => today()->addDay()]);
        $result = app(TreasuryForecast::class)->build(today()->year, 'optimistic', exact: true);
        $this->assertSame('1099.90', $result['days']->get(7)['closing']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([7, 30, 60, 90] as $n) {
            $this->get('/finance?area=forecasts&horizon='.$n.'&scenario=optimistic')->assertOk()->assertInertia(fn (Assert $p) => $p->where('forecast.horizon', $n)->where('forecast.closing', '1099.90'));
        }
    }

    public function test_no_bank_accounts_means_no_available_forecast_not_a_fake_zero_estimate(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/finance?area=forecasts')->assertInertia(fn (Assert $p) => $p->where('forecast.available', false)->where('forecast.closing', null)->where('metrics.available', null));
    }

    public function test_manual_forecast_retries_preserve_one_record_and_changed_payload_fails(): void
    {
        $data = ['operation_key' => (string) Str::uuid(), 'direction' => 'out', 'description' => 'Necesidad manual', 'amount' => '100.10', 'due_on' => today()->addDays(3)->toDateString(), 'probability' => 100];
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/finance/forecast-items', $data)->assertSessionHasNoErrors();
        $this->post('/finance/forecast-items', $data)->assertSessionHasNoErrors();
        $this->assertSame(1, FinancialForecastItem::count());
        $this->post('/finance/forecast-items', [...$data, 'amount' => '101.10'])->assertSessionHasErrors('operation_key');
        $this->patch('/finance/forecast-items/'.FinancialForecastItem::firstOrFail()->id, ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('financial_forecast_items', ['status' => 'cancelled']);
    }

    public function test_finance_navigation_has_parent_and_exactly_three_children(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $m = collect(app(WorkspaceNavigation::class)->for($u))->firstWhere('id', 'finance');
        $this->assertSame('/finance', $m['href']);
        $this->assertTrue($m['navigable']);
        $this->assertSame(['Tesorería', 'Bancos y conciliación', 'Previsiones'], array_column($m['children'], 'title'));
    }

    public function test_financial_viewer_cannot_write_and_sensitive_bank_data_is_masked(): void
    {
        $a = $this->account();
        Gate::define('manage-treasury', fn () => false);
        $this->actingAs(User::factory()->create(['role' => 'commercial']))->get('/banking')->assertOk()->assertInertia(fn (Assert $p) => $p->where('accounts.0.iban', '•••• 9012')->where('selectedAccount.iban', '•••• 9012')->where('capabilities.manage', false));
        $this->patch('/banking/accounts/'.$a->id, ['name' => 'Changed', 'is_active' => false])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'technician']))->get('/finance')->assertForbidden();
    }

    public function test_bank_account_can_be_deactivated_without_deleting_its_history(): void
    {
        $a = $this->account();
        $this->transaction($a, '-1.00');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch('/banking/accounts/'.$a->id, ['name' => 'Nombre revisado', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame(1, $a->transactions()->count());
        $this->assertFalse($a->fresh()->is_active);
    }

    public function test_bank_calendar_dates_and_month_scope_are_preserved(): void
    {
        $account = $this->account();
        $tx = $this->transaction($account, '10.10', ['booked_on' => '2026-08-31', 'value_on' => '2026-09-01']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/banking')->assertInertia(fn (Assert $p) => $p->where('transactions.data.0.booked_on', '2026-08-31')->where('transactions.data.0.value_on', '2026-09-01'));
        $this->get('/finance?area=treasury&month=2026-08')->assertInertia(fn (Assert $p) => $p->where('movements.total', 1)->where('movements.data.0.id', $tx->id)->where('cashCategories.0.name', 'Sin clasificar')->where('cashCategories.0.in', '10.10'));
        $this->get('/finance?area=treasury&month=2026-09')->assertInertia(fn (Assert $p) => $p->where('movements.total', 0));
    }
}
